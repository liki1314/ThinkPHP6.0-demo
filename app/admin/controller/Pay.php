<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\common\facade\Pay as FacadePay;
use app\common\http\CommonAPI;
use think\exception\ValidateException;
use think\facade\Cache;
use \Yansongda\Supports\Collection;
use app\admin\model\Company;

class Pay extends Base
{
    protected $middleware = [
        'check' => ['except' => ['callback']],
    ];

    //待支付
    const UNPAID_STATUS = 1;
    //已支付
    const PAID_STATUS = 2;
    //支付失败
    const FAIL_STATUS = 3;
    //支付取消
    const CANCEL_STATUS = 4;

    public function callback($way)
    {
        $pay = FacadePay::channel('global_' . $way);
        /** @var Collection $result */
        $collection = $pay->verify();

        $order = Cache::store('redis')->hGetAll(sprintf('order:%s', $collection->out_trade_no));
        if (empty($order) || $order['status'] != self::UNPAID_STATUS) {
            return $pay->success();
        }

        if ($collection->status == self::PAID_STATUS) {
            //回调global
            CommonAPI::httpPost('CommonAPI/companyRecharge', [
                'key' => $order['key'],
                'paytype' => $order['paytype'] == 'alipay' ? 1 : ($order['paytype'] == 'wechat' ? 2 : 3), //1支付宝2微信3paypal
                'orderno' => $collection->out_trade_no,
                'rechargeamount' => $order['rechargeamount'],
                'rechargetime' => time(),
                'outtradeno' => $collection->out_order_number,
            ]);
        }

        Cache::store('redis')->hSet(sprintf('order:%s', $collection->out_trade_no), 'status', $collection->status);
        return $pay->success();
    }

    public function pay()
    {
        $this->validate($this->param, [
            'money' => 'require|float',
            'gateway' => 'require|in:wechat,alipay,paypal',
            'way' => 'require|in:web,scan,app',
        ]);

        $orderNumber = substr(strtoupper(str_replace('.', '', uniqid('TKG') . date('YmdHis') . mt_rand(1000, 9999))), 0, 32);
        if (Cache::has(sprintf('order:%s', $orderNumber))) {
            throw new ValidateException(lang('订单已存在'));
        }

        $gateway = FacadePay::channel('global_' . $this->param['gateway']);
        $result = $gateway->pay($gateway->makeOrder($orderNumber, $this->param['money'], '网校充值'), $this->param['way']);
        Cache::store('redis')
            ->hMSet(
                sprintf('order:%s', $orderNumber),
                [
                    'status' => self::UNPAID_STATUS,
                    'rechargeamount' => $this->param['money'],
                    'paytype' => $this->param['gateway'],
                    'key' => Company::cache(true, 12 * 3600)->find($this->request->user['company_id'])['authkey'],
                    'company_id' => $this->request->user['company_id']
                ]
            );
        Cache::store('redis')->expire(sprintf('order:%s', $orderNumber), 6 * 3600);

        return $this->success(['order_number' => $orderNumber, 'data' => $result]);
    }

    public function read($orderNumber)
    {
        $order = Cache::store('redis')->hGetAll(sprintf('order:%s', $orderNumber));
        if (empty($order) || $order['company_id'] != $this->request->user['company_id']) {
            throw new ValidateException(lang('订单不存在'));
        }

        return $this->success(['status' => $order['status']]);
    }

    public function cancel($orderNumber)
    {
        $order = Cache::store('redis')->hGetAll(sprintf('order:%s', $orderNumber));
        if (empty($order) || $order['company_id'] != $this->request->user['company_id']) {
            throw new ValidateException(lang('订单不存在'));
        }

        Cache::store('redis')->hSet(sprintf('order:%s', $orderNumber), 'status', self::CANCEL_STATUS);
        return $this->success();
    }
}
