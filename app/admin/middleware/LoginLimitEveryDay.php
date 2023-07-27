<?php

declare(strict_types=1);

namespace app\admin\middleware;

use DateTime;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;

class LoginLimitEveryDay
{

    public static $type = [

        1 => [
            "key" => "login:every:day",
            "err" => "login_limit_every_day",
        ],
        2 => [
            "key" => "sms:every:day",
            "err" => "sms_limit_every_day",
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
        $limit = Cache::get($key);
        if ($limit !== null && $limit >= 20) {
            throw new ValidateException(lang(self::$type[$type]["err"]));
        }

        $response = $next($request);
        $data = $response->getData();

        //type = 1 登录限制 只有错误时候才限制
        //type = 2 短信限制 成功才限制
        if (
            $type == 1 && isset($data['result']) && $data['result'] != 0 ||
            $type == 2 && isset($data['result']) && $data['result'] == 0
        ) {
            $limit === null ? Cache::set($key, 1, new DateTime(date("Y-m-d 23:59:59"))) : Cache::inc($key);
        }

        return $response;
    }
}
