<?php

declare(strict_types=1);

namespace app\clientapi\controller;

use app\BaseController;
use app\clientapi\middleware\Auth;
use app\common\facade\Excel;
use think\facade\Db;

class Index extends BaseController
{
    protected $middleware = [Auth::class];

    public function getWssxOrder()
    {
        $data = Db::connect('mysql')
            ->table('saas_member_order')
            ->field([
                'order_number',
                'out_order_number',
                "JSON_UNQUOTE(extra_info->'$.username')" => 'username',
                'JSON_UNQUOTE(extra_info->"$.account")' => 'account',
                'JSON_UNQUOTE(extra_info->"$.company_id")' => 'company_id',
                'type',
                'JSON_UNQUOTE(extra_info->"$.card_name")' => 'card_name',
                'DATE_ADD(FROM_UNIXTIME(update_time),INTERVAL JSON_UNQUOTE(extra_info->"$.card_period") DAY)' => 'card_period',
                'FROM_UNIXTIME(update_time)' => 'pay_time',
                'JSON_UNQUOTE(extra_info->"$.card_price")' => 'card_price',
                'JSON_UNQUOTE(extra_info->"$.card_discount")' => 'card_discount',
                'money',
            ])
            ->where('out_order_number', '<>', '')
            ->when(!empty($this->param['start_time']), function ($query) {
                $query->whereTime('update_time', '>=', $this->param['start_time']);
            })
            ->when(!empty($this->param['end_time']), function ($query) {
                $query->whereTime('update_time', '<=', $this->param['end_time']);
            })
            ->select();

        return Excel::export($data->toArray(), ['订单号', '第三方订单号', '用户名', '手机号', '企业ID', '支付方式', '会员卡类型', '会员卡有效期', '支付日期', '会员卡金额', '折扣', '实际支付金额'], '微思账单流水' . date('YmdHis'));
    }
}
