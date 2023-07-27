<?php
// 事件定义文件

use app\Request;
use thans\jwt\facade\JWTAuth;
use think\facade\Queue;
use think\facade\Log;

return [
    'bind' => [],

    'listen' => [
        'AppInit' => [],
        'HttpRun' => [],
        'HttpEnd' => [
            // 记录访问日志
            function (\think\Response $response) {
                if (!request()->isOptions() && !request()->route('_nolog')) {
                    Log::info(sprintf(
                        '%s %s %s %s %s %s %s %s',
                        request()->method(),
                        request()->url(),
                        JWTAuth::token() ? JWTAuth::setValidate(false)->setRefresh(false)->decode(JWTAuth::token())['data']->getValue() : null,
                        false !== strpos(request()->contentType(), 'json') ? request()->getContent() : json_encode(request()->post()),
                        $response->getContent(),
                        $response->getCode(),
                        request()->header('authorization'),
                        $response->getHeader('authorization'),
                    ));
                }
            }
        ],
        'LogLevel' => [],
        'LogWrite' => [],
        //通知
        'Notice' => [
            function ($notice, Request $request) {
                $notice['company_id'] = $request->user['company_id'];
                Queue::push(\app\admin\job\Notice::class, $notice);
            }
        ],
    ],

    'subscribe' => [],
];
