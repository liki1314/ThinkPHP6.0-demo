<?php
return [
    // 最小创建用户数
    'min_user_num' => env('sys_config.min_user_num', 10),
    // 最大创建用户数
    'max_user_num' => env('sys_config.max_user_num', 99999),
    // 最小创建角色数
    'min_role_num' => env('sys_config.min_role_num', 3),
    // 最大创建角色数
    'max_role_num' => env('sys_config.max_role_num', 99),
    // 最小创建部门层级数
    'min_dep_level' => env('sys_config.min_dep_level', 3),
    // 最大创建部门层级数
    'max_dep_level' => env('sys_config.max_dep_level', 9),
    // 菜单表后缀
    'auth_rule_suffix' => env('auth_rule_suffix', ''),
    // 重复排课验证
    'repeat_lesson' => env('sys_config.repeat_lesson', '1'),
];
