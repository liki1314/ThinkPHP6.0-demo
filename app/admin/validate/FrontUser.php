<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;

class FrontUser extends Validate
{
    public function field()
    {
        $this->field = [
            'name' => lang('front_user_name'),
            'nickname' => lang('nickname'),
            'sex' => lang('sex'),
            'birthday' => lang('birthday'),
            'locale' => lang('locale'),
            'mobile' => lang('mobile'),
            'address' => lang('family_address'),
            'pwd' => lang('密码'),
            'domain_account' => lang('账号'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name' => ['require', 'max' => 50],
        'nickname' => 'max:50',
        'sex' => ['require', 'in' => '0,1'],
        'avatarFile' => 'image',
        'birthday' => 'date',
        'p_name' => 'max:10',
        'pwd' => ['requireCallback:checkPwd', 'length:8,20', 'alphaNum'],
        'locale' => ['requireWithout:is_custom', 'checkLocale'],
        'mobile' => ['requireWithout:is_custom', 'ruleIfMatch:locale,=:CN,mobile'],
        'domain_account' => ['requireWith:is_custom', 'checkDomainAccount'],
        'address' => ['max' => 100],
        'email' => 'email',
        'province' => 'integer',
        'city' => 'integer',
        'area' => 'integer',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        "birthday.before" => 'user_birthday_error',
        'pwd'=>'pwd_rule_error'
    ];

    public function sceneStudent()
    {
        return $this->append('birthday', 'before:-2 years');
    }

    public function sceneTeacher()
    {
        return $this->append('birthday', 'before:-20 years')
            ->append('province')
            ->append('city')
            ->append('area');
    }

    protected function checkLocale($value)
    {
        return config("countrycode.abbreviation_code.$value") ? true : lang('locale_validate');
    }

    protected function checkPwd($value,$data)
    {
        return !empty($data['is_custom']) || !empty($data['require_pwd']) || !empty($data['pwd']);
    }

    public function sceneUpdateStudent()
    {
        return $this->only(['name','sex']);
    }

    public function sceneUpdateTeacher()
    {
        return $this->only(['name','sex']);
    }

    public function checkDomainAccount($value)
    {
        return preg_match_all('/\w+@\w+/', $value) ? true : lang('domainaccount_error');
    }
}
