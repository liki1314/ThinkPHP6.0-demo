<?php

declare(strict_types=1);

namespace app\admin\model;

use think\facade\Lang;

/**
 * @mixin \think\Model
 */
class AuthRule extends Base
{
    protected $json = ['name'];

    public function getNameAttr($value)
    {
        return $value[Lang::getLangSet()] ?? null;
    }
}
