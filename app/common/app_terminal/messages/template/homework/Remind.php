<?php

namespace app\common\app_terminal\messages\template\homework;


/**
 * 作业提醒通知
 */
class Remind extends Homework
{
    protected $name = 'homework.remind';

    public function getDataByUser($user)
    {
        return [
            'title' => '作业提醒',
            'alias' => strval($user['user_account_id']),
            'remark' => sprintf('%s同学有%s作业未完成，要抓紧时间哦。', $user['nickname'], $this->origin['title']),
            'extras' => [
                'type' => $this->origin['app_name'],
                'students_id' => $user['id'],
                'homework_id' => $this->origin['homework_id']
            ]
        ];
    }

    public function getType()
    {
        return self::REMIND_TYPE;
    }
}
