<?php

declare(strict_types=1);

namespace app\wssx\model;

use app\common\model\Company;
use app\Request;
use think\exception\ValidateException;
use app\common\service\Math;

/**
 * @mixin \think\Model
 */
class MemberOrder extends Base
{
    /** 待支付 */
    const UNPAID_STATUS = 1;
    /** 已支付 */
    const PAID_STATUS = 2;
    /** 已取消 */
    const CANCEL_STATUS = 3;

    protected $json = ['extra_info'];

    public function getOrderData($way = 'wechat', $method = 'app')
    {
        $product = explode('-', explode('|', $this->getAttr('desc'))[0])[0];
        if ($way == 'wechat') {
            return [
                'out_trade_no' => config('app.order_prefix') . $this->getAttr('order_number'),
                'body' => $product,
                'total_fee' => env('app_debug') ? 1 : intval(bcmul($this->getAttr('money'), '100')),
            ];
        } else {
            return [
                'out_trade_no' => config('app.order_prefix') . $this->getAttr('order_number'),
                'total_amount' => env('app_debug') ? 0.01 : $this->getAttr('money'),
                'subject'      => $product,
            ];
        }
    }

    protected $globalScope = ['accountId'];

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('order_number,status,money,`desc`,create_time,apple_price')
            ->order('__TABLE__.create_time', 'desc')
            ->append(['card'])
            ->hidden(['desc']);

        if (isset($data['type']) && $data['type']) {
            $query->where('__TABLE__.status', $data['type']);
        }
    }

    public function scopeAccountId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (isset($request->user['user_account_id']) && in_array('create_by', $query->getTableFields())) {
                $query->where('__TABLE__.create_by', $request->user['user_account_id']);
            }
        });
    }

    /**
     * 创建支付单
     * @param $params
     */
    public function createOrder($params)
    {
        $cardModel = MemberCard::where('id', $params['card_id'])
            ->where('enable', MemberCard::CARD_OPEN_STATUS)
            ->findOrEmpty();

        if ($cardModel->isEmpty()) {
            throw new ValidateException(lang('this_card_is_disbale'));
        }

        if ($cardModel['price'] <= 0) {
            throw new ValidateException(lang('this_card_price_disbale'));
        }

        $objMath = new Math;

        $save = [];
        $save['order_number'] = $this->getOrderNum();
        $save['money'] = $cardModel['discount'] > 0 ? $objMath->mul([$cardModel['price'], $cardModel['discount']]) : $cardModel['price'];
        $save['desc'] = $cardModel['name'] . '-' . $cardModel['apple_product_id'] . '|' . $cardModel['period'];
        $save['source'] = $params['source'] ?? '';
        $save['apple_price'] = $cardModel['apple_price'];
        $save['extra_info'] = [
            'card_id' => $cardModel->getKey(),
            'card_name' => $cardModel['name'],
            'card_period' => $cardModel['period'],
            'card_discount' => $cardModel['discount'],
            'card_price' => $cardModel['price'],
            'username' => request()->user['username'],
            'account' => request()->user['account'],
            'company_id' => Company::where('createuserid', request()->user['user_account_id'])->where('type', 6)->value('id'),
        ];
        return self::create($save);
    }

    /**
     * 生成唯一订单号码
     * @return string
     */
    public function getOrderNum()
    {
        return date('YmdHis') . mt_rand(1000, 9999);
    }


    public function getCardAttr($value, $data)
    {
        $temp = explode('|', $data['desc']);
        $nameList = explode('-', $temp[0]);
        return $data['status'] == self::PAID_STATUS ? ['name' => $nameList[0], 'expire' => date('Y-m-d H:i', is_numeric($temp[2]) ? (int)$temp[2] : strtotime($temp[2])), 'id' => $nameList[1] ?? ''] : ['name' => $nameList[0], 'expire' => '', 'id' => $nameList[1] ?? ''];
    }
}
