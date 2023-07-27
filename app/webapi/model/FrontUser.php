<?php

declare(strict_types=1);

namespace app\webapi\model;
use think\Model;

/**
 * @mixin \think\Model
 */
class FrontUser extends Model
{
    /** 教师 */
    const TEACHER_TYPE = 7;
    /** 学生 */
    const STUDENT_TYPE = 8;

    /** 默认头像路径 */
    const DEFAULT_AVATAR = [
        self::TEACHER_TYPE => [
            0 => '/image/woman_teacher.png',
            1 => '/image/man_teacher.png'
        ],
        self::STUDENT_TYPE => [
            0 => '/image/woman_student.png',
            1 => '/image/man_student.png'
        ]
    ];

    const DISABLE = 2;
    const ENABLE = 0;

    /** 进入教室身份与userroleid映射关系 */
    const IDENTITY_MAP = [
        self::STUDENT_TYPE => 2,
        self::TEACHER_TYPE => 0,
    ];




}
