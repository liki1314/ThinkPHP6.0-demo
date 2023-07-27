<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-30
 * Time: 14:06
 */

namespace app\admin\controller;

use app\admin\model\{FrontUser,Company};
use \app\admin\model\HomeworkRecord as HomeworkRecordModel;
use app\admin\model\UsefulExpression as UsefulExpressionModel;
use app\common\service\Upload;
use app\admin\model\Homework as HomeworkModel;
use app\admin\validate\HomeworkRecord as HomeworkRecordValidate;
use think\facade\Db;
use think\exception\ValidateException;
use think\Paginator;
use think\facade\Cache;

/**
 * 作业点评
 * Class HomeworkRecord
 * @package app\admin\controller
 */
class HomeworkRecord extends Base
{
    /**
     * 点评列表
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function index($id)
    {
        $info = Company::getDetailById($this->request->user['company_id']);
        $count = HomeworkModel::where('id', $id)->count();
        $res  = $this->searchList(HomeworkRecordModel::class);
        if ($count > 0 && !$res->isEmpty()) {
            $files = [];
            $list = $res->withAttr('unreminds', function ($value, $data) use ($info) {
                    $max = $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
                    return max(($max - $data['reminds']), 0);
                })
                ->each(function ($value) use (&$files) {
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
                });

            $files = HomeworkModel::getResources($files);

            $list->each(function ($value) use ($files) {
                $ks = sprintf("%s-%s-submit", $value['homework_id'], $value['student_id']);
                $kr = sprintf("%s-%s-remark", $value['homework_id'], $value['student_id']);
                $value->submit_files = $files[$ks] ?? [];
                $value->remark_files = $files[$kr] ?? [];
            });
        } else {
            $list = Paginator::make([], 50, 1, 0);
        }

        return $this->success($list);
    }


    /**
     * 进行点评
     * @param $id
     * @param $student_id   为空进行批量点评
     * @return \think\response\Json
     */
    public function save($id, $student_id = null)
    {
        if (!empty($student_id)) $this->param['students'] = [$student_id];
        $this->validate($this->param, HomeworkRecordValidate::class);
        $handler = Cache::store('redis')->handler();

        try {

            //修改作业时候加锁 过期10秒
            $lock = $handler->SET(sprintf("homework-%s", $id), 1, ["NX", "EX" => 10]);
            if ($lock === false) throw new ValidateException(lang('submit_loading'));

            //查看哪些已经点评过，点评过就不写入主表
            $remarkTime = HomeworkRecordModel::where('homework_id', $id)
                ->whereIn('student_id', $this->param['students'])
                ->column("remark_time", 'student_id');

            //判断是否学生是否在这个作业下面
            if (count($remarkTime) != count($this->param['students'])) throw new ValidateException(lang('gid_require'));

            $inc = 0;
            array_map(function ($value) use (&$inc) {
                if ($value == 0) $inc++;
            }, $remarkTime);

            Db::transaction(function () use ($id, $inc) {

                HomeworkRecordModel::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->save([
                        'remark_content' => $this->param['content'] ?? '',
                        'rank' => $this->param['rank'],
                        'remark_files' => $this->param['resources'] ?? [],
                        "remark_time" => time(),
                        "teacher_id" => $this->request->user['user_account_id']
                    ]);

                if ($inc > 0) {
                    HomeworkModel::where('id', $id)->inc('remarks', $inc)->update();
                }
            });

            if ($this->param['useful_expressions'] == 1 && isset($this->param['content']) && !empty($this->param['content'])) {
                (new UsefulExpressionModel)->save(['expression' => $this->param['content'] ?? '', 'type' => UsefulExpressionModel::ACCOUNT]);
            }
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
     * 删除点评
     * @param $id
     * @return \think\response\Json
     */
    public function del($id)
    {
        $this->validate($this->param, ['students' => ['require', 'array'],], ['students' => 'homework_students__array']);

        $handler = Cache::store('redis')->handler();

        try {

            //修改作业时候加锁 过期10秒
            $lock = $handler->SET(sprintf("homework-%s", $id), 1, ["NX", "EX" => 10]);
            if ($lock === false) throw new ValidateException(lang('submit_loading'));

            //查看哪些已经点评过，点评过就不写入主表
            $remarkTime = HomeworkRecordModel::where('homework_id', $id)
                ->whereIn('student_id', $this->param['students'])
                ->column("remark_time");

            if (count($remarkTime) != count($this->param['students'])) throw new ValidateException(lang('homework_students_count'));

            $dec = 0;

            array_map(function ($value) use (&$dec) {
                if ($value > 0) $dec++;
            }, $remarkTime);

            Db::transaction(function () use ($id, $dec) {

                HomeworkRecordModel::where('homework_id', $id)
                    ->whereIn('student_id', $this->param['students'])
                    ->save([
                        'remark_content' => '',
                        'rank' => 0,
                        'remark_files' => [],
                        "remark_time" => 0,
                        "teacher_id" => 0
                    ]);

                if ($dec > 0) {
                    HomeworkModel::where('id', $id)->dec('remarks', $dec)->update();
                }
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
