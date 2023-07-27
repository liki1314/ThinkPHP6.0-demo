<?php
declare (strict_types = 1);

namespace app\home\middleware;

use app\home\model\saas\FrontUser;
use think\exception\ValidateException;

class OnlyStudent
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
        if ($request->user['current_identity'] != FrontUser::STUDENT_TYPE) {
            throw new ValidateException(lang('Unauthorized operation'));
        }

        return $next($request);
    }
}
