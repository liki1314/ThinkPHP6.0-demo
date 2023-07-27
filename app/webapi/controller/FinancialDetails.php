<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-28
 * Time: 15:03
 */

namespace app\webapi\controller;


use app\webapi\model\CompanyFeeMicro;
use app\webapi\model\CompanyFeeRecord;
use app\webapi\model\CompanyFeeRoom;
use app\webapi\model\CompanyFeeStorage;
use app\webapi\model\CompanyRecharge;
use app\webapi\model\CostDetailRecord;
use app\webapi\model\CostDetailRoom;
use app\webapi\model\CostDetailStorage;
use app\webapi\model\Company;
use think\exception\ValidateException;
use think\facade\Db;
use think\response\Json;

class FinancialDetails extends Base
{
    /**
     * @return Json
     */
    public function record()
    {

        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'month');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CompanyFeeRecord)->where('company_id', $data['company_id'])
                    ->whereIn('month', $data['date'])
                    ->delete();
                (new CompanyFeeRecord)->insertAll($data['data']);
            });

        } else {
            (new CompanyFeeRecord)->insertAll($data['data']);
        }

        return $this->success();
    }

    /**
     * @return Json
     */
    public function room()
    {
        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'month');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CompanyFeeRoom)->where('company_id', $data['company_id'])
                    ->whereIn('month', $data['date'])
                    ->delete();
                (new CompanyFeeRoom)->insertAll($data['data']);
            });

        } else {
            (new CompanyFeeRoom)->insertAll($data['data']);
        }


        return $this->success();
    }

    /**
     * @return Json
     */
    public function storage()
    {
        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'month');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CompanyFeeStorage)->where('company_id', $data['company_id'])
                    ->whereIn('month', $data['date'])
                    ->delete();
                (new CompanyFeeStorage)->insertAll($data['data']);
            });

        } else {
            (new CompanyFeeStorage)->insertAll($data['data']);
        }

        return $this->success();
    }

    /**
     * @return Json
     */
    public function storageDetails()
    {
        $type = request()->post('type');
        $data = Company::processTheData('companyid', 'datemonth');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {
                foreach ($data['data'] as $value) {
                    (new CostDetailStorage)->where('companyid', $data['company_id'])
                        ->where('datemonth', $value['datemonth'])
                        ->where('dateday', $value['dateday'])
                        ->delete();
                }
                (new CostDetailStorage)->insertAll($data['data']);
            });

        } else {
            (new CostDetailStorage)->insertAll($data['data']);
        }

        return $this->success();
    }

    /**
     * @return Json
     */
    public function recordDetails()
    {
        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'createtime');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CostDetailRecord)->where('company_id', $data['company_id'])
                    ->whereIn('createtime', $data['date'])
                    ->delete();
                (new CostDetailRecord)->insertAll($data['data']);
            });

        } else {
            (new CostDetailRecord)->insertAll($data['data']);
        }

        return $this->success();
    }

    /**
     * @return Json
     */
    public function roomDetails()
    {
        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'usertime');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CostDetailRoom)->where('company_id', $data['company_id'])
                    ->whereIn('usertime', $data['date'])
                    ->delete();
                (new CostDetailRoom)->insertAll($data['data']);
            });

        } else {
            (new CostDetailRoom)->insertAll($data['data']);
        }

        return $this->success();
    }


    /**
     * @return Json
     */
    public function recharge()
    {
        $auth_key = request()->post('auth_key');

        $data = request()->post('data');

        $companyId = Company::getAuthKyToCompanyID($auth_key);

        $data['company_id'] = $companyId;

        (new CompanyRecharge)->insert($data);

        return $this->success();
    }

    /**
     * 微录课
     * @return Json
     */
    public function micro()
    {

        $type = request()->post('type');
        $data = Company::processTheData('company_id', 'month');

        if (empty($data['company_id'])) {
            throw new ValidateException("公司没有找到");
        }

        if ($type == 1) {
            Db::transaction(function () use ($data) {

                (new CompanyFeeMicro)->where('company_id', $data['company_id'])
                    ->whereIn('month', $data['date'])
                    ->delete();
                (new CompanyFeeMicro)->insertAll($data['data']);
            });

        } else {
            (new CompanyFeeMicro)->insertAll($data['data']);
        }


        return $this->success();
    }
}
