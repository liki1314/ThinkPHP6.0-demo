<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-17
 * Time: 14:25
 */

namespace app\home\controller\v1;


use app\home\controller\Base;
use app\home\model\saas\Company;
use app\home\model\saas\FrontUser;
use app\home\model\saas\Homework;
use app\home\model\saas\RoomUser;
use app\home\model\saas\teacher\HomeworkRecord;
use app\home\model\saas\Room;
use app\home\model\saas\UsefulExpression;
use app\home\validate\Homework as HomeworkValidate;
use app\home\validate\HomeworkRecord as HomeworkRecordValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Cache;
use app\common\service\Upload;
use app\home\model\saas\HomeworkRecordLog;
use think\paginator\driver\Bootstrap;

class HomeworkTeacher extends Base
{
    /**
     * 作业列表
     * @return \think\response\Json
     */
    public function index()
    {
        return $this->success($this->searchList(Homework::class));
    }

    /**
     * 布置作业
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function decorate()
    {
        $this->validate($this->param, HomeworkValidate::class);

        Room::findOrFail($this->param['lesson_id']);

        if (!isset($this->param['students']) || empty($this->param['students'])) {
            $students = RoomUser::where('room_id', $this->param['lesson_id'])->column('front_user_id');
        } else {
            $students = $this->param['students'];
        }
        $id = '';
        Db::transaction(function () use ($students, &$id) {
            $homeworkModel = new Homework();
            $this->param['students'] = count($students);
            $homeworkModel->allowField(Homework::$fieldInsert)->save($this->param);
            //不是草稿就写入学生
            $id = $homeworkModel->id;
            $data = [];
            foreach ($students as $value) {
                $data[] = [
                    "homework_id" => $homeworkModel->id,
                    "student_id" => $value,
                    "teacher_id" => 0,
                    "submit_content" => '',
                    "remark_content" => '',
                ];
            }
            HomeworkRecord::insertAll($data);

            if ($this->param['is_draft'] == Homework::DRAFT_NO) {
                //进行通知
                event('Notice', [
                    'template' => 'homework.assign',
                    'origin' => ['homework_id' => $homeworkModel->id],
                    'front_user_id' => $students
                ]);
            }
        });


        return $this->success(['id' => $id]);
    }


    /**
     * 编辑学生
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function update($id)
    {
        $this->validate($this->param, HomeworkValidate::class);
        // Room::findOrFail($this->param['lesson_id']);
        $homeworkModel = Homework::findOrFail($id);

        if ($homeworkModel['is_draft'] == Homework::DRAFT_NO) {
            if (
                $homeworkModel['issue_status'] == Homework::ISSUE_STATUS ||
                ($homeworkModel['issue_status'] == Homework::ISSUE_STATUS_DATE && strtotime($homeworkModel['day']) < time())
            ) throw new ValidateException(lang("has_been_released_no_save"));
        }

        if (!isset($this->param['day']) || empty($this->param['day'])) {
            $this->param['day'] = date("y-m-d");
            $this->param['create_time'] = time();
        }

        if (!isset($this->param['students']) || empty($this->param['students'])) {
            $students = RoomUser::where('room_id', $this->param['lesson_id'])->column('front_user_id');
        } else {
            $students = $this->param['students'];
        }
        $this->param['students'] = count($students);
        $this->param['issue_status'] = $this->param['issue_status'] ?? Homework::ISSUE_STATUS;

        Db::transaction(function () use ($id, $students, $homeworkModel) {

            $homeworkModel->allowField(Homework::$fieldInsert)->save($this->param);
            HomeworkRecord::where('homework_id', $id)->delete();
            $data = [];
            foreach ($students as $value) {
                $data[] = [
                    "homework_id" => $homeworkModel->id,
                    "student_id" => $value,
                    "teacher_id" => 0,
                    "submit_content" => '',
                    "remark_content" => '',
                ];
            }
            HomeworkRecord::insertAll($data);
        });

        //不是草稿进行通知
        if ($this->param['is_draft'] == Homework::DRAFT_NO) {
            event('Notice', [
                'template' => 'homework.assign',
                'origin' => ['homework_id' => $homeworkModel['id']],
                'front_user_id' => $students
            ]);
        }

        return $this->success();
    }

    /**
     * 删除作业
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete($id)
    {
        $homeworkModel = Homework::findOrFail($id);

        $homeworkModel->delete();

        return $this->success();
    }

    /**
     * 作业详情
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function show($id)
    {
        Homework::where('id', $id)->findOrFail();
        $info = Company::getDetailById($this->request->user['company_id']);
        $res = Homework::with(['students.user' => function ($query) {
            $query->field(['homework_id', 'student_id']);
        }])->findOrFail($id)->append([
            'unsubmits',
            'serial',
            'students_total',
            'submit_rate',
            'read_rate',
            'is_remark',
            'reminds',
        ])->withAttr('reminds', function ($value) use ($info) {
            return $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
        })->withAttr('is_remark', function ($value, $data) use ($info) {
            return $info['notice_config']['homework_remark']['switch'] ?? config('app.notice.homework_remark.switch');
        });

        $resources = Homework::getResources([
            $res['id'] => $res['resources']
        ]);
        $res['resources'] = $resources[$res['id']] ?? [];
        return $this->success($res);
    }

    /**
     * 点评列表
     */
    public function remarkList($id)
    {
        Homework::findOrFail($id);
        $files = [];
        $info = Company::getDetailById($this->request->user['company_id']);
        $list = new Bootstrap([], $this->rows, $this->page, 0);
        $coll = $this->searchList(HomeworkRecord::class);
        if (!$coll->isEmpty()) {
            $list = $coll->withAttr('unreminds', function ($value, $data) use ($info) {
                $max = $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
                return max(($max - $data['reminds']), 0);
            })->each(function ($value) use (&$files) {
                if (isset($value->student) && !$value->student->isEmpty()) {
                    $value->student->companynickname = $value->student->nickname;
                    $value->student->avatar = isset(FrontUser::DEFAULT_AVATAR[$value->student->userroleid]) ?
                        Upload::getFileUrl($value->student->avatar ?: FrontUser::DEFAULT_AVATAR[$value->student->userroleid][$value->student->sex], $value->student->avatar ? '' : 'local') : '';
                }
                if (isset($value->teacher_name) && isset($value->teacher_avatar) && $value->teacher_id > 0) {
                    $value->teacher = [
                        "name" => $value->teacher_name,
                        "avatar" => Upload::getFileUrl($value->teacher_avatar ?: FrontUser::DEFAULT_AVATAR[7][1], $value->teacher_avatar ? '' : 'local')
                    ];
                } else {
                    $value->teacher = ["name" => '', "avatar" => ''];
                }
                $ks = sprintf("%s-%s-submit", $value['homework_id'], $value['student_id']);
                $kr = sprintf("%s-%s-remark", $value['homework_id'], $value['student_id']);
                $files[$ks] = empty($value['submit_files']) ? [] : $value['submit_files'];
                $files[$kr] = empty($value['remark_files']) ? [] : $value['remark_files'];
            })->toArray();
        }
        $files = Homework::getResources($files);

        if (isset($list['data'])) {
            foreach ($list['data'] as $k => $value) {
                $ks = sprintf("%s-%s-submit", $value['homework_id'], $value['student_id']);
                $kr = sprintf("%s-%s-remark", $value['homework_id'], $value['student_id']);
                $list['data'][$k]['submit_files'] = $files[$ks] ?? [];
                $list['data'][$k]['remark_files'] = $files[$kr] ?? [];
            }
        }

        return $this->success($list);
    }

