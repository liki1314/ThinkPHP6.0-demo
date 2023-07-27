<?php

declare(strict_types=1);

namespace app\home\controller\v3;

use app\home\controller\Base;
use app\home\model\saas\Homework as SaasHomework;
use app\home\model\saas\HomeworkRecord;
use app\home\model\saas\HomeworkRecordLog as SaasHomeworkRecordLog;
use app\home\validate\HomeworkRecordLog;
use app\home\validate\HomeworkRecord as ValidateHomeworkRecord;
use app\home\model\saas\FrontUser;

class Homework extends Base
{
    public function record($homeworkId)
    {
        SaasHomework::findOrFail($homeworkId);
        $model = HomeworkRecord::withSearch(['record'], $this->param)->findOrFail();

        $files = [];
        foreach ($model['records'] as $key => $value) {
            $files[sprintf('%d_submit_files', $value['id'])] = $value['resources'];
            $files[sprintf('%d_remark_files', $value['id'])] = $value['remark']['resources'] ?? null;
        }
        $files = SaasHomework::getResources(array_filter($files));

        $model = $model->toArray();
        foreach ($model['records'] as $key => &$value) {
            $value['resources'] = $files[sprintf('%d_submit_files', $value['id'])] ?? [];
            if (isset($value['remark'])) {
                $value['remark']['resources'] = $files[sprintf('%d_remark_files', $value['id'])] ?? [];
            }
        }

        return $this->success($model);
    }

    public function updateRecord($recordId)
    {
        $this->validate($this->param, HomeworkRecordLog::class);

        /** @var  SaasHomeworkRecordLog*/
        $model = SaasHomeworkRecordLog::findOrFail($recordId);
        $model->saveSubmit($this->param);

        //学生提交作业给老师发通知
        if (empty($this->param['is_draft'])) {
            try {
                $homeworkModel = SaasHomework::alias('a')
                    ->join('homework_record b', 'b.homework_id=a.id')
                    ->where('b.id', $model['homework_record_id'])
                    ->field('a.*')
                    ->findOrFail();
                event('Notice', [
                    'template' => 'homework.submit',
                    'origin' => [
                        'homework_id' => $homeworkModel['id'],
                        //学生
                        'student' => FrontUser::where('user_account_id', $this->request->user['user_account_id'])
                            ->where('userroleid', FrontUser::STUDENT_TYPE)
                            ->findOrEmpty()
                            ->toArray()
                    ],
                    //老师
                    'front_user_id' => FrontUser::where('user_account_id', $homeworkModel['create_by'])
                        ->where('userroleid', FrontUser::TEACHER_TYPE)
                        ->column('id')
                ]);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return $this->success();
    }

    public function recoverRecord($recordId)
    {
        /** @var  SaasHomeworkRecordLog*/
        $model = SaasHomeworkRecordLog::where('submit_time', '>', 0)
            ->where('remark_time', 0)
            ->findOrFail($recordId);
        $model->saveSubmit(['submit_time' => 0]);

        return $this->success();
    }

    public function remarkRecord($recordId)
    {
        $this->validate($this->param, ValidateHomeworkRecord::class . '.pass');

        /** @var SaasHomeworkRecordLog */
        $model = SaasHomeworkRecordLog::findOrFail($recordId);
        $model->saveRemark($this->param);

        //老师点评作业给学生发通知
        try {
            $homeworkModel = SaasHomework::alias('a')
                ->join('homework_record b', 'b.homework_id=a.id')
                ->where('b.id', $model['homework_record_id'])
                ->field('a.*,b.student_id')
                ->findOrFail();
            event('Notice', [
                'template' => 'homework.remark',
                'origin' => ['homework_id' => $homeworkModel['id']],
                'front_user_id' => [$homeworkModel['student_id']]
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $this->success();
    }

    public function delRemarkRecord($recordId)
    {
        /** @var SaasHomeworkRecordLog */
        $model = SaasHomeworkRecordLog::findOrFail($recordId);
        $model->saveRemark(['del_remark' => 1]);

        return $this->success();
    }

    public function index()
    {
        $list = $this->searchList(HomeworkRecord::class, ['submits', 'list']);

        return $this->success($list);
    }

    public function students()
    {
        $models = HomeworkRecord::withSearch(['students'], $this->param)
            ->select()
            ->withAttr('id', function ($value, $data) {
                return $data['student_id'];
            });
        return $this->success($models);
    }
}
