<?php

declare(strict_types=1);

namespace app\wssx\model;

class UserRecord extends Base
{
    protected $deleteTime = false;

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->join(['saas_room_record' => 'a'], 'a.id=__TABLE__.room_record_id')
            ->field('playpath url,recordtitle,serial,starttime,endtime')
            ->where('__TABLE__.user_account_id', request()->user['user_account_id'])
            ->order('starttime', 'desc')
            ->append(['title'])
            ->hidden(['starttime', 'endtime']);

        if (isset($data['serial'])) {
            $query->where('serial', $data['serial']);
        }
    }

    public function getTitleAttr($value, $data)
    {
        $date = date('H:i', (int)$data['starttime']) . '~' . date('H:i', (int)$data['endtime']);
        return date('Y-m-d', (int)$data['starttime']) . ' ' . $date;
    }
}