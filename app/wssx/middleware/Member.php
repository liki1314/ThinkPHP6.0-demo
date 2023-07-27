<?php

declare(strict_types=1);

namespace app\wssx\middleware;

use app\wssx\model\FrontUser;
use app\gateway\model\UserAccount;
use think\helper\Arr;

class Member
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
        $user = UserAccount::cache(true)->findOrFail($request->user['user_account_id']);

        if (isset($user['extend_info']['member_expire']) && $user['extend_info']['member_expire'] < time() && $request->param('identity') == FrontUser::TEACHER_TYPE) {
            abort(403, '拒绝访问');
        }

        $request->user = array_merge($request->user, $user->visible(['username', 'avatar'])->toArray());
        return $next($request);
    }
}
