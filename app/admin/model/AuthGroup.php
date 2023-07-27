<?php

declare(strict_types=1);

namespace app\admin\model;

use think\exception\ValidateException;
use think\facade\Cache;

class AuthGroup extends Base
{

    /** 企业超管角色ID */
    const SUPER_ADMIN = 11;
    /** 助教角色id */
    const HELPER_ROLE = 1;
    /** 巡课角色id */
    const COURSE_ROLE = 4;
    /** 老师角色id */
    const TEACHER_ROLE = 7;
    /** 标准版企业管理员ID，用于某些需要同步到标准版的信息 */
    const GLOBAL_COMPANY_ADMIN = 11;
    /** 默认角色的company_id */
    const DEFAULT_ROLE_COMPANY_ID = 0;

    const ENABLE = 1;

    const DISABLE = 0;

    const DATA_RULE = ['0' => '全部', '1' => '本人', '2' => '部门', '3' => '部门及以下部门'];

    const ROLE_MAP = [
        '助教' => 'helper',
        '巡课' => 'Patrolclass',
        '老师' => 'teacher',
    ];

    public function scopeConfig($query)
    {
        $query->alias('a')->leftJoin('auth_group_config z', 'z.auth_group_id=a.id and z.company_id='.request()->user['company_id'])->fieldRaw('a.*,ifnull(z.rules,a.rules) rules');
    }

    public static function onAfterUpdate($model)
    {
        /* $change = $model->getChangedData();
        // 修改角色权限，清空包含此角色的账号权限缓存
        if (isset($change['rules'])) {
            Cache::tag('role:' . $model->getKey())->clear();
        } */
    }

    public function companyUser()
    {
        return $this->belongsToMany(CompanyUser::class, 'company_user_role');
    }

    public static function onBeforeDelete($model)
    {
        if ($model->getData('company_id') == self::DEFAULT_ROLE_COMPANY_ID) {
            throw new ValidateException(lang('default_role_error'));
        };

        if ($model->companyUser()->count() > 0) {
            throw new ValidateException(lang('角色下存在用户无法删除'));
        }
    }

    public function setRulesAttr($value)
    {
        if (!empty($value)) {
            $models = AuthRule::suffix(config('app.auth_rule_suffix'))->select();
            $id = [];
            foreach ($value as $val) {
                $id = array_merge($id, $models->parent($val)->column('id'));
            }
            $value = array_unique($id);
        }

        return implode(',', $value);
    }

    public function scopeCompanyId($query)
    {
        if (isset(request()->user['company_id'])) {
            $query->whereIn('__TABLE__.company_id', '0,' . request()->user['company_id']);
        }
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        if (isset($data['enable'])) {
            $query->where('enable', $data['enable']);
        }

        if (!empty($data['no_page'])) {
            $this->isPage = false;
            $query->field(['id','name','__TABLE__.company_id'])->where('enable', self::ENABLE)->where('id', '<>', self::SUPER_ADMIN);
        }

        $query->append(['is_default']);
    }

    public function searchUserbyRoleAttr($query, $value, $data)
    {
        if (isset($data['role_id'])) {
            //角色ids 关联用户中间表
            $query->where('id', $data['role_id'])->with('companyUser');
        }
    }

    public function searchSearchRoleAttr($query, $value)
    {
        $query->whereLike('name', "%$value%");
    }

    public function getRulesAttr($value)
    {
        return $value ? explode(',', $value) : [];
    }

    public function getIsDefaultAttr($value, $data)
    {
        if ($data['company_id'] == self::DEFAULT_ROLE_COMPANY_ID) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getNameAttr($value)
    {
        return isset(self::ROLE_MAP[$value]) ? lang(self::ROLE_MAP[$value]) : $value;
    }
}
