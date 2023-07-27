<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'local'),
    // 默认多磁盘
    'multi_default' => env('filesystem.multi_driver', ['public']),
    // 上传父目录
    'upload_path' => env('filesystem.upload_path', 'upload'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => app()->getRuntimePath() . 'storage',
            'url' => env('host.local', ''),
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 腾讯云
        'qcloud' => [
            'type'            => 'qcloud',
            // 默认的存储桶地域
            'region'          => env('qcloud.region', 'gz'),
            'appId'           => env('qcloud.app_id', ''),
            // 云 API 密钥 SecretId
            'secretId'        => env('qcloud.secret_id', ''),
            // 云 API 密钥 SecretKey
            'secretKey'       => env('qcloud.secret_key', ''),
            // 存储桶名称
            'bucket'          => env('qcloud.bucket', ''),
            'timeout'         => 60,
            'connect_timeout' => 60,
            'cdn'             => '',
            'scheme'          => 'https',
            'read_from_cdn'   => false,
            'url'            => env('host.download', '')
        ],
        // 腾讯云录制件
        'qcloud_record' => [
            'type'            => 'qcloud',
            // 默认的存储桶地域
            'region'          => env('qcloud.record_region', 'ap-guangzhou'),
            'appId'           => env('qcloud.app_id', ''),
            // 云 API 密钥 SecretId
            'secretId'        => env('qcloud.secret_id', ''),
            // 云 API 密钥 SecretKey
            'secretKey'       => env('qcloud.secret_key', ''),
            // 存储桶名称
            'bucket'          => env('qcloud.record_bucket', ''),
            'timeout'         => 60,
            'connect_timeout' => 60,
            'cdn'             => '',
            'scheme'          => 'https',
            'read_from_cdn'   => false,
            'url'            => env('host.download', '')
        ],

        // 华为云
        'hwcloud_doc' => [
            'type'            => 'hwcloud',
            'key'             => env('hwcloud.key', ''),
            'secret'          => env('hwcloud.secret', ''),
            'endpoint'        => env('hwcloud.endpoint', ''),
            'bucket'          => env('hwcloud.doc_bucket', ''),
            'socket_timeout'  => env('hwcloud.socket_timeout', 30),
            'connect_timeout' => env('hwcloud.connect_timeout', 10),
            'signature'       => env('hwcloud.signature', 'obs'),
            'url'            => env('host.download', '')
        ],
        'hwcloud_record' => [
            'type'            => 'hwcloud',
            'key'             => env('hwcloud.key', ''),
            'secret'          => env('hwcloud.secret', ''),
            'endpoint'        => env('hwcloud.endpoint', ''),
            'bucket'          => env('hwcloud.record_bucket', ''),
            'socket_timeout'  => env('hwcloud.socket_timeout', 30),
            'connect_timeout' => env('hwcloud.connect_timeout', 10),
            'signature'       => env('hwcloud.signature', 'obs'),
        ],
        'hwcloud_video' => [
            'type'            => 'hwcloud',
            'key'             => env('hwcloud.key', ''),
            'secret'          => env('hwcloud.secret', ''),
            'endpoint'        => env('hwcloud.endpoint', ''),
            'bucket'          => env('hwcloud.record_bucket', ''),
            'socket_timeout'  => env('hwcloud.socket_timeout', 30),
            'connect_timeout' => env('hwcloud.connect_timeout', 10),
            'signature'       => env('hwcloud.signature', 'obs'),
        ],
    ],
];
