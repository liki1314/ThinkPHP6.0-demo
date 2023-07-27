<?php

use app\admin\model\{
    AuthGroup,
    FrontUser,
    Course,
    Room,
    StudentGroup
};
use think\facade\Route;

Route::pattern([
    'id' => '\d+',
    'student_id' => '\d+',
    'company_id' => '\d+',
    'course_id' => '\d+',
    'userid' => '\d+',
    'serial' => '\d+'
]);

Route::get('test/attendance/user', 'test/user')->middleware('check'); //师生考勤
Route::get('test/attendance/lesson', 'test/lesson')->middleware('check'); //课节考勤
Route::get('course/<course_id>/lesson/<id>/chat/export', 'RoomNetwork/chatExport'); //聊天记录下载
Route::post('company/register', 'company/register'); //企业注册
Route::put('user/forgetPwd', 'user/forgetPwd'); //忘记/找回密码
Route::get('microcourse/show', 'microcourse/index'); //微课列表
//验证码相关
Route::group('sms', function () {

    //发送手机验证码 api:01
    Route::rule('send', 'user/sms', 'get|post|options')->middleware(['loginLimitHalfHour', 'loginLimitEveryDay'], 2);
});

//用户相关
Route::group('user', function () {

    //获取手机国际区号
    Route::rule('countrycode', 'user/countrycode', 'get|post|options');

    //用户登录 api:02
    Route::rule('login', 'user/login', 'get|post|options')->middleware(['loginLimitHalfHour', 'loginLimitEveryDay'], 1);
});

