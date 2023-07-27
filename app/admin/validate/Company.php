<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;
use app\admin\model\Company as CompanyModel;

class Company extends Validate
{
    use \app\common\lib\sms\Rule;

    public function field()
    {
        $this->field = [
            'linkname' => lang('linkname'),
        ];
    }

    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'ico' => ['image', 'fileSize' => 2 * 1024 * 1024],
        'business_license' => ['requireIf:companystate,' . CompanyModel::NORMAL_STATE, 'image', 'fileSize' => 10 * 1024 * 1024],
        'linkname' => ['require', 'chsAlpha', 'max:64'],
        'locale' => ['require', 'checkLocale'],
        'mobile' => ['requireIf:iscreatechild,1', 'ruleIfMatch:locale,=:CN,mobile'],
        'smscode' => 'checkSmscode',
        'email' => ['email'],
        'companytype' => ['requireIf:iscreatechild,1', 'in:1,2'],
        'companyfullname' => ['require', 'max:50', 'unique:' . CompanyModel::class],
        'credit_limit' => ['requireIf:iscreatechild,1', 'float', 'checkCreditLimit'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'companyfullname.require' => 'companyfullname_require',
        'companyfullname.length' => 'companyfullname_length',
        'companyfullname.unique' => 'companyfullname_unique',
        'ico.image' => 'ico_image',
        'ico.fileSize' => 'ico_filesize',
        'business_license.requireIf' => 'business_license_image',
        'business_license.image' => 'business_license_image',
        'business_license.fileSize' => 'business_license_filesize',
        'linkname.require' => 'linkname_validate',
        'locale' => 'locale_validate',
        'mobile' => 'mobile_validate',
        'smscode' => 'smscode_reqiure',
        'companytype' => 'companytype_reqiure',
    ];

    protected function checkSmscode($value, $rule, $data)
    {
        if (empty($data['iscreatechild']) && !empty($data['mobile'])) {
            if (!Validate::checkRule($value, 'require')) {
                return lang('smscode_reqiure');
            }
            return $this->checkVerificationCode(intval(config('countrycode.abbreviation_code.' . $data['locale']) . $data['mobile']), intval($value), 'saas') ? true : lang('msg_error');
        }

        return true;
    }

    protected function checkCreditLimit($value, $rule, $data)
    {
        if ($data['iscreatechild'] == 1) {
            return $value <= CompanyModel::where('id', $data['user']['company_id'])->value('credit_limit') ? true : lang('The pre authorization limit cannot be greater than the pre authorization limit of the main enterprise');
        }
        return true;
    }

    protected function checkLocale($value)
    {
        return config("countrycode.abbreviation_code.$value") ? true : lang('locale_validate');
    }
}
