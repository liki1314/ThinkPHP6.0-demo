<?php

namespace app\common\app_terminal\messages\template\homework;

/**
 * 作业布置通知
 */
class Assign extends Homework
{
    protected $name = 'homework.assign';

    /**
     * 设置参数
     * @param $user
     * @return array
     */
    protected function getDataByUser($user)
    {
        return [
            'title' => '老师布置作业通知',
            'alias' => strval($user['user_account_id']),
            'remark' => sprintf('%s同学，您有新的作业了%s，请查收。', $user['nickname'], $this->origin['title']),
            'extras' => [
                'type' => $this->origin['app_name'],
                'students_id' => $user['id'],
                'homework_id' => $this->origin['homework_id']
            ]
        ];
    }

    public function getType()
    {
        return self::ASSIGN_TYPE;
    }
}
