<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;
use app\admin\model\Department as DepartmentModel;

class Department extends Validate
{
    protected $rule = [
        'name' => 'require',
        'sort' => ['require', 'integer'],
        'fid' => ['integer', 'exist:' . DepartmentModel::class],
    ];
}
