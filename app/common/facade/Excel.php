<?php

declare(strict_types=1);

namespace app\common\facade;

use think\Facade;

/**
 * @see \app\common\service\Excel
 * @package app\common\facade
 * @mixin \app\common\service\Excel
 * @method static \think\Response export(array $data, array $head, string $title) 导出excel
 * @method static array import(string $fileName) 导入excel
 */
class Excel extends Facade
{
    protected static function getFacadeClass()
    {
        return 'app\common\service\Excel';
    }
}
