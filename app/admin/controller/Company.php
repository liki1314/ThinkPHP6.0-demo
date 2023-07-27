<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\AuthGroup;
use app\admin\model\Company as CompanyModel;
use app\admin\model\CompanyUser;
use app\admin\validate\Company as ValidateCompany;
use app\admin\validate\CompanyUpdate as ValidateUpdateCompany;
use app\common\facade\Live;
use app\common\http\CommonAPI;
use app\common\http\CompanyHttp;
use app\gateway\model\UserAccount;
use think\exception\ValidateException;
use think\facade\Db;
use thans\jwt\facade\JWTAuth;
use app\common\lib\sms\Rule;
use think\helper\Arr;

class Company extends Base
{
    use Rule;

    /**
     * 企业列表
     * @return \think\response\Json
     */
    public function index()
    {
        return $this->success($this->searchList(CompanyModel::class));
    }

    /**
     * 创建企业
     * @return \think\response\Json
     */
    public function save()
    {
        $this->validate($this->param, ValidateCompany::class);

        $model = Db::transaction(function () {

            $model = CompanyModel::create($this->param);

            if (!empty($this->param['iscreatechild'])) {

                $user = UserAccount::saveUser([
                    'locale' => $this->param['locale'],
                    'account' => $this->param['mobile'],
                    'username' => $this->param['mobile'],
                    'mobile' => $this->param['mobile']
                ]);
                $userAccountId = $user['user_account_id'];
            } else {
                $userAccountId = $this->request->user['user_account_id'];
            }

            $value = Db::name('company_user')
                ->where('user_account_id', $userAccountId)
                ->where('sys_role', AuthGroup::SUPER_ADMIN)
                ->value('id');

            if (!empty($value)) {
                throw new ValidateException(lang('createuserid_unique'));
            }

            CompanyUser::extra('IGNORE')
                ->insert([
                    'username' => $this->param['linkname'],
                    'company_id' => $model->getKey(),
                    'sys_role' => AuthGroup::SUPER_ADMIN,
                    'user_account_id' => $userAccountId
                ]);

            Live::createCompany($model->getKey(), !empty($this->param['iscreatechild']) ? $this->request->user['company_id'] : null);

            return $model;
        });

        if (empty($this->param['iscreatechild'])) {

            // 验证是否有权限登录当前企业
            $companyUser = CompanyUser::getCompanyUserInfo($this->request->user['user_account_id'], $model->getKey());
            // 注销当前token
            JWTAuth::invalidate(JWTAuth::token()->get());
            // 生成新token
            $userinfo = array_merge($this->request->user, $companyUser);
            return $this->success(['companyid' => $model->getKey(), 'id' => $model->getKey(), 'token' => JWTAuth::builder(['data' => json_encode($userinfo)])]);
        }

        return $this->success($model->getKey());
    }

    public function read($id)
    {
        $res = CompanyModel::withSearch(['detail'])
            ->findOrFail($id)
            ->hidden(['authkey', 'extra_info', 'notice_config', 'users']);
        return $this->success($res);
    }

    /**
     * 编辑企业
     * @param $id
     * @return \think\response\Json
     */
    public function update($id)
    {

        $model = CompanyModel::findOrFail($id);

        $this->validate($this->param, ValidateUpdateCompany::class);

        Db::transaction(function () use ($model) {
            $allowField = $model->parentid == 1 ? ['linkname', 'locale', 'mobile', 'email', 'ico'] : [
                'linkname',
                'locale',
                'mobile',
                'email',
                'companyfullname',
                'credit_limit',
                'type',
                'business_license'
            ];

            $model->allowField($allowField)->save($this->param);

            // if ($this->param['iscreatechild'] == 1) {

            CommonAPI::httpPost('CommonAPI/updateCompanyFields', [
                'key' => $model['authkey'],
                'companyname' => $model->companyfullname,
                'credit_limit' => $model->credit_limit,
                // 'companystate' => $model->companystate,
            ]);
            // }
        });

        return $this->success();
    }

    /**
     * 冻结企业
     */
    public function freezeCompany($id)
    {
        $model = CompanyModel::findOrFail($id);

        Db::transaction(function () use ($model) {
            $model->save(['companystate' => CompanyModel::FREEZE_STATE]);

            CommonAPI::httpPost('CommonAPI/updateCompanyFields', [
                'key' => $model['authkey'],
                'companystate' => $model->companystate,
            ]);
        });


        return $this->success();
    }

