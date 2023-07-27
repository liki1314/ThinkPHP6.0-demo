<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-29
 * Time: 11:29
 */

namespace app\admin\controller;

use app\admin\model\HomeworkRecord;
use app\admin\validate\Homework as HomeworkValidate;
use app\admin\model\Homework as HomeworkModel;
use think\exception\ValidateException;
use think\facade\Db;
use app\admin\model\Company;
/**
 * 作业
 * Class HomeWork
 * @package app\admin\controller
 */
class Homework extends Base
{

    /**
     * 作业列表
     * @return \think\response\Json
     */
    public function index()
    {
        return $this->success($this->searchList(HomeworkModel::class));
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
    public function info($id)
    {
        $info = Company::getDetailById($this->request->user['company_id']);

        $homeworkInfo = HomeworkModel::with(['students' => function ($query) {
            $query->field(['homework_id', 'student_id']);
        }])->field(['id', 'day', 'title', 'content', 'resources',
            'submit_way', 'issue_status', 'create_by', 'is_draft',
            'room_id', 'reads', 'submits', 'remarks', 'students as students_total', 'create_time'])
            ->findOrFail($id)
            ->append(['submit_rate', 'read_rate', 'is_remark', 'reminds'])
            ->withAttr('reminds', function ($value) use ($info) {
                return $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
            })->withAttr('is_remark', function ($value, $data) use ($info) {
                return $info['notice_config']['homework_remark']['switch'] ?? config('app.notice.homework_remark.switch');
            });

        $homeworkInfo->students->each(function ($item, $key) use ($homeworkInfo) {
            $homeworkInfo->students[$key] = $item->student_id;
        });

        $resources = HomeworkModel::getResources([
            $homeworkInfo['id'] => $homeworkInfo['resources']
        ]);

        $homeworkInfo['resources'] = $resources[$homeworkInfo['id']] ?? [];

        return $this->success($homeworkInfo);
    }

    /**
     * 布置作业
     * @return \think\response\Json
     */
    public function add()
    {

        $this->validate($this->param, HomeworkValidate::class);

        if (isset($this->param['serial'])) {
            $this->param['room_id'] = $this->param['serial'];
        }

        Db::transaction(function () {

            $homeworkModel = new HomeworkModel();
            $homeworkModel->allowField(HomeworkModel::$fieldInsert)->save($this->param);
            $data = [];
            foreach ($this->param['students'] as $value) {
                $data[] = [
                    "homework_id" => $homeworkModel->id,
                    "student_id" => $value,
                    "teacher_id" => 0,
                    "submit_content" => '',
                    "remark_content" => '',
                ];
            }
            HomeworkRecord::insertAll($data);
            if ($this->param['is_draft'] == 0) {
                event('Notice', [
                    'template' => 'homework.assign',
                    'origin' => ['homework_id' => $homeworkModel->id],
                    'front_user_id' => $this->param['students']
                ]);
            }
        });


        return $this->success();
    }


    /**
     * 修改作业
     * @param $id
     * @return \think\response\Json
     */
    public function save($id)
    {
        $this->validate($this->param, HomeworkValidate::class);

        $homeworkModel = new HomeworkModel();
        $homework = $homeworkModel->withoutGlobalScope()->findOrFail($id);
        if ($homework->is_draft == HomeworkModel::DRAFT_NO) {
            if ($homework->issue_status == HomeworkModel::ISSUE_RELEASE ||
                ($homework->issue_status == HomeworkModel::ISSUE_DATE && strtotime($homework->day) < time())
            ) throw new ValidateException(lang("has_been_released_no_save"));
        }

        Db::transaction(function () use ($homework) {

            $recordIds = HomeworkRecord::where('homework_id', $homework->id)->column('student_id');
            $homework->allowField(HomeworkModel::$fieldInsert)->save($this->param);
            //找到要删除的学生
            $delData = array_diff($recordIds, $this->param['students']);
            //找到要新增的学生
            $addData = array_diff($this->param['students'], $recordIds);
            if (!empty($delData)) {
                HomeworkRecord::where('homework_id', $homework->id)
                    ->whereIn("student_id", $delData)
                    ->delete();
            }
            if (!empty($addData)) {
                $data = [];
                foreach ($addData as $value) {
                    $data[] = [
                        "homework_id" => $homework->id,
                        "student_id" => $value
                    ];
                }
                HomeworkRecord::insertAll($data);
            }
        });


        return $this->success();
    }


    /**
     * 删除作业
     * @param $id
     * @return \think\response\Json
     */
    public function del($id)
    {
        $model = HomeworkModel::where('id', $id)->findOrFail();
        $model->delete();
        return $this->success();
    }


    /**
     * 提醒学生
     * @param $id
     * @return \think\response\Json
     */
    public function remind($id)
    {
        $this->validate($this->param, ['students' => ['require', 'array']]);

        $info = Company::getDetailById($this->request->user['company_id']);
        $configMax = $info['notice_config']['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
        //找出提醒次数未达上限的学生
        $needReind = HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $this->param['students'])
            ->where('reminds', '<', $configMax)
            ->column('student_id');

        if (!$needReind) {
            return $this->success();
        }

        HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $needReind)
            ->save(["remind" => 1]);

        HomeworkRecord::where("homework_id", $id)
            ->whereIn("student_id", $needReind)
            ->inc('reminds')
            ->update();

        event('Notice', [
            'template' => 'homework.remind',
            'origin' => ['homework_id' => $id],
            'front_user_id' => $needReind,
        ]);

        return $this->success();
    }

}