Route::group(function () {
    // 用户
    Route::group('user', function () {
        //用户退出 api:04
        Route::rule('logout', 'logout', 'get|post|options');

        //获取用户信息
        Route::rule('info', 'info', 'get|post|options');

        //修改个人信息
        Route::rule('modify', 'modify', 'get|options|put');
        //本地账号验证
        Route::rule('accountVerify', 'accountVerify', 'post');
        //修改手机号
        Route::rule('mobile', 'mobile', 'put');
        //刷新token
        Route::rule('refreshToken', 'refreshToken', 'get|post|options');

        Route::get('entryCompany/<company_id>', 'entryCompany')->validate(['company_id' => 'gt:1'], null, ['company_id' => lang('invalid company')]);

        Route::get('company', 'company');

        Route::put('pwd', 'pwd');
    })->prefix('user/');

    // 企业相关
    Route::group('company', function () {
        Route::post('', 'save');
    })->prefix('company/');

    Route::group(function () {
        // 部门
        Route::group('department', function () {
            Route::put('<id>', 'update')->append(['_code' => '080100030000']); //编辑部门
            Route::delete('<id>', 'delete')->append(['_code' => '080100040000']); //删除部门
            Route::get('tree', 'tree')/* ->append(['_code' => '080100010000']) */; //部门列表
            Route::post('', 'save')->append(['_code' => '080100020000']); //添加部门
        })->prefix('department/');

        // 角色
        Route::group('role', function () {
            Route::get('all', 'index')->append(['no_page' => 1]); // 角色列表下拉框
            Route::put('enable', 'enable')->append(['_code' => '070100040000']); //禁用角色
            Route::put('disable', 'disable')->append(['_code' => '070100040000']); //禁用角色
            Route::get('<id>', 'read')->append(['_code' => '070100010000']); //角色详情
            Route::get('', 'index')->append(['_code' => '070100010000']); //角色列表
            Route::post('', 'save')->append(['_code' => '070100020000']); //新增角色
            Route::put('<id>', 'update')->append(['_code' => '070100030000']); //编辑角色
            // Route::delete('<id>', 'batchDel')->append(['_code' => '070100050000']);//删除角色
            Route::delete('batchDel', 'batchDel')->validate(['id' => ['require', 'array', 'each' => 'integer']])->append(['_code' => '070100050000']); //批量删除
        })->prefix('role/');

        // 用户
        Route::group('userManage', function () {
            Route::get('', 'index')->append(['_code' => '060100000000']); //用户列表
            Route::get('<id>', 'read');
            Route::get('helper', 'all')->append(['group_id' => AuthGroup::HELPER_ROLE]);
            Route::get('company', 'company');
            Route::post('', 'save')->append(['create_user' => true, '_code' => '060100020000']); //新增用户
            Route::put('batchEnable', 'batchEnable');
            Route::put('batchDisable', 'batchDisable');
            Route::group(function () {
                Route::put('enable', 'enable')->append(['_code' => '060100040000']); //启用用户
                Route::put('disable', 'disable')->append(['_code' => '060100040000']); //禁用用户
                Route::put('<id>', 'update')->append(['create_user' => true, '_code' => '060100030000']); //编辑用户
                Route::delete('<id>', 'delete')->append(['_code' => '060100050000']); //删除用户
            });
            Route::delete('batchDel', 'batchDel')->validate(['id' => ['require', 'array', 'each' => 'integer']]);

            Route::delete('qrcode/<userid>', 'cancelBind'); //学生老师取消绑定通知小助手
            // 学生
            Route::group('student', function () {
                Route::get('', 'userList')/* ->append(['_code' => '020100010000']) */; //学生列表
                Route::get('group/all', 'allStudentGroup'); // 分组下拉框
                Route::post('userListExport', 'userListExport')->completeMatch(true); //学生列表导出
                Route::get('<id>', 'readUser')->append(['_code' => '020100070000']); //查看学生详情
                Route::post('', 'saveUser')->append(['_code' => '020100020000']); //新增学生档案
                Route::put('<id>', 'updateUser')->append(['_code' => '020100080000']); //编辑学生详情
                Route::delete('batchDel', 'deleteFrontUser')->append(['_code' => '020100100000']); //删除学生
                Route::put('batchDisable', 'disableFrontUser')->append(['_code' => '020100090000']); //批量禁用学生
                Route::post('attendClass', 'attendClass')->append(['_code' => '020100030000']);
                Route::post('import', 'importUser')->append(['_code' => '020100050000']); //批量导入手机账号
                Route::post('importAccount', 'importUser')->append(['_code' => '020100050001', 'is_custom' => 1]); //批量导入自定义账号
                Route::put('batchEnable', 'enableFrontUser'); //启用学生
                Route::post('attendLesson', 'attendLesson');  //批量分配课节
                // 分组
                Route::group('group', function () {
                    Route::get('', 'studentGroupList')/* ->append(['_code' => '020200010000']) */; //分组列表
                    Route::post('', 'saveStudentGroup')->append(['_code' => '020200020000']); //新增分组
                    Route::put('<id>', 'updateStudentGroup');
                    Route::delete('', 'deleteStudentGroup')->append(['_code' => '020200040000']); //解散分组
                    Route::put('disable', 'changeStudentGroupState')->append(['state' => StudentGroup::DISABLE, '_code' => '020200050000']); //禁用分组
                    Route::put('enable', 'changeStudentGroupState')->append(['state' => StudentGroup::ENABLE]);
                    Route::post('batchDivideStudent', 'batchDivideStudent')->append(['_code' => '020100040000']); //批量分组
                    Route::post('batchDivideGroup', 'batchDivideGroup')->append(['_code' => '020200030000']); // 分配学生
                });
            })->append(['userroleid' => FrontUser::STUDENT_TYPE]);
            //地区
            // Route::get('area', 'getArea');
            // 老师
            Route::group('teacher', function () {
                Route::get('all', 'all')->append(['group_id' => FrontUser::TEACHER_TYPE]); // 老师列表下拉框
                Route::get('', 'userList')->append(['_code' => '020300010000']); //老师列表
                Route::post('', 'saveUser')->append(['_code' => '020300030000']); //新增老师
                Route::get('<id>', 'readUser')->append(['_code' => '020300020000']); //查看老师
                Route::put('<id>', 'updateUser')->append(['_code' => '020300070000']); //编辑老师
                Route::delete('batchDel', 'deleteFrontUser')->append(['_code' => '020300090000']); //删除老师
                Route::put('batchDisable', 'disableFrontUser')->append(['_code' => '020300060000']); //批量 单个 禁用老师
                Route::post('import', 'importUser')->append(['_code' => '020300040000']); //批量导入
                Route::put('batchEnable', 'enableFrontUser'); //启用老师
            })->append(['userroleid' => FrontUser::TEACHER_TYPE]);
        })->prefix('userManage/');

        // 系统配置
        Route::resource('sysconfig', 'Sysconfig');

        // 企业配置
        Route::group('sysconfig', function () {
            // 排课配置
            Route::group(function () {
                Route::get('scheduling', 'getConfig');
                Route::post('scheduling', 'setConfig');
            })->append(['configname' => 'scheduling']);

            //时间配置
            Route::group(function () {
                Route::get('timeformat', 'getConfig');
                Route::post('timeformat', 'setConfig');
            })->append(['configname' => 'time_format']);
        })->prefix('sysconfig/');

        // 权限
        Route::group('auth', function () {
            Route::get('tree', 'tree');
        })->prefix('auth/');

        // 企业
        Route::group('company', function () {
            Route::get('', 'index')->append(['_code' => '050100000000']); //企业列表
            Route::get('<id>', 'read')->completeMatch(true)->append(['_code' => '050100060000']); //企业详情
            // Route::post('', 'save')->completeMatch(true)/* ->append(['_code' => '050100030000']) */;//新增企业 兼容新建子企业
            Route::put('<id>', 'update')->completeMatch(true)->append(['_code' => '050100070000']); //编辑企业
            Route::put('freeze/<id>', 'freezeCompany')->append(['_code' => '050100080000']); //冻结企业
            Route::put('unfreeze/<id>', 'unfreezeCompany')->append(['_code' => '050100080000']); //解冻企业
            //     Route::post('createChildCompany', 'save')->append(['iscreatechild' => '1', '_code' => '050100030000']);//新增子企业
            Route::get('<id>/statistics', 'info'); //企业数量统计
            //高级设置
            Route::group('<id>/config', function () {
                Route::get('', 'getCompanyConfig');
                Route::put('', 'updateCompanyConfig');
            });
        })->prefix('company/');

        // 资源库
        Route::group('resource', function () {
            Route::get('', 'index');/* ->append(['_code' => '030100010000']) */ //资源列表
            Route::get('catagory', 'catagoryList');
            Route::post('dir', 'createDir')->append(['_code' => '030100030000']); //新建文件夹
            Route::post('file', 'uploadFile')->append(['active' => 1/* , '_code' => '030100020000' */]); //上传文件
            Route::post('asyncUpload', 'uploadFile')->append(['active' => 0, 'dir_id' => 0, '_code' => '030100020000']);  //异步上传文件
            Route::put('<type>-<id>', 'update')->pattern(['type' => '[12]'])->append(['_code' => '030100080000']); //重命名文件(夹)
            Route::put('move', 'move')->append(['_code' => '030100060000']); //移动到
            Route::put('copy', 'copy')->append(['_code' => '030100070000']); //复制到
            Route::delete('', 'deleteByType')->append(['_code' => '030100040000']); //删除文件夹
            Route::get('filesize', 'filesize'); //企业上传大小配置
        })->prefix('resource/');

        Route::get('resourceDownload/<fileid>', 'resource/donwload')->append(['_code' => '030100100000']); //网盘资源下载

        // 课程资源库
        Route::get('course/<course_id>/resource', 'course/courseFileList')->append(['_code' => '010100110000']); //课程资料列表
        Route::get('course/<course_id>/record', 'course/record'); //课程录制件
        Route::delete('course/<course_id>/record', 'course/deleleRecord'); //删除课程录制件

        // 课程
        Route::group('course', function () {
            Route::group('small', function () {
                Route::post('', 'save')->append(['_code' => '010100030000']); //新增小班课
                Route::put('<id>', 'update')->append(['_code' => '010100080000']);  //编辑小班课
            })->append(['type' => Course::SMALL_TYPE]);

            Route::group('big', function () {
                Route::post('', 'save')->append(['_code' => '010100040000']); //新增大直播
                Route::put('<id>', 'update')->append(['_code' => '010100080000']);  //编辑大直播
            })->append(['type' => Course::BIG_TYPE]);

            // 课节
            Route::group('<course_id>/lesson', function () {
                Route::get('', 'index')->append(['_code' => '010100100000']);
                Route::group('<id>', function () {
                    Route::get('', 'read');

                    Route::group(function () {
                        Route::get('network', 'getNetwork')->append(['_code' => '010100100902']);  //监控
                        Route::get('report', 'report')->append(['_code' => '010100100800']); //课堂报告
                        Route::get('monitor/students', 'monitorStudents');
                        Route::get('monitor', 'monitor')->append(['_code' => '010100100900']); //监课报告
                        Route::delete('record/<record_id>', 'recordDel')->append(['_code' => '010100101004']); //删除录课数据
                        Route::get('record', 'record')->append(['_code' => '010100101000']); //录课数据
                        Route::get('chat', 'chat')->append(['_code' => '010100101100']); //聊天记录
                        Route::get('statistical/number', 'statisticalNumber'); //数量统计
                        Route::get('statistical/interval', 'statisticalInterval'); //数量统计
                    })->prefix('RoomNetwork/');

                    Route::get('before', 'beforePrepare')->append(['_code' => '010100100500']); //课前准备
                    Route::put('allocate', 'allocate'); //课节分配学生
                    Route::put('<action>', '<action>')->append(['_code' => '010100100400']); //调课


                });
                Route::post('', 'save')->append(['_code' => '010100100100'])->middleware('live', ['roomBindFile']); //新增单次课节
                Route::post('batch', 'batch')->append(['_code' => '010100100200', 'append_num' => 1])->middleware('live', ['roomBindFile']); //新增批量课节
                Route::put('<id>', 'update')->append(['_code' => '010100100300'])->middleware('live', ['roomUnbindFile', 'roomBindFile']); //编辑课节
                Route::delete('<id>', 'delete')->append(['_code' => '010100100600'])->middleware('live', 'deleteRoom'); //删除课节

                Route::delete('', 'deleteSelect')->middleware('live', 'deleteRoom'); //批量删除课节
                Route::delete('resource', 'deleteLessonResource');  //批量删除课节课件

                //顺延课节
                Route::put('delay', 'delay');
                Route::get('delay/<type>-<days>-<lessons>', 'getDelayTime')->pattern(['type' => '1|2', 'days' => '\d+', 'lessons' => '[\d+,?]+']);
                Route::post('delayStauts', 'delayStauts');

                Route::put('teacher', 'batchUpdate')->append(['behavior' => 'updateTeacher']); //批量修改课节老师
                Route::put('ratio', 'batchUpdate')->append(['behavior' => 'updateRatio']); //批量修改课节分辨率
                Route::put('time', 'batchUpdateTime'); //批量修改上课时间
            })->prefix('lesson/');

            Route::group(function () {
                Route::get('', 'index');  //课程列表
                Route::get('statistics', 'statistics'); // 课程统计
                Route::get('template', 'template/index'); // 选择教室
                Route::get('student', 'userManage/userList')->append(['userroleid' => FrontUser::STUDENT_TYPE]); // 分配学生
                Route::get('teacher', 'userManage/userList')->append(['userroleid' => FrontUser::TEACHER_TYPE]); // 选择老师
                Route::get('resource', 'resource/index'); // 资源库选择
            })->append(['_code' => '010100010000']);

            Route::get('all', 'getAllCourse'); //所有课程下拉列表
            Route::get('<id>', 'read')->append(['_code' => '010100100000'])->completeMatch(true);  //课程详情
            Route::delete('<course_id>/resource', 'deleteResource')->append(['_code' => '010100110200']); //删除课程资料
            Route::delete('<id>', 'delete')->append(['_code' => '010100090000']); //删除课程;

            Route::post('<course_id>/resource', 'resource/uploadFile')->append(['active' => 0, '_code' => '010100110100']); //课程资料上传
        })->prefix('course/');

        //获取批量课节匹配课件
        Route::get('courseware/<lesson_num>-<dir_id>-<rule_type>', 'lesson/getCoursewareByRule')->validate(['lesson_num' => ['integer', 'gt:0'], 'dir_id' => ['integer', 'gt:0'], 'rule_type' => ['integer', 'in:1,2,3,4']]);

        // 教室模板
        Route::group('template', function () {
            Route::get('', 'index')->append(['_code' => '050200010000']); //教室模板
            Route::get('<id>', 'read')->append(['_code' => '050200010000']); // 教室模板详情
            Route::put('<id>/disable', 'changeState')->append(['state' => 1, '_code' => '050200050000']); //禁用模板
            Route::put('<id>/enable', 'changeState')->append(['state' => 0, '_code' => '050200050000']); //启用模板
            Route::post('', 'save')->append(['_code' => '050200020000']); //新增教室模板
            Route::put('<id>', 'update')->append(['_code' => '050200030000'])->completeMatch(true); //编辑教室模板
            Route::get('settingItemsInfo', 'settingItemsInfo'); // 获取教室模板相关基础配置信息
            Route::delete('<id>', 'delete')->append(['_code' => '050200060000']); //删除模板
            Route::put('<id>/up', 'up'); // 模板上移
            Route::put('<id>/down', 'down'); // 模板下移
            Route::put('<id>/top', 'top'); // 模板置顶
        })->prefix('template/');


        // 微录课
        Route::group('microcourse', function () {
            Route::get('', 'index'); //微课列表
            Route::post('', 'save')->append(['_code' => '090107000000']); //新建微课
            Route::post('package', 'save')->append(['type' => 2, '_code' => '090108000000']); //新建微课包
            Route::put('package/<thirdroomid>', 'update')->append(['type' => 2, '_code' => '090101000000']); //编辑微课包
            Route::get('<id>-<type>', 'read'); //微课详情
            Route::delete('', 'delete')->append(['_code' => '090102000000']); //删除微课
            Route::get('package', 'packageList'); //微课包下拉框
            Route::delete('package', 'delete')->append(['type' => 2, '_code' => '090102000000']); //删除微课包
            Route::put('move', 'modify')->append(['type' => 'move', '_code' => '090106000000']); //移动微课包
        })->prefix('microcourse/');

        // 消息通知
        Route::group('notice', function () {
            Route::get('config', 'getConfig')->append([
                'only' => ['course', 'homework', 'room']
            ]); // 通知设置详情
            Route::post('config', 'saveConfig'); // 通知设置
        })->prefix('notice/');

        //作业点评
        Route::group('homework/<id>/remark', function () {
            Route::get('', 'index')->append(['_code' => '090205000000']);                       //点评列表
            Route::post('', 'save')->append(['_code' => '090206000000']);                       //进行点评
            Route::put('<student_id>', 'save')->append(['_code' => '090207000000']);            //点评编辑
            Route::delete('', 'del')->append(['_code' => '090208000000']);                      //删除点评
        })->prefix('HomeworkRecord/');
        //作业
        Route::group('homework', function () {

            Route::group(function () {
                Route::get('', 'index')->append(['is_draft' => 0, '_code' => '090200000000']);          //作业列表
                Route::post('', 'add')->append(['_code' => '090201000000']);                            //布置作业
                Route::post('<id>/remind', 'remind')->append(['_code' => '090204000000']);              //提醒
                Route::get('<id>', 'info')->append(['_code' => '090205000000']);                        //布置详情
                Route::put('<id>', 'save')->append(['_code' => '090202000000']);                        //修改作业
                Route::delete('<id>', 'del')->append(['_code' => '090203000000']);                      //删除作业
            })->prefix('Homework/');

            Route::group('drafts', function () {
                Route::get('', 'index')->append(['is_draft' => 1, '_code' => '090209000000']);    //作业草稿
                Route::put('<id>', 'save')->append(['_code' => '090210000000']);                  //草稿修改
                Route::delete('<id>', 'del')->append(['_code' => '090211000000']);                //草稿删除
            })->prefix('Homework/');

            Route::group(function () {
                Route::get('usefulExpressions', 'index')->append(['is_self' => 0]); //评语
                Route::delete('usefulExpressions/<id>', 'del')->append(['is_self' => 1]); //删除评语
            })->prefix('UsefulExpression/');
        });


        Route::group('sysconfig', function () {

            Route::group('lessonRemark', function () {
                Route::get('', 'index');                         //课堂点评列表
                Route::post('', 'save');                         //课堂点评列表
            })->prefix('RemarkItem/');

            Route::group('usefulExpressions', function () {
                Route::get('', 'index')->append([
                    'is_self' => 2
                ]);                                                        //获取企业评语
                Route::post('', 'add');                        //添加企业评语
                Route::delete('<id>', 'del')->append([
                    'is_self' => 2
                ]);                                                        //删除企业评语
                Route::put('<id>', 'save');                    //编辑企业评语
                Route::put('sort', 'sort');                    //排序
            })->prefix('UsefulExpression/');


            Route::group(function () {

                Route::get('enterClassroom', 'getConfig')->append(['only' => ['teacher_enter_in_advance', 'student_enter_in_advance']]);            //查看
                Route::put('enterClassroom', 'saveConfig');   //提前进入教室设置
                Route::get('prepareLessons', 'getConfig')->append(['only' => ['prepare_lessons']]); //学生预习课件详情
                Route::put('prepareLessons', 'saveConfig'); //学生预习课件设置
                Route::get('previewLessons', 'getConfig')->append(['only' => ['preview_lessons']]); //学生预习课件详情
                Route::put('previewLessons', 'saveConfig'); //学生预习课件设置
                Route::get('homeworkRemark', 'getConfig')->append(['only' => ['homework_remark']]); //作业点评设置详情
                Route::put('homeworkRemark', 'saveConfig'); //作业点评设置详情
                Route::get('homeworkRemind', 'getConfig')->append(['only' => ['homework_remind']]); //作业提醒次数设置
                Route::put('homeworkRemind', 'saveConfig'); //作业提醒次数设置
                Route::get('repeatLesson', 'getConfig')->append(['only' => ['repeat_lesson']]); //重复排课验证详情
                Route::put('repeatLesson', 'saveConfig'); //重复排课验证设置

            })->prefix('notice/');

            //录制件回放周期存放配置
            Route::group('record', function () {
                Route::put('', 'setRecord'); // 设置周期
                Route::get('', 'getRecord'); // 周期详情
            })->prefix('company/');
        });

        //课程总览
        Route::group('overview', function () {
            Route::get('lessoning', 'index')->append(['type' => 1]);
            Route::get('lesson/<start_date>-<end_date>', 'index')
                ->append(['type' => 2])
                ->completeMatch(true)
                ->pattern(['start_date' => '\d{4}-\d{2}-\d{2}', 'end_date' => '\d{4}-\d{2}-\d{2}']);
            Route::get('lesson/export', 'export')->append(['_nolog' => 1]); //导出课节排课学生
        })->prefix('lesson/');

        Route::group('lesson', function () {
            Route::get('issue', 'issue');
            Route::get('month', 'getAllMonths');
            Route::delete('issue/all', 'delIssueAll');
            Route::delete('issue', 'delIssue');
            Route::get('<id>/url', 'url');
        })->prefix('lesson/');


        // 分享模板
        Route::group('shareTemplate', function () {
            // 大直播
            Route::group('big', function () {
                Route::post('', 'save'); //新建大直播分享模板
                Route::put('<id>', 'update'); //编辑大直播分享模板
                Route::get('', 'index'); //大直播分享模板列表
                Route::get('<id>', 'read'); //大直播分享模板详情
            })->append(['type' => Room::ROOMTYPE_LARGEROOM]);
            // 微录课
            Route::group('micro', function () {
                Route::post('', 'save'); //新建微录课分享模板
                Route::put('<id>', 'update'); //编辑微录课分享模板
                Route::get('', 'index'); //微录课分享模板列表
                Route::get('<id>', 'read'); //微录课分享模板详情
            })->append(['type' => \app\admin\model\MicroCourse::ROOMTYPE_MICRO]);
            //删除模板
            Route::delete('<id>', 'delete'); //删除分享模板
        })->prefix('shareTemplate/');

        Route::group('freetime', function () {
            Route::post('teachers', 'teacherList');
            Route::get('<teacher_id>', 'freetimeList')->pattern(['teacher_id' => '\d+'])->completeMatch(true);
            Route::get('lesson', 'read');
        })->prefix('freetime/');


        //课节课件
        Route::get('lesson/<room_id>/files', 'lesson/files');


        //学生管理
        Route::group('student', function () {
            Route::get('<student_id>/lesson', 'lesson');    //学生教室
            Route::get('<student_id>/course/<course_id>', 'courseLesson');
            Route::get('<student_id>/course', 'course');    //学生教室
        })->prefix('student/');

        //回放录制件
        Route::group('record', function () {
            Route::get('', 'recordList'); //回放列表
            Route::delete('', 'deleleRecord'); //回放列表
        })->prefix('course/');

        //文件导出
        Route::group('export', function () {
            Route::get('', 'index')->append(['_code' => '101000000000']); //文件列表

        })->prefix('export/');

        //考勤
        Route::group('attendance', function () {
            Route::get('lesson', 'lesson'); // 课节考勤
            Route::get('teacher', 'index')->append(['type' => 1]); // 老师考勤
            Route::get('student', 'index')->append(['type' => 2]); // 学生考勤
            Route::get('<user_id>', 'info'); // 考勤明细
        })->prefix('attendance/');

        Route::get('teacher/all', 'teacherAll')
            ->prefix('userManage/'); //前台老师列表
    })->middleware(app\admin\middleware\Auth::class);

    Route::group('wechat', function () {
        Route::get('qrcode', 'qrCode')->completeMatch(true)->append(['_nolog' => 1]);
        Route::get('qrcode/<userid>', 'userQrCode');
    })->prefix('weChat/');

    Route::group('finance', function () {
        Route::get('companyConsume/<year>', 'getCompanyConsumeByYear');
        Route::get('companyBalance', 'getCompanyBalance');
        Route::post('confirmRechargeInfo', 'confirmRechargeInfo');
        Route::post('pay', 'pay')/* ->append(['_code' => '040100010000']) */; //在线充值
        Route::get('getPayResult/<order_no>', 'getPayResult');
    })->prefix('finance/');

    //财务
    Route::group('finance', function () {
        Route::get('overview', 'overview'); //->append(['_code' => '040100000000']); //财务总览
        Route::post('recharge', 'recharge'); //->append(['_code' => '040100020000']); //子企业充值
        Route::get('recharge', 'rechargeRecord'); //->append(['_code' => '040100050000']); //充值记录
        Route::get('chargeStandard', 'chargeStandard'); //->append(['_code' => '040100040000']); //计费标准
        Route::get('cost/<month>', 'monthbill')->pattern(['month' => '\d{4}-\d{2}']); //->append(['_code' => '040300010000']); //费用账单
        Route::get('roomBillDetail/<roomtype>/<month>', 'roomBillDetail')->pattern(['roomtype' => '0|3|4', 'month' => '\d{4}-\d{2}']); //->append(['_code' => '040300030000']); //课时费用账单明细
        Route::get('storageBillDetail/<month>', 'storageBillDetail')->pattern(['month' => '\d{4}-\d{2}']); //->append(['_code' => '040300030000']); //存储费用账单//明细
        Route::get('recordBillDetail/<month>', 'recordBillDetail')->pattern(['month' => '\d{4}-\d{2}']); //->append(['_code' => '040300030000']); //转码费用账单明细
        Route::get('microBillDetail/<month>', 'microBillDetail')->pattern(['month' => '\d{4}-\d{2}']); //->append(['_code' => '040300030000']); //转码费用账单明细
    })->prefix('finance/');

    Route::group('userManage', function () {
        Route::get('auth', 'auth')->append(['_nolog' => 1]); // 用户权限
        Route::put('pwd/<id>', 'updatePwd'); //修改密码
    })->prefix('userManage/');

    //教室地址
    Route::get('enterRoom/<room_id>-<type>-<username>', 'lesson/enterRoom')->pattern(['username' => '[\w\-]+']);
})->middleware('check');

Route::any('finance/alipayNotify', 'finance/alipayNotify');

Route::any('wechat/server', 'weChat/server');
Route::put('microcourse/<thirdroomid>', 'microcourse/update'); //编辑微课不走鉴权

Route::any('pay/callback/<way>', 'pay/callback'); //支付平台回调
Route::post('pay/pay', 'pay/pay'); //创建订单
Route::get('pay/<order_number>/status', 'pay/read'); //获取订单状态
Route::delete('pay/<order_number>/cancel', 'pay/cancel');//取消订单
