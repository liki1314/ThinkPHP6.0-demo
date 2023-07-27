<?php

namespace app\common\app_terminal\messages\template\course;


/**
 * 结课提醒
 * Class ClassEnd
 * @package app\common\app_terminal\messages\template\course
 */
class ClassEnd extends ClassNotice
{

    protected $name = 'course.class_end';


    public function getDataByUser($user)
    {
        return [
            'title' => '结课提醒',
            'remark' => sprintf('同学您好，你所学课程即将期满（{剩余课节}）', $user['nickname'], $this->origin['title']),
            'extras' => [
                'type' => $this->origin['app_name'],
                'room_id' => $this->origin['room_id']
            ]
        ];
    }

}
