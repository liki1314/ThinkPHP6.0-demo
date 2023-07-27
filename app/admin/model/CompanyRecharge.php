<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-27
 * Time: 14:13
 */

namespace app\admin\model;


class CompanyRecharge extends Base
{

    protected $deleteTime = false;

    // 返现类型，1：赔偿，2：赠送
    const RETURN_CASH_TYPE_DEFAULT = 0;
    const RETURN_CASH_TYPE_COMPENSATE = 1;
    const RETURN_CASH_TYPE_GIVE = 2;


    public function searchDefaultAttr($query, $value, $data)
    {

        $query->field([
            'recharge_number as rechargenumber',
            'recharge_time as rechargetime',
            'recharge_amount as rechargeamount',
            'recharge_type as rechargetype',
            'trade_type',
            'trade_status'])
         //   ->where('returncash_type', self::RETURN_CASH_TYPE_COMPENSATE)
            ->order('id DESC');
    }

    public function searchStarttimeAttr($query, $value)
    {
        $query->where('recharge_time', '>=', $value);
    }

    public function searchEndtimeAttr($query, $value)
    {
        $query->where('recharge_time', '<=', $value);
    }


    public function getRechargeamountAttr($value)
    {

        return number_format($value, 2);
    }

}
