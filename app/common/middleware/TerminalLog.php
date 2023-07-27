<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-30
 * Time: 09:15
 */

namespace app\common\middleware;

use think\facade\Cache;

class TerminalLog
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
        $response = $next($request);
        $result = $response->getData();

        //表明为移动端登录
        if ($request->isMobile() || $request->header('terminal-type') == 1) {

            $user_account_id = '';

            if (!empty($request->user) && isset($request->user['user_account_id'])) {
                $user_account_id = $request->user['user_account_id'];
            } elseif (isset($result['result']) && $result['result'] === 0 && isset($result['data']['user_account_id'])) {
                $user_account_id = $result['data']['user_account_id'];
            }

            if (!empty($user_account_id)) {
                Cache::set('terminal:app:' . $user_account_id, 1, 60 * 60 * 24 * 50);
            }
        }

        return $response;
    }
}
