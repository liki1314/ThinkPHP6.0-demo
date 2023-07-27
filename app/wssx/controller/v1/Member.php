<?php

declare(strict_types=1);

namespace app\wssx\controller\v1;

use app\wssx\controller\Base;
use app\common\facade\Pay;
use app\wssx\model\MemberOrder;
use app\wssx\model\MemberCard;

class Member extends Base
{
    /**
     * 会员卡列表
     */
    public function card()
    {
        $data = MemberCard::withSearch(['card'], $this->param)->select();
        return $this->success($data);
    }

    public function payOrder()
    {
        $this->validate(
            $this->param,
            [
                'order_number' => 'require',
                'way' => [
                    'array',
                    'each' => ['value' => 'in:wechat,alipay', 'method' => 'in:web,wap,app,pos,scan,transfer,mini,mp']
                ]
            ]
        );

        /** @var MemberOrder $order */
        $order = MemberOrder::where('order_number', $this->param['order_number'])
            // ->where('create_by', $this->request->user['user_account_id'])
            ->where('status', 1)
            ->findOrFail();

        $method = $this->param['way']['method'];
        $result = Pay::channel($this->param['way']['value'])->pay($order->getOrderData($this->param['way']['value'], $method), $method);

        return $this->success(['signature' => $result, 'period' => (int)(explode('|', $order['desc'])[1] ?? 0)]);
    }

    /**
     * 用户订单列表
     */
    public function orderList()
    {
        return $this->success($this->searchList(MemberOrder::class));
    }

    /**
     * 创建支付单
     */
    public function createOrder()
    {
        $rule = [
            'card_id' => ['require', 'integer'],
            'source' => ['in:ios,android']
        ];

        $message = [
            'card_id.require' => 'card_id_empty',
        ];

        $this->validate($this->param, $rule, $message);

        $model = (new MemberOrder)->createOrder($this->param);

        $data = $model->visible(['create_time', 'money', 'order_number']);

        return $this->success($data);
    }
}
