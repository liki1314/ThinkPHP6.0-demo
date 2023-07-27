<?php
declare (strict_types = 1);

namespace app\wssx\model;

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

    /** 进入教室身份与userroleid映射关系 */
    const IDENTITY_MAP = [
        self::STUDENT_TYPE => 2,
        self::TEACHER_TYPE => 0,
    ];

}
