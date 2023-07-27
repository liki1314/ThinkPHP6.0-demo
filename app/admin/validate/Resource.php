<?php

/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2020/12/23
 * Time: 11:38
 */

namespace app\admin\validate;

use app\admin\model\Catalog;
use think\Validate;

class Resource extends Validate
{
    protected  $rule = [
        'catalog_title' => ['require', 'max' => 100],
        'pid' => ['require', 'integer'],
    ];

    protected  $message = [
        'catalog_title.require' => 'file_dir_empty',
        'pid.integer' => 'current_file_pid_error',
    ];


}
