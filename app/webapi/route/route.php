<?php

use think\facade\Route;

Route::group(function (\app\Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::group(function () {
        Route::post('login', 'login'); // 登录
    })->prefix($version . 'user/');
})->middleware(app\webapi\middleware\CheckSign::class);
