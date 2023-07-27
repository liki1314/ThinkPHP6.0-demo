<?php

namespace app\common\app_terminal\messages\template\homework;


/**
 * 作业点评通知
 */
class Remark extends Homework
{
    protected $name = 'homework.remark';

    public function getDataByUser($user)
    {
        return [
            'title' => '点评完成通知',
            'alias' => strval($user['user_account_id']),
            'remark' => sprintf('%s作业点评已经完成，快去看看吧。', $this->origin['title']),
            'extras' => [
                'type' => $this->origin['app_name'],
                'students_id' => $user['id'],
                'homework_id' => $this->origin['homework_id']
            ]
        ];
    }

    public function getType()
    {
        return self::REMARK_TYPE;
    }
}
