<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-26
 * Time: 17:58
 */

namespace app\admin\model;


use think\db\exception\DataNotFoundException;

/**
 * 转码存储费用
 * Class CompanyFee
 * @package app\admin\model
 */
class CompanyFeeRecord extends Base
{
    protected $deleteTime = false;

    /**
     * 企业消费占比
     * @param $companyId
     * @param bool $month
     * @return array
     */
    public static function consumptionThan($companyId, $month = false)
    {

        $month = $month === false ? date('Y-m') : $month;

        $ret = [
            'main_company_consume' => [
                'roomfee' => [
                    'fee' => 0,
                    'percent' => '25.00'
                ],
                'mp4fee' => [
                    'fee' => 0,
                    'percent' => '25.00'
                ],
                'storagefee' => [
                    'fee' => 0,
                    'percent' => '25.00'
                ],
                'micro' => [
                    'fee' => 0,
                    'percent' => '25.00'
                ]
            ],
            'child_company_consume' => [],
            'child_company_number' => 0
        ];
        //获取子企业
        $dictCompanies = Company::where(['parentid' => $companyId])->column('companyfullname', 'id');
        foreach ($dictCompanies as $k => $v) {
            $ret['child_company_consume'][$k] = [
                'fee' => 0,
                'companyname' => $v,
                'company_id' => $k
            ];
            $ret['child_company_number']++;
        }
        $companyIds = array_keys($dictCompanies);
        $companyIds[] = $companyId;
        $total = 0;

        //教室费用
        $roomFee = CompanyFeeRoom::withoutGlobalScope()
            ->field(['company_id', "sum(fee) as room_fee"])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->group('company_id')
            ->select();

        foreach ($roomFee as $value) {

            if ($value->company_id == $companyId) {
                $ret['main_company_consume']['roomfee']['fee'] = number_format($value->room_fee, 2, '.', '');
                $total += $ret['main_company_consume']['roomfee']['fee'];
            } else {
                $ret['child_company_consume'][$value->company_id]['fee'] = number_format($value->room_fee, 2, '.', '');
            }
        }
        //转码费用
        $recordFee = self::withoutGlobalScope()
            ->field(['company_id', 'sum(fee) as fee'])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->group('company_id')
            ->select();

        foreach ($recordFee as $value) {
            if ($value->company_id == $companyId) {
                $ret['main_company_consume']['mp4fee']['fee'] = number_format($value->fee, 2, '.', '');
                $total += $ret['main_company_consume']['mp4fee']['fee'];
            } else {
                $ret['child_company_consume'][$value->company_id]['fee'] = number_format($ret['child_company_consume'][$value->company_id]['fee'] + $value->fee, 2, '.', '');
            }
        }


        //转码，存储费用
        $fee = CompanyFeeStorage::withoutGlobalScope()
            ->field(['company_id', 'sum(fee) as fee'])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->group('company_id')
            ->select();

        foreach ($fee as $value) {
            if ($value->company_id == $companyId) {
                $ret['main_company_consume']['storagefee']['fee'] = number_format($value->fee, 2, '.', '');
                $total += $ret['main_company_consume']['storagefee']['fee'];
            } else {
                $ret['child_company_consume'][$value->company_id]['fee'] = number_format($ret['child_company_consume'][$value->company_id]['fee'] + $value->fee, 2, '.', '');
            }
        }


        $micro = CompanyFeeMicro::withoutGlobalScope()
            ->field(['company_id', 'sum(fee) as fee'])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->group('company_id')
            ->select();

        foreach ($micro as $value) {
            if ($value->company_id == $companyId) {
                $ret['main_company_consume']['micro']['fee'] = number_format($value->fee, 2, '.', '');
                $total += $ret['main_company_consume']['micro']['fee'];
            } else {
                $ret['child_company_consume'][$value->company_id]['fee'] = number_format($ret['child_company_consume'][$value->company_id]['fee'] + $value->fee, 2, '.', '');
            }
        }

        if ($total > 0) {
            $ret['main_company_consume']['roomfee']['percent'] = number_format($ret['main_company_consume']['roomfee']['fee'] / $total * 100, 2, '.', '');
            $ret['main_company_consume']['mp4fee']['percent'] = number_format($ret['main_company_consume']['mp4fee']['fee'] / $total * 100, 2, '.', '');
            $ret['main_company_consume']['storagefee']['percent'] = number_format($ret['main_company_consume']['storagefee']['fee'] / $total * 100, 2, '.', '');
            $ret['main_company_consume']['micro']['percent'] = number_format($ret['main_company_consume']['micro']['fee'] / $total * 100, 2, '.', '');
        }

        $ret['child_company_consume'] = array_values($ret['child_company_consume']);

        return $ret;
    }

