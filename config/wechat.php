<?php
return [
    'app_id' => env('wechat.app_id', ''),
    'secret' => env('wechat.secret', ''),
    'token' => env('wechat.token', ''),

    // 消息模板
    'template' => [
        //
        'course' => [
            //
            'go_to_class' => env('wechat.go_to_class_template_id', ''),
            //
            'class_report' => env('wechat.class_report_template_id', '-0wevA4'),
            //
            'class_end' => env('wechat.class_end_template_id', ''),
        ],
        //
        'homework' => [
            //
            'assign' => env('wechat.homework_assign_template_id', ''),
            //
            'remark' => env('wechat.homework_remark_template_id', ''),
            //
            'remind' => env('wechat.homework_remind_template_id', ''),
        ],
    ],
];
