<?php

declare(strict_types=1);

namespace app\webapi\middleware;

use Exception;

class CheckGlobal
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
        if ($request->header('content-md5') !== md5(config('app.global.secret_key') . $request->getContent() . $request->header('time'))) {
            abort(403, '拒绝访问');
        }

        return $next($request);
    }
}