    /**
     * 作业点评
     * @param $id
     * @param null $student_id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function remark($id, $student_id = null)
    {

        if (!empty($student_id)) $this->param['students'] = [$student_id];
        $this->validate($this->param, HomeworkRecordValidate::class);
        Homework::findOrFail($id);
        $handler = Cache::store('redis')->handler();

        try {

            //修改作业时候加锁 过期10秒
            $lock = $handler->SET(sprintf("homework-%s", $id), 1, ["NX", "EX" => 10]);

            if ($lock === false) throw new ValidateException(lang('submit_loading'));

            $remarkTime = HomeworkRecord::where('homework_id', $id)
                ->whereIn('student_id', $this->param['students'])
                ->column("remark_time", 'student_id');

            //判断是否学生是否在这个作业下面
            if (count($remarkTime) != count($this->param['students'])) throw new ValidateException(lang('gid_require'));
            $inc = 0;
            array_map(function ($value) use (&$inc) {
                if ($value == 0) $inc++;
            }, $remarkTime);

            Db::transaction(function () use ($id, $inc) {

                HomeworkRecord::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->save([
                        'remark_content' => $this->param['content'] ?? '',
                        'rank' => $this->param['rank'],
                        'remark_files' => isset($this->param['resources']) && is_array($this->param['resources']) ? Homework::execResources($this->param['resources']) : [],
                        "remark_time" => time(),
                        "teacher_id" => $this->request->user['user_account_id']
                    ]);

                if ($inc > 0) {
                    Homework::where('id', $id)->inc('remarks', $inc)->update();
                }

                if ($this->param['useful_expressions'] == 1 && isset($this->param['content']) && !empty($this->param['content'])) {
                    (new UsefulExpression)->save(['expression' => $this->param['content'] ?? '', 'type' => UsefulExpression::ACCOUNT]);
                }

                //老接口兼容
                HomeworkRecord::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->select()
                    ->each(function ($item) {
                        HomeworkRecordLog::where('homework_record_id', $item['id'])
                            ->order('id', 'desc')
                            ->limit(1)
                            ->save([
                                'remark_content' => $this->param['content'] ?? '',
                                'rank' => $this->param['rank'],
                                'remark_files' => isset($this->param['resources']) && is_array($this->param['resources']) ? Homework::execResources($this->param['resources']) : [],
                                "remark_time" => time(),
                                "teacher_id" => $this->request->user['user_account_id']
                            ]);
                    });
            });
            //释放锁
            $handler->DEL(sprintf("homework-%s", $id));
        } catch (\Exception $e) {
            //释放锁
            $handler->DEL(sprintf("homework-%s", $id));
            throw new ValidateException($e->getMessage());
        }

        event('Notice', [
            'template' => 'homework.remark',
            'origin' => ['homework_id' => $id],
            'front_user_id' => $this->param['students']
        ]);

        return $this->success();
    }

    /**
     * 学生
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function students($id)
    {
        Homework::where('id', $id)->findOrFail();
        $info = Company::getDetailById($this->request->user['company_id']);
        $list = HomeworkRecord::field(['homework_id', 'student_id', 'submit_time', 'rank', 'read_time', 'remark_time', 'reminds'])
            ->withJoin(['student' => ['id', 'nickname', 'avatar', 'userroleid', 'sex']])
            ->with(['homework'])
            ->where('homework_id', $id)
            ->where(function ($query) {
                if (isset($this->param['is_submit'])) {
                    if ($this->param['is_submit'] == 1) {
                        $query->where('submit_time', '>', 0);
                    } else {
                        $query->where('submit_time', 0);
                    }
                }
            })
            ->select()
            ->append(['id', 'status', 'unreminds'])
            ->hidden(['homework_id', 'student_id', 'remark_time', 'read_time', 'company_id', 'reminds'])
            ->each(function ($value) {
                $value->name = $value['student']['name'] ?? '';
                $value->avatar = $value['student']['avatar'] ?? '';
                unset($value->student);
            })
            ->withAttr('submit_time', function ($value) {
                return $value;
            })->withAttr('unreminds', function ($value, $data) use ($info) {
                $max = $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
                return max(($max - $data['reminds']), 0);
            });
        return $this->success($list);
    }

    /**
     * 提醒学生
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function remind($id)
    {
        $this->validate($this->param, ['students' => ['require', 'array']]);
        Homework::findOrFail($id);
        HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $this->param['students'])
            ->save(["remind" => 1]);

        $info = Company::getDetailById($this->request->user['company_id']);
        $maxNum = $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');

        $needStudent = HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $this->param['students'])
            ->where('reminds', '<', $maxNum)
            ->column('student_id');

        if (!$needStudent) {
            return $this->success();
        }

        HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $this->param['students'])
            ->where('reminds', '<', $maxNum)
            ->inc('reminds')
            ->update();

        event('Notice', [
            'template' => 'homework.remind',
            'origin' => ['homework_id' => $id],
            'front_user_id' => $needStudent
        ]);

        return $this->success();
    }

    /**
     * 评语列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function usefulExpression()
    {
        $this->validate($this->param, ['lesson_id' => ['requireWithout:homework_id']]);
        if (!empty($this->param['lesson_id'])) {
            Room::findOrFail($this->param['lesson_id']);
        } else {
            Homework::findOrFail($this->param['homework_id']);
        }

        $UsefulExpression = new UsefulExpression();
        $UsefulExpression->isPage = false;
        return $this->success($this->searchList($UsefulExpression));
    }

    /**
     * 删除快捷语
     * @param $id
     * @return \think\response\Json
     */
    public function usefulExpressionDel($id)
    {
        UsefulExpression::where('id', $id)
            ->where('useful_id', $this->request->user['user_account_id'])
            ->where('type', UsefulExpression::ACCOUNT)
            ->delete();

        return $this->success();
    }


