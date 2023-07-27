<?php

declare(strict_types=1);

namespace app\home\controller\v2;

use app\home\model\saas\FrontUser;
use app\home\model\saas\Homework as homeWorkModel;
use app\home\model\saas\HomeworkRecord;
use app\home\model\saas\HomeworkRecordLog;
use think\helper\Arr;
use app\home\model\saas\teacher\HomeworkRecord as TeacherHome;
use think\exception\ValidateException;

class Homework extends \app\home\controller\Base
{
    public function index()
    {
        return $this->success($this->searchList(HomeworkRecord::class));
    }

    public function read()
    {
        $model = HomeworkRecord::withSearch(['info'], $this->param)->findOrFail();

        if (!$model['read_time']) {
            HomeworkRecord::where('homework_id', $model['homework_id'])
                ->where('student_id', $model['student_id'])
                ->update(['read_time' => time()]);

            homeWorkModel::where('id', $model['homework_id'])->inc('reads')->update();
        }
        //接口获取资源详情
        $data = (new HomeworkRecord)->getFile($model->toArray());

        return $this->success($data);
    }

    public function save($homework_id, $student_id)
    {
        $rule = [
            'submit_files' => [
                'array',
                function ($value) {
                    if (!$value) return true;
                    foreach ($value as $item) {
                        if (!isset($item['id']) || !isset($item['source']) || !in_array($item['source'], [1, 2])) {
                            return lang('homework_resources');
                        }
                    }
                    return true;
                }
            ],
            'is_draft' => [
                'require',
                'in' => '0,1',
            ],
            'submit_content' => 'requireWithout:submit_files',
        ];

        $message = [
            'submit_files' => lang('homework_resources'),
            'submit_content' => lang('homework_submit_content'),
            'is_draft' => lang('homework_is_draft'),
        ];

        if (!isset($this->param['submit_files']) || empty($this->param['submit_files'])) {
            $this->param['submit_files'] = [];
        }

        $this->validate($this->param, $rule, $message);

        /** @var TeacherHome */
        $model = TeacherHome::where('homework_id', $homework_id)
            ->where('student_id', $student_id)
            // ->where('submit_time', 0)
            ->findOrFail();

        if (!(in_array($model['status'], [HomeworkRecord::DRAFT_STATUS, HomeworkRecord::UNPASS_STATUS, HomeworkRecord::UNSUBMIT_STATUS]) ||
            $model['is_pass'] == 0 && $model['status'] == HomeworkRecord::REMARK_STATUS
        )) {
            throw new ValidateException(lang('当前状态无法提交作业'));
        }

        $model->transaction(function () use ($model, $homework_id) {
            //清除上次点评信息
            $remarkData = [
                'teacher_id' => 0,
                'remark_time' => 0,
                'remark_content' => null,
                'remark_files' => null,
                'rank' => 0,
                'is_pass' => 1,
            ];

            $status = $model['status'];
            $model->save(Arr::only($this->param, ['submit_content', 'submit_files', 'is_draft']) + $remarkData);
            //老接口兼容===如果当前是草稿状态，则表示是修改提交记录，否则表示新增提交记录
            if ($status == HomeworkRecord::DRAFT_STATUS) {
                $homeworkRecordLog = HomeworkRecordLog::where('homework_record_id', $model->getKey())->order('id', 'desc')->findOrEmpty();
            } else {
                $homeworkRecordLog = new HomeworkRecordLog(['homework_record_id' => $model->getKey()]);
            }
            $homeworkRecordLog->save(Arr::only($this->param, ['submit_content', 'submit_files', 'is_draft']));

            if (empty($this->param['is_draft'])) {
                $homeworkModel = homeWorkModel::findOrFail($homework_id);
                event('Notice', [
                    'template' => 'homework.submit',
                    'origin' => [
                        'homework_id' => $homework_id,
                        'student' => FrontUser::where('user_account_id', $this->request->user['user_account_id'])->where('userroleid', FrontUser::STUDENT_TYPE)->findOrEmpty()->toArray() //学生
                    ],
                    'front_user_id' => FrontUser::where('user_account_id', $homeworkModel['create_by'])->where('userroleid', FrontUser::TEACHER_TYPE)->column('id') //老师
                ]);
            }
        });

        return $this->success();
    }
}