    /**
     * 解冻企业
     */
    public function unfreezeCompany($id)
    {
        $model = CompanyModel::findOrFail($id);

        Db::transaction(function () use ($model) {
            $model->save(['companystate' => CompanyModel::NORMAL_STATE]);
            CommonAPI::httpPost('CommonAPI/updateCompanyFields', [
                'key' => $model['authkey'],
                'companystate' => $model->companystate,
            ]);
        });

        return $this->success();
    }


    /**
     * 删除指定资源
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        //
    }

    /**
     * 企业总数和用户数
     * @param $id
     * @return \think\response\Json
     */
    public function info($id)
    {
        $childs = CompanyModel::where('parentid', $this->request->user['company_id'])->column('id');
        array_push($childs, $this->request->user['company_id']);
        $result = [
            'company_num' => count($childs),
            'user_num' => CompanyUser::whereIn('company_id', $childs)->distinct(true)->count(),
        ];

        return $this->success($result);
    }

    /**
     * 注册企业
     */
    public function register()
    {
        $rule = [
            'companyfullname' => ['require', 'length' => '1,50', 'unique:' . CompanyModel::class],
            'linkname|' . lang('linkname') => ['require', 'chsAlpha'],
            'mobile' => ['require'],
            'smscode' => ['require'],
        ];
        $message = [
            'companyfullname.require' => 'companyfullname_require',
            'companyfullname.length' => 'companyfullname_length',
            'companyfullname.unique' => 'companyfullname_unique',
            'linkname.require' => 'linkname_validate',
            'mobile' => 'mobile_validate',
            'smscode' => 'smscode_reqiure',
        ];

        $this->validate($this->param, $rule, $message);

        //创建主企业修改规则
        $abbreviation_code = config('countrycode')['abbreviation_code']; // 区域号码
        $this->param['locale'] = isset($abbreviation_code[$this->param['locale'] ?? '']) ? $this->param['locale'] : 'CN';

        $this->validate($this->param, $rule, $message);
        // 验证短信验证码
        if ((isset($this->param['mobile']) && $this->param['mobile']) && (isset($this->param['smscode']) && $this->param['smscode'])) {
            $areacode = $abbreviation_code[$this->param['locale']];

            $check_smscode = ($this->param['smscode'] == config('app.super_smscode') && env('app_debug')) || $this->checkVerificationCode(intval($areacode . $this->param['mobile']), intval($this->param['smscode']), 'saas');
            if (!$check_smscode) {
                throw new ValidateException(lang('msg_error'));
            }
        }

        $result = (new CompanyModel())->register($this->param);
        return $this->success($result);
    }


    /**
     * @return \think\response\Json
     */
    public function getCompanyConfig()
    {
        return $this->success((new CompanyHttp)->getConfig());
    }

    /**
     * @return \think\response\Json
     */
    public function updateCompanyConfig()
    {

        (new CompanyHttp)->setConfig($this->param['configid'], $this->param['configval'], $this->param['configItem'] ?? []);

        return $this->success();
    }

    /**
     * 设置回放周期
     */
    public function setRecord()
    {
        $rule = [
            'video_days' => ['require', 'integer', 'in:0,7,14,30,60'],
            'mp4_days' => ['require', 'integer', 'in:0,7,14,30,60'],
        ];

        $message = [
            'video_days' => 'video_days_error',
            'mp4_days' => 'mp4_days_error'
        ];

        $this->validate($this->param, $rule, $message);

        CommonAPI::httpPost('CommonAPI/updateChargingItem', [
            'key' => CompanyModel::cache(true)->find($this->request->user['company_id'])['authkey'],
            'common_keep_day' => $this->param['video_days'],
            'mp4_keep_day' => $this->param['mp4_days'],
        ]);

        return $this->success();
    }

    /**
     * 获取录制件设置周期
     */
    public function getRecord()
    {
        $apiRes = CommonAPI::httpPost('CommonAPI/searchChargingItem', [
            'key' => CompanyModel::cache(true)->find($this->request->user['company_id'])['authkey'],
        ]);
        return $this->success(['video_days' => $apiRes['data']['common_keep_day'], 'mp4_days' => $apiRes['data']['mp4_keep_day']]);
    }
}
