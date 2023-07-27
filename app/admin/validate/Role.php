<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;
use app\admin\model\AuthGroup;

class Role extends Validate
{
    public function field()
    {
        $this->field = [
            'name' => lang('role_name'),
            'desc' => lang('role_desc'),
            'enable' => lang('role_enable'),
            'rules' => lang('role_rules'),
            'data_rule' => lang('data_rule'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name'      => ['require', 'max:60', 'unique:' . AuthGroup::class],
        'desc'      => 'max:140',
        'enable'    => ['require', 'integer', 'in:0,1'],
        'rules'     => ['array', 'each' => 'integer'],
        'data_rule' => ['require', 'integer', 'in:0,1,2,3']
    ];
}
