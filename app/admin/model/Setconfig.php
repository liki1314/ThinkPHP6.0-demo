<?php
declare (strict_types = 1);

namespace app\admin\model;

/**
 * @mixin \think\Model
 */
class Setconfig extends Base
{
    /** 创建最大用户数 */
    const MAX_USER = 1;

    /** 创建最大角色数 */
    const MAX_ROLE = 2;

    /** 创建最大部门层数 */
    const MAX_DEP_LEVEL = 3;
}
