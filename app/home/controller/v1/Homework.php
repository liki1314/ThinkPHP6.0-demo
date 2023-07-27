<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use think\helper\Arr;
use app\home\model\saas\HomeworkRecord;
use think\exception\ValidateException;
use app\home\model\saas\Homework as homeWorkModel;
use app\home\model\saas\HomeworkRecordLog;

class Homework extends \app\home\controller\Base
{

    public function index()
    {
        return $this->success($this->searchList(HomeworkRecord::class));
    }

    public function save($homework_id, $student_id)
    {
        $rule = [
            'submit_files' => [
                'array',
                function ($value) {
                    if (!$value) return true;
                    foreach ($value as $item) {
                        if (!isset($item['name']) || !isset($item['path'])) {
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

        $this->validate($this->param, $rule, $message);

        $model = HomeworkRecord::where('homework_id', $homework_id)
            ->where('student_id', $student_id)
            // ->where('submit_time', 0)
            ->findOrEmpty();

        if (!(in_array($model['status'], [HomeworkRecord::DRAFT_STATUS, HomeworkRecord::UNPASS_STATUS, HomeworkRecord::UNSUBMIT_STATUS]) ||
            $model['is_pass'] == 0 && $model['status'] == HomeworkRecord::REMARK_STATUS
        )) {
            throw new ValidateException(lang('当前状态无法提交作业'));
        }

        if ($model->isEmpty()) {
            throw new ValidateException(lang("data_not_exists"));
        }

        $model->transaction(function () use ($model) {
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
        });


        return $this->success();
    }

    public function read()
    {
        $model = HomeworkRecord::withSearch(['detail'], $this->param)->findOrFail();

        if (!$model['read_time']) {
            HomeworkRecord::where('homework_id', $model['homework_id'])
                ->where('student_id', $model['student_id'])
                ->update(['read_time' => time()]);

            homeWorkModel::where('id', $model['homework_id'])->inc('reads')->update();
        }

        //接口获取资源详情
        $data = (new HomeworkRecord)->getV1File($model->toArray());
        return $this->success($data);
    }

    public function revoke($homework_id, $student_id)
    {
        $model = HomeworkRecord::where('homework_id', $homework_id)
            ->where('student_id', $student_id)
            ->where('submit_time', '>', 0)
            ->where('remark_time', 0)
            ->findOrFail();

        $model->transaction(function () use ($model) {
            $model->save(['submit_time' => 0]);
            //老接口兼容
            HomeworkRecordLog::where('homework_record_id', $model->getKey())->order('id', 'desc')->limit(1)->update(['submit_time' => 0]);
        });

        return $this->success();
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
