<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;

class CompanyConfig extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'freetime_switch' => ['require', 'in:0,1'],
        'freetime_duration' => ['requireIf:freetime_switch,1', 'integer', '>=:0'],
        'lesson_duration' => ['requireIf:freetime_switch,0', 'integer', '>=:0'],
        'h24' => ['require', 'in:0,1'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'freetime_duration' => "Please enter the length of teacher's leisure time.",
        'lesson_duration' => 'Please enter the length of a class.',
    ];

    protected $scene = [
        'scheduling' => ['freetime_switch', 'freetime_duration', 'lesson_duration'],
        'time_format' => ['h24'],
    ];
}
