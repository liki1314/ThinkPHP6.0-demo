<?php

use app\admin\model\AuthGroup;
use app\admin\model\CourseLesson;
use think\exception\ValidateException;


return [
    'listen' => [
        // 绑定超级管理员角色的帐号不能停用、不能删除
        'app\admin\model\CompanyUser.BeforeDelete' => [
            function ($models) {
                if ($models instanceof \think\model\Collection) {
                    $models->each(function ($model) {
                        if (AuthGroup::SUPER_ADMIN == $model['sys_role']) {
                            throw new ValidateException(lang('cannot_del_superadmin'));
                        }
                    });
                } elseif (AuthGroup::SUPER_ADMIN == $models['sys_role']) {
                    throw new ValidateException(lang('cannot_del_superadmin'));
                }
            }
        ],
        // 更新课程状态
        'SaveRoom' => [
            function ($model) {
                //更新课程开始和结束时间
            }
        ],
    ],
];
