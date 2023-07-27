<?php
// 全局中间件定义文件
return [
    // 全局请求缓存
    // \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    // \think\middleware\SessionInit::class
    // 跨域请求支持
    [
        \think\middleware\AllowCrossDomain::class,
        [['Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With, key, version']]
    ],

    // 设置当前时区
    app\common\middleware\SetTimeZone::class,
];
