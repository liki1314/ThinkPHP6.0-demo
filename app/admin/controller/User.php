<?php

namespace app\admin\controller;

use app\admin\model\Company as ModelCompany;
use app\admin\model\CompanyUser;
use app\common\lib\sms\Rule;
use thans\jwt\facade\JWTAuth;
use think\Request;
use app\common\Code;
use app\common\service\Upload;
use think\exception\ValidateException;
use app\gateway\model\UserAccount;
use app\admin\validate\Login as ValidateUser;
use think\helper\Arr;
use think\facade\Lang;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\PhoneNumber;
use app\common\sms\CodeMessage;

class User extends Base
{
    use Rule;

    public function sms(Request $request)
    {
        $this->validate($this->param, ValidateUser::class . '.smscode');

        $lang = $request->param('lang', 'zh-cn');
        $locale = $request->post('locale', 'CN');
        $phone = $request->post('phone', '');

        $abbreviation_code = config('countrycode.abbreviation_code');
        $locale = isset($abbreviation_code[$locale]) ? $locale : 'CN';
        $areacode = $abbreviation_code[$locale];

        $code = $this->createVerificationCode($areacode . $phone, 'saas', 10 * 60);

        if (empty($code)) {
            throw new ValidateException(lang('send_error'));
        }

        $easySms = new EasySms(config('easysms'));
        $gateway = $areacode == '86' ? 'chuanglan' : 'qcloud';
        // $gateway = 'qcloud';
        $message = new CodeMessage($code, $locale, $lang);
        $easySms->send(($locale == 'CN') ? $phone : new PhoneNumber($phone, $areacode), $message, [$gateway]);

        return $this->success();
    }

    public function login()
    {
        $this->validate($this->param, ValidateUser::class . '.login');

        $model = UserAccount::login(
            $this->param['locale'],
            $this->param['phone'],
            $this->param['login_type'],
            $this->param['pwd'] ?? $this->param['smscode']
        );
        $model['user_account_id'] = $model['id'];
        $model['userid'] = $model['live_userid']??$model['id'];
        $token = JWTAuth::builder(['data' => $model->visible(['user_account_id', 'userid', 'username', 'account'])->toJson()]);

        return $this->success(['token' => $token]);
    }

    public function logout()
    {
        //拉黑token
        JWTAuth::invalidate(JWTAuth::token()->get());
        return $this->success();
    }

    public function modify()
    {
        $model = UserAccount::findOrFail($this->request->user['user_account_id']);
        $model->save(Arr::only($this->param, ['nickname', 'avatarFile']));

        return $this->success();
    }

    /**
     *账号验证
     */
    public function accountVerify()
    {
        $this->validate($this->param, ValidateUser::class . '.verify');

        UserAccount::verify(
            $this->request->user['account'],
            $this->param['type'],
            $this->param['pwd_or_code']
        );

        return $this->success();
    }

    /**
     * 修改手机号
     */
    public function mobile()
    {
        $this->validate($this->param, ValidateUser::class . '.modifyMobile');

        $model = UserAccount::verify(
            $this->request->user['account'],
            $this->param['type'],
            $this->param['pwd_or_code']
        );
        $model->modifyMobile($this->param['new_locale'], $this->param['new_mobile']);

        JWTAuth::invalidate(JWTAuth::token()->get());

        return $this->success();
    }

    public function info()
    {
        $model = UserAccount::field("locale,account,live_userid userid,username as nickname,avatar")
            ->findOrFail($this->request->user['user_account_id'])->append(['code', 'mobile']);

        if (!empty($this->request->user['company_id'])) {
            $companys = ModelCompany::withSearch(['child'], $this->request->user)->column('id companyid,parentid,companyfullname,companyname,ico,companystate,endtime,type', 'id');
            $companys = array_map(function ($value) {
                $value['ico'] = Upload::getFileUrl($value['ico']);
                return $value;
            }, $companys);
            $loginCompany = $companys[$this->request->user['company_id']];
            unset($companys[$this->request->user['company_id']]);
            $companys = array_values($companys);
            array_unshift($companys, $loginCompany);
            $model['company'] = $companys;
            $model['super_user'] = $this->request->user['super_user'] ?? 0;
        }

        return $this->success($model);
    }

    public function refreshToken()
    {
        return $this->success(['token' => JWTAuth::refresh()]);
    }

