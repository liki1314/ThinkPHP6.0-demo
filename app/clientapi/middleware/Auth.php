<?php

declare(strict_types=1);

namespace app\clientapi\middleware;

class Auth
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (abs(time() - $request->param('time', 0)) > 300) {
            abort(403, '时间参数错误');
        }

        if (md5('' . $request->param('time')) != $request->param('key')) {
            abort(403, '参数验证错误');
        }

        return $next($request);
    }
}
