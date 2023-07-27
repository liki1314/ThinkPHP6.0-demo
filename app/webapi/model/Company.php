<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-02
 * Time: 09:34
 */

namespace app\webapi\model;

use think\exception\ValidateException;
use think\facade\Cache;

class Company extends Base
{
    const COMPANY_KEY_HASH = 'company:authkey:collection';

    protected $json = ['notice_config'];

    /**
     * @param $authKy
     * @return mixed
     */
    public static function getAuthKyToCompanyID($authKy)
    {
        $handler = Cache::store('redis')->handler();
        $companyId = $handler->HGET(self::COMPANY_KEY_HASH, $authKy);
        if (empty($companyId)) {
            $companyId = Company::where('authkey', $authKy)->value('id');
            if (empty($companyId)) throw new ValidateException('公司ID不存在');
            $handler->HSET(self::COMPANY_KEY_HASH, $authKy, $companyId);
        }
        return $companyId;
    }

    /**
     * @param string $company_id
     * @param string $d
     * @return mixed
     */
    public static function processTheData($company_id = 'company_id', $d = 'month')
    {
        $auth_key = request()->post('auth_key');

        if (empty($auth_key)) throw new ValidateException('auth_key 不存在');

        $data = request()->post('data');
        $companyId = self::getAuthKyToCompanyID($auth_key);
        if (empty($companyId)) return [];
        $date = [];
        foreach ($data as $k => $v) {
            $data[$k][$company_id] = $companyId;
            $date[] = $v[$d];
        }
        return [
            'date' => $date,
            'data' => $data,
            'company_id' => $companyId,
        ];
    }

    public static function onAfterUpdate($model)
    {
        Cache::delete('company:authkey:' . $model['authkey']);
    }
}