    public function countrycode()
    {
        $searchstr = str_replace(' ', '', $this->request->request('searchstr'));
        $countrycode = config('countrycode');
        $code_list = $countrycode[Lang::getLangSet()];

        if ($searchstr) {
            $search_list = [];
            foreach ($code_list as $key => $value) {
                if ((strpos($value['country'], $searchstr) !== false) || (strpos($value['code'], str_replace('+', '', $searchstr)) !== false)) {
                    $search_list[] = $value;
                }
            }
            $code_list = $search_list;
        }

        $list = [];
        foreach ($code_list as $key => $value) {
            $list[] = [
                'country' => $value['country'],
                'locale' => $value['abbreviation'],
                'code' => $value['code'],
            ];
        }

        return $this->success($list);
    }

    public function entryCompany($companyId = 0)
    {
        $model = ModelCompany::find($companyId);

        if (empty($model)) {
            throw new ValidateException(lang('invalid company'));
        }

        if (
            $model['parentid'] != ModelCompany::ROOT_COMPANY
            && in_array($model['companystate'], [4, 5])
        ) {
            throw new ValidateException(lang('child_freeze'));
        }

        // 验证是否有权限登录当前企业
        $this->request->user = array_merge($this->request->user, ['company_id' => $companyId]);
        $companyUser = CompanyUser::getCompanyUserInfo($this->request->user['user_account_id'], $this->request->user['company_id']);

        if (empty($companyUser)) {
            throw new ValidateException(lang('账号异常'));
        }

        if (!$this->request->param('no_black_token')) {
            // 注销当前token
            JWTAuth::invalidate(JWTAuth::token()->get());
        }

        // 生成新token
        $token = JWTAuth::builder(['data' => json_encode(array_merge($this->request->user, Arr::except($companyUser, ['role'])))]);

        $this->validate($this->param, ['company_id' => function () use ($model, $token, $companyId) {
            if (
                $model['companystate'] == ModelCompany::TRIAL_STATE
                && $model['endtime'] < date('Y-m-d H:i:s')
                || $model['parentid'] == ModelCompany::ROOT_COMPANY
                && in_array($model['companystate'], [4, 5])
            ) {
                return ['result' => Code::DUE, 'token' => $token];
            }

            if (
                $model['companystate'] == ModelCompany::NORMAL_STATE
                && ($money = $model['balance'] + $model['credit_limit']) < 0
            ) {
                return ['result' => Code::BALANCE_NOT_ENOUGH, 'token' => $token];
            }

            return true;
        }]);

        return $this->success(['token' => $token]);
    }

    public function company()
    {
        $data = ModelCompany::alias('a')
            ->join(['saas_company_user' => 'b'], 'b.company_id = a.id and b.delete_time = 0 and b.state = 1')
            // ->withSearch(['valid'])
            ->where('b.user_account_id', $this->request->user['user_account_id'])
            ->where('a.id', '<>', ModelCompany::ROOT_COMPANY)
            ->where('type', '<>', 6)
            ->field('company_id,companyname as company_name,ico,parentid,createuserid')
            ->select()
            ->each(function ($model) {
                $model['logoicon'] = $model['ico'];
                $model['my_master_company'] = $model['parentid'] == ModelCompany::ROOT_COMPANY && $model['createuserid'] == $this->request->user['user_account_id'] ? 1 : 0;
            })
            ->hidden(['parentid', 'createuserid', 'ico']);

        return $this->success($data);
    }


    /**
     * 找回/忘记密码
     */
    public function forgetPwd()
    {
        $this->validate($this->param, ValidateUser::class . '.forgotPwd');

        $model = UserAccount::where('account', config("countrycode.abbreviation_code.{$this->param['locale']}") . $this->param['phone'])->findOrFail();

        $model->save(['pwd' => $model->getUserPwd($this->param['new_pwd'])]);

        // $token = JWTAuth::builder(['data' => $model->visible(['id', 'avatar', 'username', 'account', 'locale'])->toJson()]);

        return $this->success();
    }


    /**
     * 修改密码
     */
    public function pwd()
    {
        $this->validate($this->param, ValidateUser::class . '.updatePwd');

        $model = UserAccount::where('account', $this->request->user['account'])->findOrFail();
        $model->save(['pwd' => $model->getUserPwd($this->param['new_pwd'])]);

        return $this->success();
    }
}
