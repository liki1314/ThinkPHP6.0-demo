<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-27
 * Time: 16:46
 */

namespace app\admin\model;


class CostDetailRoom extends Base
{
    protected $deleteTime = false;

    public function searchMonthAttr($query, $value)
    {
        $query->whereBetween('usertime', [$value . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($value . '-01'))]);
    }

    public function searchRoomtypeAttr($query, $value)
    {
        $query->where('roomtype', $value);
    }

    public function getDurationAttr($value)
    {
        return number_format($value, 2);
    }

    public function getFeeAttr($value)
    {
        return number_format($value, 2);
    }
}
