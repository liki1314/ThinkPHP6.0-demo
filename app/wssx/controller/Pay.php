<?php

declare(strict_types=1);

namespace app\wssx\controller;

use app\common\facade\Pay as FacadePay;
use app\common\model\Company;
use app\wssx\model\MemberOrder;
use think\facade\Db;
use \Yansongda\Supports\Collection;
use app\wssx\model\UserAccount;
use app\common\http\CommonAPI;

class Pay
{
    public function notice($way = 'wechat')
    {
        $pay = FacadePay::channel($way);
        /** @var Collection $result */
        $collection = $pay->verify();

        Db::transaction(function () use ($collection, $way) {
            $model = MemberOrder::lock(true)
                ->where('order_number', substr($collection->out_trade_no, strlen(config('app.order_prefix'))))
                ->where('status', MemberOrder::UNPAID_STATUS)
                ->where('out_order_number', '<>', $collection->out_order_number)
                ->findOrFail();
            $data = ['status' => $collection->status, 'type' => $way, 'out_order_number' => $collection->out_order_number];
            if ($collection->status == MemberOrder::PAID_STATUS) {
                $user = UserAccount::cache(true)->findOrFail($model['create_by']);

                [$name, $period] = explode('|', $model['desc'], 2);
                $extend_info = $user['extend_info'];
                $extend_info['member_expire'] = strtotime(
                    "+$period days",
                    isset($extend_info['member_expire']) ? ($extend_info['member_expire'] < time() ? time() : $extend_info['member_expire']) : strtotime('+' . config('app.member_period') . ' days')
                );
                $extend_info['member_name'] = explode('-', $name)[0] ?? null;
                $user->extend_info = $extend_info;
                $user->save();

                $data['desc'] = $model['desc'] . '|' . $extend_info['member_expire'];
                $data['extra_info->member_expire'] = $extend_info['member_expire'];
            }

            $model->save($data);

            //更新企业状态
            $company = Company::where('createuserid', $model['create_by'])
                ->where('type', 6)
                ->findOrEmpty();
            $company->save(['companystate' => 1, 'endtime' => date('Y-m-d H:i:s', $extend_info['member_expire'])]);

        });

        return $pay->success();
    }
}
