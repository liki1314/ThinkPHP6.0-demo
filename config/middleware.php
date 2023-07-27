<?php

// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        // 登录校验
        'check' => [
            thans\jwt\middleware\JWTAuthAndRefresh::class,
            app\common\middleware\Check::class,
        ],

        'live' => app\common\middleware\Live::class,

        'loginLimitHalfHour' => app\admin\middleware\LoginLimitHalfHour::class,
        'loginLimitEveryDay' => app\admin\middleware\LoginLimitEveryDay::class,
        'terminalLog' => app\common\middleware\TerminalLog::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [
        thans\jwt\middleware\JWTAuthAndRefresh::class,
        app\common\middleware\Check::class,
    ],
];
