<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'notice' => app\admin\command\Notice::class,
        'test' => app\admin\command\Test::class,
        'sms' => app\gateway\command\Sms::class,
        'room_notice' => app\admin\command\RoomNotice::class,
        'cancelorder' => app\wssx\command\CancelOrder::class,
        'synccompany' => app\wssx\command\SyncCompany::class,
        'coupon' => app\wssx\command\Coupon::class,
        'exportWssxOrder' => app\wssx\command\ExportOrder::class,
    ],
];
