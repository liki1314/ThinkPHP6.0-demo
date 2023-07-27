<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-29
 * Time: 10:58
 */

namespace app\common\app_terminal\messages\template\course;


use app\admin\model\FrontUser;
use app\admin\model\Room;
use app\common\app_terminal\messages\template\Driver;

abstract class ClassNotice extends Driver
{

    const TEACHER = 1;
    const STUDENTS = 2;
    /**
     * 原始消息数据
     *
     * @var array
     */
    protected $origin;


    /**
     * 创造发送消息需要的数据
     *
     * @param mixed $origin
     * @param array $front_user_id 前台账号id
     * @return $this
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function makeSendData($origin, $front_user_id)
    {
        $this->setOrigin($origin);

        $front_user_id[] = $this->origin['teacher_id'];

        $this->users = FrontUser::field(['id', 'user_account_id', 'nickname as name', 'userroleid'])
            ->whereIn('id', $front_user_id)
            ->select()
            ->toArray();

        return $this;
    }

    /**
     * @param $origin
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function setOrigin($origin)
    {
        $room = Room::where('id', $origin['id'])->field(['roomname', 'starttime', 'teacher_id'])->find();
        $this->origin['title'] = $room['roomname'];
        $this->origin['start_time'] = $room['starttime'];
        $this->origin['app_name'] = $this->name;
        $this->origin['room_id'] = $origin['id'];
        $this->origin['teacher_id'] = $room['teacher_id'];
    }
}
