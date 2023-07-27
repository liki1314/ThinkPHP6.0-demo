<?php

namespace app\common\pay\driver;

use app\common\pay\Driver;
use Yansongda\Pay\Pay;
use app\wssx\model\MemberOrder;

/**
 * 支付宝
 */
class Alipay extends Driver
{
    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->handler = Pay::alipay($this->config);
    }

    protected function app($order)
    {
        return $this->handler->app($order)->getContent();
    }

    public function web($order)
    {
        return $this->handler->web($order)->getContent();
    }

    public function verify()
    {
        $collection = $this->handler->verify();
        $collection->status = $collection->trade_status == 'TRADE_SUCCESS' ? MemberOrder::PAID_STATUS : ($collection->trade_status == 'TRADE_CLOSED' ? MemberOrder::CANCEL_STATUS : MemberOrder::UNPAID_STATUS);
        $collection->out_order_number = $collection->trade_no;
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
            'total_amount' => $money,
            'subject'      => $desc,
        ];
    }
}
