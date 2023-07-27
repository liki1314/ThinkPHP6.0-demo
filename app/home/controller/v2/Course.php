<?php

declare(strict_types=1);

namespace app\home\controller\v2;

use app\home\model\saas\Course as ModelCourse;
use app\home\model\saas\Room;

class Course extends \app\home\controller\Base
{

    public function info($id)
    {
        $info = ModelCourse::withSearch(['detail'])->findOrFail($id);
        foreach ($info->getAttr('rooms') as $v) {
            $v->withAttr('state', function ($value, $data) {
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
        }
        return $this->success($info);
    }
}
