<?php

namespace app;

// 应用请求对象类
class Request extends \think\Request
{
    protected $filter = [\app\common\service\Filter::class . '::trim'];
}
