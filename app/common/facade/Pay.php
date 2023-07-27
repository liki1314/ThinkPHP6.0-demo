<?php

declare(strict_types=1);

namespace app\common\facade;

use think\Facade;

/**
 * @see \app\common\Pay
 * @package app\common\facade
 * @mixin \app\common\Pay
 */
class Pay extends Facade
{
    protected static function getFacadeClass()
    {
        return 'app\common\Pay';
    }
}
