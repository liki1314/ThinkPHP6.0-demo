<?php

declare(strict_types=1);

namespace app\common\middleware;

use thans\jwt\exception\JWTException;
use thans\jwt\facade\JWTAuth;

class Check
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     * @throws JWTException
     */
    public function handle($request, \Closure $next)
    {
        $payload = JWTAuth::auth(false);
        $request->user = json_decode($payload['data']->getValue(), true);

        //user_account_id为token新加字段
        if (!isset($request->user['user_account_id'])) {
            throw new JWTException('系统异常');
        }

        if (empty($request->user['userid'])) {
            throw new JWTException('系统异常');
        }

        return $next($request)->header(['Access-Control-Expose-Headers' => 'Authorization']);
    }
}
