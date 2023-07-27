<?php

use think\facade\Route;

Route::group(function (\app\Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    // 视频
    // Route::resource('video', $version . 'Video');
    // 播放列表
    // Route::resource('play', $version . 'Play');
    // 视频分类
    // Route::resource('category', $version . 'Category');

    Route::group('category', function () {
        Route::post('', 'save');
        Route::delete('<id>','delete');
        Route::get('<id>', 'read');
        Route::put('<id>', 'update');
    })->prefix($version . 'category/');


    //播放列表
    Route::group('play', function () {
        Route::get('', 'index');
        Route::post('', 'save');
        Route::put('<play_id>/video/<id>', 'move');
        Route::put('<id>', 'update');
        Route::delete('<play_id>/video/<id>', 'remove');
    })->prefix($version . 'play/');

    Route::group('video', function () {
        Route::get('', 'index');
        Route::delete('<id>', 'delete');
        Route::post('<video_id>/encrypt', 'encrypt');
        Route::post('<action>', '<action>');
    })->prefix($version . 'video/');

    //视频统计
    Route::group('statistics', function () {
        Route::get('viewlog/<date>', 'viewLog');
        Route::get('videoview', 'videoView');
        Route::get('device', 'device');
        Route::get('visitor', 'visitor');
        Route::get('video/<id>/traffic', 'traffic');
        Route::get('duration', 'duration');
        Route::get('engagement/<video_id>-<viewer_id>', 'engagement');
    })->prefix($version . 'statistics_video/');



    //用户视频设置
    Route::group('setting', function () {
        Route::get('playsafe/<userid>', 'read');
        Route::post('playsafe', 'playsafe');
    })->prefix($version . 'setting/');


    // 微录课
    Route::group('microcourse', function () {
        Route::get('', 'index'); //微课列表
        Route::post('', 'save'); //新建微课
    })->prefix($version . 'microcourse/');


})->middleware(app\webapi\middleware\CheckSign::class);
