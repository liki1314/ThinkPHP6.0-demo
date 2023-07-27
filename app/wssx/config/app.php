<?php
return [
    'member_period' => env('wssx.member_period', 3), //会员试用期（天）
    'qrcode_period' => env('wssx.qrcode_period', 45 * 60), //二维码有效期（秒）
    'order_prefix' => env('wssx.order_prefix', 'WSSX'), //订单号前缀

    'apple_bundle_id' => 'com.talkcloud.wssx', //苹果包名

    'service_tel' => env('wssx.service_tel', ''),
];
