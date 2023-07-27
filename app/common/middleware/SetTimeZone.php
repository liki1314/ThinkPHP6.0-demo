<?php

declare(strict_types=1);

namespace app\common\middleware;

use think\facade\Config;
use think\Response;

class SetTimeZone
{

    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     * @throws Exception
     */
    public function handle($request, \Closure $next)
    {
        if ($request->method(true) == 'OPTIONS') {
            return Response::create()->code(204);
        }

        date_default_timezone_set($request->param('timezone', Config::get('app.default_timezone', 'Asia/Shanghai')));

        return $next($request);
    }
}