    /**
     * 某一年消费情况
     * @param $companyId
     * @param $year
     * @return array
     */
    public static function getCompanyConsumeByYear($companyId, $year)
    {
        $ret = [];

        $endtime = strtotime($year . '-12-02');
        $starttime = strtotime($year . '-01-01');
        $months = [];
        while ($starttime < $endtime) {
            $month = date('Y-m', $starttime);
            $months[] = $month;
            $ret[$month] = [
                'month' => substr($month, -2),
                'child_company_consume' => "0",
                'main_company_consume' => "0"
            ];
            $starttime = strtotime('+1 month', $starttime);
        }


        $companyIds = Company::where(['parentid' => $companyId])->column('id');
        $companyIds[] = $companyId;
        //教室费用
        $roomFee = CompanyFeeRoom::withoutGlobalScope()
            ->field(['company_id', "DATE_FORMAT(`month`,'%Y-%m') as months", "sum(fee) as fee"])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$year . '-01-01 00:00:00', date('Y-m-t 23:59:59', strtotime($year . '-12-01'))])
            ->group(['company_id', 'months'])
            ->select();

        foreach ($roomFee as $value) {

            if ($value->company_id == $companyId) {
                $ret[$value->months]['main_company_consume'] = number_format($value->fee, 2, '.', '');
            } else {
                $ret[$value->months]['child_company_consume'] = number_format($ret[$value->months]['child_company_consume'] + $value->fee, 2, '.', '');
            }
        }
        //存储费用

        $storageFee = CompanyFeeStorage::withoutGlobalScope()
            ->field(['company_id', "DATE_FORMAT(`month`,'%Y-%m') as months", "sum(fee) as fee"])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$year . '-01-01 00:00:00', date('Y-m-t 23:59:59', strtotime($year . '-12-01'))])
            ->group(['company_id', 'months'])
            ->select();

        foreach ($storageFee as $value) {
            if ($value->company_id == $companyId) {
                $ret[$value->months]['main_company_consume'] = number_format($ret[$value->months]['main_company_consume'] + $value->fee, 2, '.', '');
            } else {
                $ret[$value->months]['child_company_consume'] = number_format($ret[$value->months]['child_company_consume'] + $value->fee, 2, '.', '');
            }
        }

        //转码
        $fee = self::withoutGlobalScope()
            ->field(['company_id', "DATE_FORMAT(`month`,'%Y-%m') as months", "sum(fee) as fee"])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$year . '-01-01 00:00:00', date('Y-m-t 23:59:59', strtotime($year . '-12-01'))])
            ->group(['company_id', 'months'])
            ->select();

        foreach ($fee as $value) {
            if ($value->company_id == $companyId) {
                $ret[$value->months]['main_company_consume'] = number_format($ret[$value->months]['main_company_consume'] + $value->fee, 2, '.', '');
            } else {
                $ret[$value->months]['child_company_consume'] = number_format($ret[$value->months]['child_company_consume'] + $value->fee, 2, '.', '');
            }
        }

        //微录课
        $fee = CompanyFeeMicro::withoutGlobalScope()
            ->field(['company_id', "DATE_FORMAT(`month`,'%Y-%m') as months", "sum(fee) as fee"])
            ->whereIn('company_id', $companyIds)
            ->whereBetween('month', [$year . '-01-01 00:00:00', date('Y-m-t 23:59:59', strtotime($year . '-12-01'))])
            ->group(['company_id', 'months'])
            ->select();

        foreach ($fee as $value) {
            if ($value->company_id == $companyId) {
                $ret[$value->months]['main_company_consume'] = number_format($ret[$value->months]['main_company_consume'] + $value->fee, 2, '.', '');
            } else {
                $ret[$value->months]['child_company_consume'] = number_format($ret[$value->months]['child_company_consume'] + $value->fee, 2, '.', '');
            }
        }


        return array_values($ret);
    }


    /**
     * 某月消费情况
     * @param $companyId
     * @param string $month
     * @return array
     * @throws DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getMonthBill($companyId, $month)
    {
        //教室费用
        $roomFee = CompanyFeeRoom::withoutGlobalScope()
            ->where('company_id', $companyId)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->field(['type', "sum(duration) as duration", "sum(fee) as fee"])
            ->group('type')
            ->select();

        $ret = [];
        $totalFee = 0;

        foreach ($roomFee as $v) {
            $ret[] = [
                'usage' => number_format($v['duration'], 2, '.', ''),
                'fee' => number_format($v['fee'], 2, '.', ''),
                'roomtype' => $v['type'],
                'percent' => "0.00",
                'data_type' => 'roomfee'
            ];
            $totalFee += number_format($v['fee'], 2, '.', '');
        }


        //转码
        $fee = self::withoutGlobalScope()
            ->field(['sum(fee) as fee', 'sum(duration) as duration'])
            ->where('company_id', $companyId)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->find();

        if (!empty($fee)) {
            $totalFee += number_format($fee['fee'], 2, '.', '');
            $ret[] = [
                'usage' => number_format($fee['duration'] / 60, 2, '.', ''),
                'fee' => number_format($fee['fee'], 2, '.', ''),
                'percent' => "0.00",
                'data_type' => 'recordfee'
            ];
        }

        //存储费用

        $fee = CompanyFeeStorage::withoutGlobalScope()
            ->field(['sum(fee) as fee', 'sum(size) as size'])
            ->where('company_id', $companyId)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->find();

        if (!empty($fee)) {
            $totalFee += number_format($fee['fee'], 2, '.', '');
            $ret[] = [
                'usage' => number_format($fee['size'] / 1024, 6, '.', ''),
                'fee' => number_format($fee['fee'], 2, '.', ''),
                'percent' => "0.00",
                'data_type' => 'storagefee'
            ];
        }


        //微录课

        $fee = CompanyFeeMicro::withoutGlobalScope()
            ->field(['sum(fee) as fee', 'sum(duration) as duration'])
            ->where('company_id', $companyId)
            ->whereBetween('month', [$month . '-01 00:00:00', date('Y-m-t 23:59:59', strtotime($month . '-01'))])
            ->find();

        if (!empty($fee)) {
            $totalFee += number_format($fee->getData('fee'), 2, '.', '');
            $ret[] = [
                'usage' => number_format($fee->getData('duration') / 60, 2, '.', ''),
                'fee' => number_format($fee->getData('fee'), 2, '.', ''),
                'percent' => "0.00",
                'data_type' => 'micro'
            ];
        }

        if (!empty($ret)) {
            //计算百分比
            foreach ($ret as $k => $v) {
                $ret[$k]['percent'] = $totalFee > 0 ? number_format($v['fee'] / $totalFee * 100, 2, '.', '') : 0;
            }
        }


        return $ret;

    }

}
