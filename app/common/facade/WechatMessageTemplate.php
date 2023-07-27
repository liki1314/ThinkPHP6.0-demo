<?php

declare(strict_types=1);

namespace app\common\facade;

use think\Facade;

/**
 * @see \app\common\WechatMessageTemplate
 * @package app\common\facade
 * @mixin \app\common\WechatMessageTemplate
 * @method static \app\common\wechat\messages\template\Driver store()
 */
class WechatMessageTemplate extends Facade
{
    protected static function getFacadeClass()
    {
        return 'app\common\WechatMessageTemplate';
    }
}
