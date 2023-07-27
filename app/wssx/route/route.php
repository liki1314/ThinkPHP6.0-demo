<?php

use think\facade\Route;
use app\Request;

Route::group(function (Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::group('member', function () {
        Route::get('card', 'card'); //会员卡列表
        Route::post('order', 'createOrder')->completeMatch(true); //创建支付订单
        Route::post('order/pay', 'payOrder'); //支付
        Route::get('order', 'orderList'); //订单列表
    })->prefix($version . 'member/');

    Route::group('room', function () {
        Route::get('', 'index'); //房间列表
        Route::group('<serial>', function () {
            Route::get('record', 'record'); //房间录制件列表
            Route::put('', 'update'); //修改房间名
            Route::get('enter/<identity>', 'read')->middleware(\app\wssx\middleware\Member::class); //进入房间
            Route::get('share', 'share')->middleware(\app\wssx\middleware\Member::class); //分享房间
            Route::get('qrcode', 'qrcode')->completeMatch(true); //房间二维码
            Route::get('qrcode/content', 'qrcodeContent'); //房间二维码内容
        });
    })->prefix($version . 'room/');

    Route::get('user/homepage', $version . 'user/homepage'); //首页
})->middleware('check');

Route::get('enterRoom/<serial>', 'v1.room/enterRoom'); //进入教室地址
Route::any('pay/<way>', 'pay/notice');

Route::group(function (Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }
    Route::post('courseware/convert', $version . 'room/convert'); //兑换课件
});
