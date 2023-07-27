<?php

return [
    // 应用地址
    'app_host' => env('app.host', ''),
    // 应用的命名空间
    'app_namespace' => '',
    // 是否启用路由
    'with_route' => true,
    // 默认应用
    'default_app' => 'admin',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map' => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind' => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list' => ['common'],

    // 异常页面的模板文件
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg' => false,
    // 记录阿里云日志
    'record_aliyun_log' => env('log.record_aliyun', false),

    // 提前多少秒可进入教室
    'before_enter' => env('room.before_enter', 900),
    // 通知配置
    'notice' => [
        'teacher_enter_in_advance' => env('room.teacher_enter_in_advance', 0), //
        'student_enter_in_advance' => env('room.student_enter_in_advance', 900), //
        //学生预习课件
        'prepare_lessons' => [
            'switch' => env('room.prepare_lessons_switch', '0'),
            'times' => env('room.prepare_lessons_in_advance', 0),
        ],
        'course' => [
            'class_end' => ['switch' => env('notice_config.course_class_end_switch', '1'), 'lesson_num' => env('notice_config.course_class_end_lesson_num', '1')],
            'class_report' => ['switch' => env('notice_config.course_class_report_switch', '1')],
            'go_to_class' => ['switch' => env('notice_config.course_go_to_class_switch', '1'), 'hours' => env('notice_config.course_go_to_class_hours', '2')],
        ],
        'homework' => [
            'assign' => ['switch' => env('notice_config.homework_assign_switch', '1')],
            'remark' => ['switch' => env('notice_config.homework_remark_switch', '1')],
            'remind' => ['switch' => env('notice_config.homework_remind_switch', '1')],
            'submit' => ['switch' => env('notice_config.homework_submit_switch', '1')],
        ],
        'room' => [
            'late' => ['switch' => 1, 'mins' => 5],
            'leave_early' => ['switch' => 1, 'mins' => 5]
        ],
        'preview_lessons' => [
            'switch' => env('room.preview_lessons_switch', "0")
        ],
        'homework_remind' => [
            'time' => env('room.homework_remind_time', 5) //企业作业提醒最大次数
        ],
        'homework_remark' => [
            'switch' => env('room.homework_remark', 1) //企业点评,默认开启
        ],
        'repeat_lesson' => [
            'switch' => env('room.repeat_lesson', 1) //重复排课验证,默认开启
        ],
    ],

    'httpclient' => [
        'handler' => '',
        // 超时设置
        'connect_timeout' => env('httpclient.timeout', 3),
    ],

    //global相关配置
    'global' => [
        'secret_key' => env('global.secret_key', ''),
    ],
    //默认充值金额
    'upgradecompany_amount' => env('pay.min_recharge', 0.01),

    //服务主机
    'host' => [
        'webapi' => env('host.live_talk', ''), //global
        'commonapi' => env('host.live_talk', ''), //global
        'local' => env('host.local', ''), //console
    ],

    // 主企业authkey
    'master_company_authkey' => env('master_company_authkey', ''),
    // 超级短信验证码（用于测试环境）
    'super_smscode' => env('super_smscode', ''),
    // office在线预览
    'office_preview' => env('host.office_preview', ''),

    'config' => [
        'remove_account' => env('config.remove_account', 1), //是否可以注销账号
        'phone' => env('config.phone', ''), //联系电话
    ],
    'member_period' => env('wssx.member_period', 3), //会员试用期（天）

    // 企业默认配置
    'company_default_config' => [
        //
        'scheduling' => [
            'freetime_switch' => env('company_default_config.freetime_switch', 0),
            'freetime_duration' => env('company_default_config.freetime_duration', 0),
            'lesson_duration' => env('company_default_config.lesson_duration', 0),
        ],
        //时间配置
        'time_format' => [
            'h24' => 1
        ],
        //导航配置
        'navigation' => [
            'zytb' => '', //
            'xlyj' => '', //
        ],
    ],
];