    /**
     * 删除点评
     * @param $id
     * @return \think\response\Json
     */
    public function delRemark($id)
    {
        $this->validate($this->param, ['students' => ['require', 'array'],], ['students' => 'homework_students__array']);

        $handler = Cache::store('redis')->handler();

        try {

            //修改作业时候加锁 过期10秒
            $lock = $handler->SET(sprintf("homework-%s", $id), 1, ["NX", "EX" => 10]);
            if ($lock === false) throw new ValidateException(lang('submit_loading'));

            //查看哪些已经点评过，点评过就不写入主表
            $remarkTime = HomeworkRecord::where('homework_id', $id)
                ->whereIn('student_id', $this->param['students'])
                ->column("remark_time");

            if (count($remarkTime) != count($this->param['students'])) throw new ValidateException(lang('homework_students_count'));

            $dec = 0;

            array_map(function ($value) use (&$dec) {
                if ($value > 0) $dec++;
            }, $remarkTime);

            Db::transaction(function () use ($id, $dec) {

                HomeworkRecord::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->save([
                        'remark_content' => '',
                        'rank' => 0,
                        'remark_files' => [],
                        "remark_time" => 0,
                        "teacher_id" => 0
                    ]);

                if ($dec > 0) {
                    Homework::where('id', $id)->dec('remarks', $dec)->update();
                }
                //老接口兼容
                HomeworkRecord::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->select()
                    ->each(function ($item) {
                        HomeworkRecordLog::where('homework_record_id', $item['id'])
                            ->order('id', 'desc')
                            ->limit(1)
                            ->save([
                                'remark_content' => '',
                                'rank' => 0,
                                'remark_files' => [],
                                "remark_time" => 0,
                                "teacher_id" => 0
                            ]);
                    });
            });
            //释放锁
            $handler->DEL(sprintf("homework-%s", $id));
        } catch (\Exception $e) {
            //释放锁
            $handler->DEL(sprintf("homework-%s", $id));
            throw new ValidateException($e->getMessage());
        }
        return $this->success();
    }
}
