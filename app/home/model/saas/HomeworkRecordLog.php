<?php

declare(strict_types=1);

namespace app\home\model\saas;

use think\exception\ValidateException;
use think\helper\Arr;

class HomeworkRecordLog extends Base
{
    protected $json = ['submit_files', 'remark_files', 'resources'];

    protected $deleteTime = false;

    protected $map = [
        'content' => 'remark_content',
        'resources' => 'remark_files',
    ];

    public function teacher()
    {
        return $this->belongsTo(UserAccount::class, 'teacher_id')->joinType('left');
    }

    public function setIsDraftAttr($value)
    {
        $this->set('submit_time', $value == 0 ? time() : 0);
        return $value;
    }

    public function setSubmitFilesAttr($value)
    {
        return array_map(function ($array) {
            return Arr::only($array, ['id', 'source', 'duration']);
        }, $value);
    }


    // 点评内容
    public function getRemarkAttr($value, $data)
    {
        return $data['remark_time'] == 0 ? null : [
            'content' => $data['remark_content'],
            'resources' => $data['remark_files'],
            'rank' => $data['rank'],
            'is_pass' => $data['is_pass'],
            'remark_time' => $data['remark_time'],
            'teacher' => $this->getAttr('teacher') ? $this->getAttr('teacher')->append(['name'])->visible(['name', 'avatar']) : null,
        ];
    }

    public function setRemarkFilesAttr($value)
    {
        return $value ? Homework::execResources($value) : [];
    }

    public function setIsPassAttr($value, $data)
    {
        $this->set('teacher_id', request()->user['user_account_id']);
        $this->set('remark_time', time());

        return $value;
    }

    public function setDelRemarkAttr()
    {
        $this->set('remark_content', '');
        $this->set('rank', 0);
        $this->set('remark_files', []);
        $this->set('remark_time', 0);
        $this->set('teacher_id', 0);
        $this->set('is_pass', 1);
    }


    public function saveRemark(array $data)
    {
        $this->transaction(function () use ($data) {
            $this->save(Arr::only($data, ['useful_expressions', 'rank', 'is_pass', 'resources', 'content', 'del_remark']));

            if (!empty($data['useful_expressions']) && !empty($data['content'])) {
                UsefulExpression::create(['expression' => $data['content'], 'type' => UsefulExpression::ACCOUNT]);
            }
            // 更新冗余点评记录
            $model = HomeworkRecord::findOrFail($this->getAttr('homework_record_id'));
            if (!in_array($model->getAttr('status'), [HomeworkRecord::SUBMIT_STATUS, HomeworkRecord::UNPASS_STATUS, HomeworkRecord::REMARK_STATUS])) {
                throw new ValidateException(lang('当前状态无法点评作业'));
            }
            $model->save(
                Arr::only(
                    $this->getData(),
                    ['rank', 'remark_files', 'remark_content', 'remark_time', 'teacher_id', 'is_pass']
                )
            );
        });
    }

    public function saveSubmit(array $data)
    {
        $this->transaction(function () use ($data) {
            $this->save(Arr::only($data, ['submit_content', 'submit_files', 'is_draft', 'submit_time']));
            // 更新冗余提交记录
            $model = HomeworkRecord::findOrFail($this->getAttr('homework_record_id'));
            if (!in_array($model->getAttr('status'), [HomeworkRecord::SUBMIT_STATUS, HomeworkRecord::DRAFT_STATUS, HomeworkRecord::UNPASS_STATUS, HomeworkRecord::UNSUBMIT_STATUS])) {
                throw new ValidateException(lang('当前状态无法提交作业'));
            }
            $model->save(
                Arr::only(
                    $this->getData(),
                    [
                        'submit_files', 'submit_content', 'submit_time',
                        'teacher_id', 'remark_time', 'remark_content', 'remark_files', 'rank', 'is_pass'
                    ]
                )
            );
        });
    }
}
