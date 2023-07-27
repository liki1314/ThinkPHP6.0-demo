<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\CompanyFeeMicro;
use app\common\http\CommonAPI;
use app\common\service\Crypt3Des;
use think\Exception;
use think\Request;
use app\admin\model\CompanyFeeRecord;
use app\admin\model\CompanyRecharge;
use app\admin\model\CostDetailRecord;
use app\admin\model\CostDetailRoom;
use app\admin\model\CostDetailStorage;
use app\admin\model\Company as CompanyModel;

class Finance extends Base
{
    /**
     * 总览
     * @param Request $request
     * @return \think\response\Json
     */
    public function overview(Request $request)
    {

        $month = $this->param['month'] ?? false;

        $company = CompanyModel::field(['balance', 'credit_limit'])->findOrFail($request->user['company_id'])->toArray();

        $res = CompanyFeeRecord::consumptionThan($request->user['company_id'], $month);

        return $this->success(array_merge($res, $company));
    }

    /**
     * 获取指定月资源消费
     * @param $year
     * @param Request $request
     * @return \think\response\Json
     */
    public function getCompanyConsumeByYear($year, Request $request)
    {
        return $this->success(CompanyFeeRecord::getCompanyConsumeByYear($request->user['company_id'], $year));
    }

    /**
     * 充值记录
     * @return \think\response\Json
     */
    public function rechargeRecord()
    {
        $this->validate($this->param, ['starttime' => 'require', 'endtime' => 'require']);
        $ret = $this->searchList(CompanyRecharge::class)->each(function ($value) {
            $value->rechargetime = date('Y-m-d H:i', $value->rechargetime);
        });
        return $this->success($ret);
    }


    /**
     * 获取企业余额
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getCompanyBalance(Request $request)
    {
        $companyId = $request->get('companyid');
        $ret = CompanyModel::getCompanyBalance($companyId, $request->user['company_id']);
        return $this->success($ret);
    }


    /**
     * 月账单
     * @param $month
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function monthbill($month, Request $request)
    {
        return $this->success(CompanyFeeRecord::getMonthBill($request->user['company_id'], $month));
    }

    /**
     * 教室费用明细
     * @return \think\response\Json
     */
    public function roomBillDetail()
    {
        return $this->success($this->searchList(CostDetailRoom::class));
    }


    /**
     * 存储费用明细
     * @param $month
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function storageBillDetail($month)
    {
        return $this->success(CostDetailStorage::getStorageBillDetail($month));
    }

    /**
     * 转码费用明细
     * @return \think\response\Json
     */
    public function recordBillDetail()
    {
        return $this->success($this->searchList(CostDetailRecord::class));
    }

    /**
     * 转码费用明细
     * @return \think\response\Json
     */
    public function microBillDetail()
    {
        return $this->success($this->searchList(CompanyFeeMicro::class));
    }


    /**
     * 计费标准
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function chargeStandard(Request $request)
    {
        $company = CompanyModel::cache(true, CompanyModel::CACHE_TIME)->find($request->user['company_id']);

        return $this->success((new CommonAPI())->getChargeStandard($company['authkey']));
    }


    /**
     * 子企业充值
     * @return \think\response\Json
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function recharge()
    {
        $this->validate($this->param, ['company_id' => 'require', 'recharge_amount' => 'require']);

        $company = CompanyModel::cache(true, CompanyModel::CACHE_TIME)->find($this->param['company_id']);

        if ($company['parentid'] != $this->request->user['company_id']) throw new Exception('异常');

        (new CommonAPI())->recharge($company['authkey'], $this->param['recharge_amount'], $this->request->user['userid']);

        return $this->success();
    }


    /**
     * 主企业充值
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function pay()
    {
        $userId = $this->request->user['userid'];
        $time = time();
        $authKey = CompanyModel::cache(true, CompanyModel::CACHE_TIME)->find($this->request->user['company_id'])['authkey'];
        $param = [
            'key' => $authKey,
            'userid' => strval($userId),
            't' => strval($time)
        ];
        $responseBody = stripslashes(json_encode($param, JSON_UNESCAPED_UNICODE));
        $sign = base64_encode(hash_hmac('sha256', $responseBody, $authKey, true));
        $param['sign'] = $sign;

        $paramStr = (new Crypt3Des)->encrypt(http_build_query($param), config('app.global.secret_key'));

        return $this->success([
            'param' => $paramStr
        ]);
    }
}
