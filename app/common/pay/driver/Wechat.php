<?php

namespace app\common\pay\driver;

use app\common\pay\Driver;
use Yansongda\Pay\Pay;
use app\wssx\model\MemberOrder;

/**
 * 微信支付
 */
class Wechat extends Driver
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->handler = Pay::wechat($this->config);
        // $this->handler = Factory::payment($this->config);
    }

    protected function app($order)
    {
        return $this->handler->app($order)->getContent();
    }

    public function verify()
    {
        $collection = $this->handler->verify();
        $collection->status = $collection->result_code == 'SUCCESS' ? MemberOrder::PAID_STATUS : ($collection->result_code == 'FAIL' ? MemberOrder::CANCEL_STATUS : MemberOrder::UNPAID_STATUS);
        $collection->out_order_number = $collection->transaction_id;
        return $collection;
    }

    public function success()
    {
        return response($this->handler->success()->getContent(), 200, [], \app\common\service\Text::class);
    }

    public function makeOrder($orderNumber, $money, $desc): array
    {
        return [
            'out_trade_no' => $orderNumber ?: time(),
            'body' => $desc,
            'total_fee' => $money * 100,
        ];
    }

    public function scan($order)
    {
        return $this->handler->scan($order)->code_url;
    }
}
