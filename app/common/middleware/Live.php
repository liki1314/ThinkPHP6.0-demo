<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\facade\Live as FacadeLive;
use think\Response;

class Live
{

    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @param mixed $server 需要调用的server
     * @param bool $sync 是否同步调用server
     * @return Response
     * @throws Exception
     *
     */
    public function handle($request, \Closure $next, $server)
    {
        /** @var Response $response */
        $response = $next($request);
        // 添加中间件执行代码
        $result = $response->getData();
        if (isset($result['result']) && $result['result'] === 0) {
            $servers = (array) $server;
            foreach ($servers as $value) {
                FacadeLive::send($value, $result['data'], $request);
            }
        }

        return $response;
    }
}
