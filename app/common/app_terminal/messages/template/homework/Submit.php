<?php

namespace app\common\app_terminal\messages\template\homework;


/**
 * 作业提交通知
 */
class Submit extends Homework
{
    protected $name = 'homework.submit';

    public function getDataByUser($user)
    {
        return [
            'title' => '作业完成提醒',
            'alias' => strval($user['user_account_id']),
            'remark' => sprintf('老师您好，【%s】的作业 【%s】已完成，请查阅。', $this->origin['student']['nickname'], $this->origin['title']),
            'extras' => [
                'type' => $this->origin['app_name'],
                'students_id' => $this->origin['student']['id'],
                'homework_id' => $this->origin['homework_id']
            ]
        ];
    }

    public function getType()
    {
        return self::SUBMIT_TYPE;
    }
}
