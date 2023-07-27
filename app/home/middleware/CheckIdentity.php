<?php
declare (strict_types = 1);

namespace app\home\middleware;

use think\exception\ValidateException;

class CheckIdentity
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
        if (empty($request->user['current_identity'])) {
            throw new ValidateException(['result' => 404, 'msg' => lang('Identity cannot be empty')]);
        }

        return $next($request);
    }
}
