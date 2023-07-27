<?php

declare(strict_types=1);

namespace app\webapi\middleware;

class After
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
        /** @var \think\Response */
        $response = $next($request);
        $result = $response->getData();
        if (isset($result['result']) && $result['result'] === 0) {
            $response->contentType('text/plain')->content('success');
        }

        return $response;
    }
}
