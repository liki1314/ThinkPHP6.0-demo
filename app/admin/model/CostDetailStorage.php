<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-27
 * Time: 17:11
 */

namespace app\admin\model;


class CostDetailStorage extends Base
{

    protected $deleteTime = false;

    /**
     * 获取存储费用明细
     * @param $month
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getStorageBillDetail($month)
    {

        $bills = self::field(['datemonth', 'dateday', 'totalsize', 'storagetotalfee', 'price'])
            ->where('companyid', request()->user['company_id'])
            ->whereIn('datemonth', [date('Y-m', strtotime('-1 month', strtotime($month))), $month])
            ->order('datemonth ASC, dateday ASC')->select();


        $ret = [];
        foreach ($bills as $key => $bill) {
            if ($bill['datemonth'] != $month) {
                continue;
            }
            $tmp = [];
            $tmp['time'] = $month . '-' . $bill['dateday']; //转码时间
            $tmp['time'] = date('Y-m-d', strtotime($tmp['time']));
            $tmp['totalsize'] = number_format($bill['totalsize'] / 1024, 6); //总文件M
            //新增文件
            if (isset($bills[$key - 1])) {
                $tmp['increased_size'] = number_format(($bill['totalsize'] - $bills[$key - 1]['totalsize']) / 1024, 6);
            } else {
                $tmp['increased_size'] = number_format($bill['totalsize'] / 1024, 6);
            }
            $tmp['price'] = number_format($bill['price'], 3); //单价
            $tmp['storagetotalfee'] = number_format($bill['storagetotalfee'], 2); //总价
            $ret[] = $tmp;
        }
        return $ret;
    }

}
