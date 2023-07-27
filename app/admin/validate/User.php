<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;
use app\admin\model\Department;
class User extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'username' => ['require', 'max' => 64],
        'locale' => ['require', 'checkLocale'],
        'mobile' => ['require','ruleIfMatch:locale,=:CN,mobile'],
        'state' => 'in:0,1',
        'roles' => ['require', 'array', 'max' => 100],
        'department_id' => ['integer','checkDepId'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'username' => 'username_format',
        'mobile' => 'mobile_format',
        'state' => 'state_in',
        'roles' => 'roles_array',
        'department_id' => 'department_require',
    ];

    protected function checkLocale($value)
    {
        return config("countrycode.abbreviation_code.$value") ? true : lang('locale_validate');
    }

    protected function checkDepId($value)
    {
        if (!$value) {
            return true;
        }
        $model = Department::where('id',$value)->findOrEmpty();
        return $model->isEmpty() ? lang('depid_not_exists') : true;
    }
}
