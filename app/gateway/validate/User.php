<?php

declare(strict_types=1);

namespace app\gateway\validate;

use think\Validate;
use app\common\lib\sms\Rule;
use think\facade\Db;

class User extends Validate
{
    use Rule;
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'locale' => ['requireIfMatch:mode,in:1,2', 'checkLocale'],
        'mobile' => ['require', 'checkMobile'],
        'mode' => ['require', 'in:1,2,3'],
        'pwd_or_code' => ['require', 'ruleIfMatch' => 'mode,=:1,checkSmsCode'],
        'smscode' => ['require', 'checkSmsCode'],
        'new_pwd' => ['require', 'length:8,20'],
        'new_locale' => ['require', 'checkLocale'],
        'new_smscode' => 'require',
        'new_mobile' => ['require', 'ruleIfMatch:new_locale,=:CN,mobile', 'checkNewMobile'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'locale.require' => 'Please select the area code',
        'locale' => 'The area code does not exist',
        'mobile.require' => 'Please input mobile phone number',
        'mobile' => 'Incorrect format of mobile phone number',
        'mode' => 'mode error',
        'pwd_or_code' => 'Password or verification code error',
        'smscode' => 'Verification code error',
        'new_pwd' => 'Wrong password',
        'new_locale.require' => 'Please select the area code',
        'new_locale' => 'The area code does not exist',
        'new_mobile.require' => 'Please input mobile phone number',
        'new_mobile' => 'Incorrect format of mobile phone number',
        'new_smscode' => 'Verification code error',
    ];

    /**
     * 登录场景
     *
     * @return void
     */
    public function sceneLogin()
    {
        return $this->only(['mode', 'locale', 'mobile', 'pwd_or_code']);
    }

    /**
     * 修改密码场景
     *
     * @return void
     */
    public function sceneUpdatePwd()
    {
        return $this->only(['smscode', 'new_pwd']);
    }

    /**
     * 忘记密码场景
     *
     * @return void
     */
    public function sceneForgotPwd()
    {
        return $this->only(['locale', 'mobile', 'smscode', 'new_pwd']);
    }

    /**
     * 账号验证场景
     *
     * @return void
     */
    public function sceneVerify()
    {
        return $this->only(['mode', 'pwd_or_code']);
    }

    /**
     * 获取短信验证码场景
     *
     * @return void
     */
    public function sceneSmscode()
    {
        return $this->only(['locale', 'mobile']);
    }

    /**
     * 检测手机号是否存在场景
     *
     * @return void
     */
    public function sceneCheckMobile()
    {
        return $this->only(['locale', 'mobile']);
    }

    /**
     * 修改手机号场景
     *
     * @return void
     */
    public function sceneModifyMobile()
    {
        return $this->only(['mode', 'pwd_or_code', 'new_locale', 'new_smscode', 'new_mobile']);
    }

    protected function checkSmsCode($value, $rule, $data)
    {
        $account = $data['user']['account'] ?? config('countrycode.abbreviation_code.' . $data['locale']) . $data['mobile'];
        return $value == config('app.super_smscode') && env('app_debug') || $this->checkVerificationCode($account, $value, config('sms.business_type')) ?: lang('SMS error');
    }

    protected function checkNewMobile($value, $rule, $data)
    {
        $account = config('countrycode.abbreviation_code.' . $data['new_locale']) . $value;

        if ($account == $data['user']['account']) {
            return lang('The new mobile number cannot be the same as the old one');
        }

        $sms_result = $data['new_smscode'] == config('app.super_smscode') && env('app_debug') || $this->checkVerificationCode($account, $data['new_smscode'], config('sms.business_type'));
        if ($sms_result === false) {
            return lang('SMS error');
        }

        $exist = Db::name('user_account')->where('account', $account)->value('id');
        if ($exist) {
            return lang('Mobile number has been registered');
        }

        return true;
    }

    protected function checkLocale($value)
    {
        return config("countrycode.abbreviation_code.$value") ? true : lang('The area code does not exist');
    }

    protected function checkMobile($value, $rule, $data)
    {
        if ((!isset($data['mode']) || $data['mode'] != 3) && $data['locale'] == 'CN') {
            return $this->check($data, ['mobile' => 'mobile']);
        }

        return true;
    }
}
