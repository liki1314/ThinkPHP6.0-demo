<?php

use think\facade\Route;

Route::group(function () {


    Route::group('company', function () {
        Route::put('<authkey>', 'update'); //修改企业
    })->prefix('company/');



    Route::group('financial', function () {
        Route::post('record', 'record');
        Route::post('room', 'room');
        Route::post('storage', 'storage');
        Route::post('storageDetails', 'storageDetails');
        Route::post('recordDetails', 'recordDetails');
        Route::post('roomDetails', 'roomDetails');
        Route::post('recharge', 'recharge');
        Route::post('micro', 'micro');
    })->prefix('FinancialDetails/');



    Route::group('room', function () {
        Route::post('startOrEnd', 'startOrEnd')->middleware(\app\webapi\middleware\After::class); //老师点击上下课通知
        Route::post('accessRecord', 'accessLog')->middleware(\app\webapi\middleware\After::class); //学生老师进出教室记录通知
        Route::post('<custom_id>/record', 'microRecord'); //微录课录制件回调
    })->prefix('room/');

    Route::get('user/<userid>', 'user/front'); //老网校临时用：获取老师和学生账号信息

    Route::post('recordback', 'room/recordback'); //微思普通录制件回调

    Route::post('file/convert', 'file/convert')->middleware(\app\webapi\middleware\After::class);//文件转换回调

})->middleware(\app\webapi\middleware\CheckGlobal::class);
