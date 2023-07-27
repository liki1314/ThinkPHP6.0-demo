<?php
declare (strict_types = 1);

namespace app\wssx\model;

use think\model\Pivot;

class RoomUser extends Pivot
{
    public function searchDefaultAttr($query, $value, $data)
    {
        $query->leftJoin(['saas_room'=>'a'],'a.id=__TABLE__.room_id')
            ->leftJoin(['saas_user_account'=>'b'],'a.create_by=b.id')
            ->field('a.roomname name,a.live_serial as serial,b.username master')
            ->where('__TABLE__.front_user_id',request()->user['user_account_id'])
            ->where('a.create_by','<>',request()->user['user_account_id'])
            ->order('a.create_time','desc');
    }
}
