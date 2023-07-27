<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    'default'     => 'redis',
    'connections' => [
        'sync'     => [
            'type' => 'sync',
        ],
        'database' => [
            'type'  => 'database',
            'queue' => 'default',
            'table' => 'jobs',
        ],
        'redis'    => [
            'type'       => 'redis',
            'queue'      => env('redis_queue.name', 'default'),
            'host'       => env('redis.host', '127.0.0.1'),
            'port'       => env('redis.port', 6379),
            'password'   => env('redis.password', ''),
            'select'     => env('redis_queue.select', 0),
            'timeout'    => 0,
            'persistent' => false,
        ],
    ],
    'failed'      => [
        // 'type'  => 'database',
        'type' => app\common\queue\failed\Aliyun::class,
        'table' => 'saas_failed_jobs',
    ],
    'queue' => [
        'live' => env('redis_queue.live', 'live'),
    ],
];
