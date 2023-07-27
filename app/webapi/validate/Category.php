<?php
/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2021/1/4
 * Time: 10:30
 */

namespace app\webapi\validate;
use think\Validate;

class Category extends Validate
{
    protected $rule = [
        'name' => ['require', 'max' => 20, 'chsDash'],
        'parent_id' => ['integer'],
    ];

    protected $message = [
        'name.require' => 'cate_name_empty',
        'parent_id.integer' => 'cate_pid_error',
    ];

    protected $scene = [
        'update'  =>  ['name'],
    ];
}