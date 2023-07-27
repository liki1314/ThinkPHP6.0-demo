<?php

declare(strict_types=1);

namespace app\home\controller\v2;
use app\home\model\saas\Room;

class Lesson extends \app\home\controller\Base
{

    public function index()
    {
        $search = array_intersect(['day'], array_keys($this->param));
        array_push($search, 'user');
        $data = Room::withSearch($search, $this->param)->select()->withAttr('state',function ($value,$data){
            if ($data['endtime'] <= time() && $data['actual_start_time'] == 0) {
                return Room::ROOM_STATUS_EXPIRE;
            } elseif ($data['actual_start_time'] > 0 && $data['actual_end_time'] == 0) {
                return Room::ROOM_STATUS_ING;
            } elseif ($data['actual_start_time'] && $data['actual_end_time'] > 0) {
                return Room::ROOM_STATUS_FINISH;
            } else {
                return Room::ROOM_STATUS_UNSTART;
            }
        });
        return $this->success($data);
    }

}
