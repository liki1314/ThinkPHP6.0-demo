<?php

declare(strict_types=1);

namespace app\admin\model;

use think\exception\ValidateException;

class Department extends Base
{
    /**
     * 删除部门
     *
     * @param int $id 部门id
     * @return void
     */
    public static function del($id)
    {
        $child = self::getFieldByFid($id, 'id');
        if (!empty($child)) {
            throw new ValidateException(lang('subdepartment_exists'));
        }
        $employee = CompanyUser::getFieldByDepartmentId($id, 'id');
        if (!empty($employee)) {
            throw new ValidateException(lang('employees_exists'));
        }
        self::destroy($id);
    }
}
