<?php

namespace app\common\app_terminal\messages\template\course;

use app\admin\model\FrontUser;

/**
 * 上课提醒
 * Class GoToClass
 * @package app\common\app_terminal\messages\template\course
 */
class GoToClass extends ClassNotice
{
    protected $name = 'course.go_to_class';


    public function getDataByUser($user)
    {
        $time = time();

        if ($user['userroleid'] == FrontUser::TEACHER_TYPE) {
            if ($this->origin['start_time'] - $time > 0) {
                $remark = sprintf('老师您好，%s直播课还有%s就开始啦！点击进入', $this->origin['title'], timetostr($this->origin['start_time'] - time()));
            } else {
                $remark = sprintf('老师您好，%s直播课已经开始啦！点击进入', $this->origin['title']);
            }
        } else {
            if ($this->origin['start_time'] - $time > 0) {
                $remark = sprintf('Hi~您报名的%s还有%s就开始啦！点击进入', $this->origin['title'], timetostr($this->origin['start_time'] - time()));
            } else {
                $remark = sprintf('Hi~您报名的%s已经开始啦！点击进入', $this->origin['title']);
            }
        }


        return [
            'title' => '上课提醒',
            'alias' => strval($user['user_account_id']),
            'remark' => $remark,
            'extras' => [
                'type' => $this->origin['app_name'],
                'room_id' => $this->origin['room_id']
            ]
        ];
    }

    public function getType()
    {
        return self::GO_TO_CLASS_TYPE;
    }
}
