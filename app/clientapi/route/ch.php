<?php

use think\facade\Route;

Route::group(function (\app\Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::get('encrypt', 'encrypt/getVideoEncrypt')->append(['_nolog' => 1]);

    // 视频
    Route::resource('video', $version . 'Video');


    //视频日志添加
    Route::group('video', function () {

        Route::post('<video_id>/play', 'playLog');
    })->prefix($version . 'video/');

    // 视频播放日志
    Route::group('playLog', function () {
        Route::post('', 'save');
        Route::get('', 'read');
    })->prefix($version . 'playLog/');
});
