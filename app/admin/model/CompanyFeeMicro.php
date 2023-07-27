<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-07-07
 * Time: 17:40
 */

namespace app\admin\model;


/**
 * 教室费用
 * Class CompanyFeeRoom
 * @package app\admin\model
 */
class CompanyFeeMicro extends Base
{
    protected $deleteTime = false;

    public function searchMonthAttr($query, $value)
    {
        $query->whereBetween('month', [$value . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($value . '-01'))])->order('month','asc');
    }


    /**
     * @param $value
     * @return string
     */
    public function getDurationAttr($value)
    {
        return number_format($value, 2);
    }

    /**
     * @param $value
     * @return string
     */
    public function getFeeAttr($value)
    {
        return number_format($value, 2);
    }

    /**
     * @param $value
     * @return string
     */
    public function getPriceAttr($value)
    {
        return number_format($value, 2);
    }

}
