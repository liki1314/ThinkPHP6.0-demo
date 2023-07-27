<?php
/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2021/1/11
 * Time: 18:00
 */

namespace app\webapi\validate;
use think\Validate;

class Setting extends Validate
{
    protected $rule = [
        'encrypt' => ['require','integer','in'=>[0,1]],
        'hlslevel' => ['require','in'=>['open','web','app','wxa_app']],
        'userid' => ['require','max' => 20, 'chsDash'],
    ];

    protected $message = [
        'encrypt.require'=>'encrypt_empty',
        'encrypt.integer'=>'encrypt_type',
        'encrypt.in'=>'encrypt_type',
        'hlslevel.in'=>'hlslevel_type',
    ];
}