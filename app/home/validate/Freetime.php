<?php

declare(strict_types=1);

namespace app\home\validate;

use think\Validate;

class Freetime extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'from_time' => ['require', 'dateFormat' => 'H:i'],
        'to_time' => ['require', 'dateFormat' => 'H:i', '>:from_time'],
        'current_date' => ['require', 'dateFormat' => 'Y-m-d', 'after:-1 days'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'from_time' => '开始时间错误',
        'to_time' => '结束时间错误',
        'current_date' => '日期错误',
    ];
}
