<?php

use think\facade\Route;
use app\Request;

Route::group(function (Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::group(function () {
        Route::get('countrycode', 'countrycode'); // 国际区号
        Route::post('login', 'login')->middleware('terminalLog'); // 登录
        Route::post('checkMobile', 'checkMobile');
        Route::get('logout', 'logout')->middleware('check'); // 退出
        Route::put('user', 'update')->middleware('check'); // 修改用户信息
        Route::get('user', 'read')->middleware('check'); // 用户信息详情
        Route::group('pwd', function () {
            Route::put('forgot', 'forgotPwd'); // 忘记密码
            Route::put('update', 'modifyPwd')->middleware('check'); // 修改密码
        });
        Route::put('mobile', 'modifyMobile')->middleware('check'); // 修改手机号
        Route::post('sms', 'getSmsCode'); // 获取短信验证码
        Route::post('verify', 'verify')->middleware('check'); // 账号校验
        Route::post('suggestion', 'suggestion'); //意见箱
    })->prefix($version . 'user/');

    Route::get('wechat/qrcode', $version . 'weChat/qrCode')->middleware('check')->append(['_nolog' => 1]); // 公众号二维码

    Route::post('upload', $version . 'upload/save')->middleware('check'); // 上传
});
