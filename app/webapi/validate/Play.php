<?php
/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2021/1/4
 * Time: 10:52
 */

namespace app\webapi\validate;
use think\Validate;

class Play extends Validate
{

    protected $rule = [
        'title' => ['require', 'max' => 20, 'chsDash'],
        'tag' => ['max' => 20, 'chsDash'],
        'desc' => ['max' => 20, 'chsDash'],
    ];

    protected $message = [
        'title.require' => 'title_empty',
    ];


}