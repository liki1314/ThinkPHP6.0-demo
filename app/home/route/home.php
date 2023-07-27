<?php

use think\facade\Route;
use app\Request;

Route::group(function (Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::put('user/changeLoginIdentity', $version . 'user/changeLoginIdentity')->middleware('terminalLog');
    Route::get('user/notice', $version . 'user/notice')->completeMatch(true); //通知列表
    Route::post('user/notice', $version . 'user/updateNotice'); //更新通知读取状态
    Route::get('user/notice/new', $version . 'user/countNotice'); //统计未读数量

    Route::get('room/<serial>', $version . 'lesson/roomInfo'); //根据教室号获取教室信息

    Route::group(function () use ($version) {
        Route::group('course', function () {
            Route::get('', 'index'); // 我的课程
            Route::get('<id>', 'info'); // 课程详情
        })->prefix($version . 'course/');

        //教师课节
        Route::group('lesson', function () {
            Route::get('<day>', 'index')->completeMatch(true)->pattern(['day' => '\d{4}-\d{2}-\d{2}']); // 按天搜索课节
            Route::get('<week>', 'index')->completeMatch(true)->pattern(['week' => '\d{8}']); // 按周搜索课节
            Route::get('<month>', 'index')->completeMatch(true)->pattern(['month' => '\d{4}-\d{2}']); // 按月搜索课节
            Route::get('beforeAndAfterToday', 'beforeAndAfterToday');
            Route::group('<serial>', function () {
                Route::post('remark', 'remark'); //课堂点评
                Route::get('remark/<student_id>', 'remarkDetail'); //点评详情
                Route::get('student', 'student');  //课堂学生
            })->pattern(['serial' => '\d+'])->middleware(app\home\middleware\OnlyTeacher::class);
            //学生课堂报告
            Route::get('<serial>/report', 'report')->middleware(app\home\middleware\OnlyStudent::class);

            Route::group('<lesson_id>', function () {
                Route::get('record', 'roomRecords'); //录制件
                Route::get('room', 'roomDetail'); //教室
                Route::get('files', 'roomFiles'); //课件
            });
        })->prefix($version . 'lesson/');

        //学生作业
        Route::group('homework', function () {
            Route::get('', 'index'); //作业列表
            Route::group('<homework_id>-<student_id>', function () {
                Route::post('', 'save'); //提交作业
                Route::get('', 'read'); //学生作业详情（单次提交）
                Route::put('', 'revoke'); // 撤回提交

                Route::get('record', 'record')->middleware(app\home\middleware\OnlyTeacher::class); //老师查看学生作业详情（重复提交）
            });

            Route::get('<homework_id>/record', 'record')->middleware(app\home\middleware\OnlyStudent::class); //学生作业详情（重复提交）
            Route::get('<homework_id>/students', 'students')->middleware(app\home\middleware\OnlyTeacher::class); //作业详情-学生列表

            //作业重复提交路由
            Route::group('record/<record_id>', function () {
                Route::put('', 'updateRecord')->middleware(app\home\middleware\OnlyStudent::class); //学生编辑草稿
                Route::delete('', 'recoverRecord')->middleware(app\home\middleware\OnlyStudent::class); //学生撤回提交作业
                Route::post('remark', 'remarkRecord')->middleware(app\home\middleware\OnlyTeacher::class); //老师保存记录点评
                Route::delete('remark', 'delRemarkRecord')->middleware(app\home\middleware\OnlyTeacher::class); //老师删除记录点评
            });
        })->prefix($version . 'homework/');

        //老师
        Route::group('homework', function () {
            //作业单次提交路由
            Route::group('<id>/remark', function () {
                Route::put('<student_id>', 'remark'); //编辑
                Route::delete('', 'delRemark'); //删除点评
                Route::post('', 'remark'); //作业点评
                Route::get('', 'remarkList'); //作业详情-点评列表

            });
            Route::get('usefulExpressions', 'usefulExpression')->append(['is_self' => 0]); //作业详情
            Route::delete('usefulExpressions/<id>', 'UsefulExpressionDel')->append(['is_self' => 1]); //删除评语
            Route::get('teacher', 'index'); //作业列表
            Route::post('', 'decorate'); //布置作业
            Route::post('<id>/remind', 'remind');
            Route::put('<id>', 'update'); //布置作业
            Route::delete('<id>', 'delete'); //布置作业
            // Route::get('<id>/students', 'students'); //作业详情-学生列表
            Route::get('<id>', 'show'); //作业详情
        })->prefix($version . 'HomeworkTeacher/')->middleware(app\home\middleware\OnlyTeacher::class);

        Route::group('resource', function () {

            Route::get('', 'index'); //企业网盘

        })->prefix($version . 'Resource/')->middleware(app\home\middleware\OnlyTeacher::class);


        Route::group('student', function () {

            Route::get('', 'index');
            Route::get('group', 'allStudentGroup'); // 分组下拉框

        })->prefix($version . 'Students/')->append(['userroleid' => \app\home\model\saas\FrontUser::STUDENT_TYPE]);


        //用户
        Route::group('user', function () {
            Route::get('homepage', 'homepage')->middleware('terminalLog'); //我的主页
            Route::get('company/all', 'companyList'); //企业列表
        })->prefix($version . 'user/');

        Route::group('sys', function () {
            Route::post('translate', 'translate'); //英文翻译
        })->prefix($version . 'sys/');

        //空闲时间
        Route::group('freetime', function () {
            Route::post('', 'save');
            Route::delete('<id>', 'delete');
            Route::get('', 'index');
            Route::get('lesson', 'read');
        })->prefix($version . 'freetime/')->middleware(app\home\middleware\OnlyTeacher::class);

    })->middleware(\app\home\middleware\CheckIdentity::class);
})->middleware('check');

Route::group(function (Request $request) {
    $version = $request->get('version') ?? $request->header('version');
    if (!empty($version)) {
        $version .= '.';
    }

    Route::group('sys', function () {
        Route::get('version', 'version');
    })->prefix($version . 'sys/');
});
