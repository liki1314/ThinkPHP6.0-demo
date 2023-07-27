<?php

declare(strict_types=1);

namespace app\admin\middleware;


use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

class LoginLimitHalfHour
{

    public static $type = [

        1 => [
            "key" => "login:half:hour",
            "err" => "login_limit_half_hour",
        ],
        2 => [
            "key" => "sms:half:hour",
            "err" => "sms_limit_half_hour",
        ]
    ];

    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @param $type
     * @return Response
     * @throws Exception
     */
    public function handle($request, \Closure $next, $type)
    {
        $phone = $request->post('phone', "15888888888");
        $key = sprintf("%s:%s", self::$type[$type]["key"], $phone);
        //半个小时限制
        $limit = Cache::get($key);
        if ($limit !== null && $limit >= 5) {
            throw new ValidateException(lang(self::$type[$type]["err"]));
        }

        $response = $next($request);
        $data = $response->getData();
        //type = 1 登录限制 只有错误时候才限制
        //type = 2 短信限制 成功才限制
        if (
            $type == 2 && isset($data['result']) && $data['result'] == 0 ||
            $type == 1 && isset($data['result']) && $data['result'] != 0
        ) {
            $limit === null ? Cache::set($key, 1, 60 * 30) : Cache::inc($key);
        }

        if ($type == 1 && isset($data['result']) && $data['result'] == 0) {
            Cache::delete($key);
        }

        return $response;
    }
}
