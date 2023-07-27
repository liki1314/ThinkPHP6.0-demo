<?php

declare(strict_types=1);

namespace app\admin\model;

use app\gateway\model\UserAccount;
use think\exception\ValidateException;
use think\helper\Arr;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;

class CompanyUser extends Base
{
    public static function onAfterWrite($model)
    {
        $roles = array_merge($model['roles'] ?? [], $model->getData()['roles'] ?? []);

        if (in_array(AuthGroup::TEACHER_ROLE, $roles)) {
            $duplicate = [
                'username' => $model['username'],
                'nickname' => $model['username'],
                'ucstate' => $model['state'] == self::DISABLE_STATE ? FrontUser::DISABLE : FrontUser::ENABLE,
                'delete_time' => isset($model->getData()['roles']) && !in_array(AuthGroup::TEACHER_ROLE, $model->getData('roles')) ? time() : 0
            ];

            //老师账号禁用删除判断是否有未结束的课节
            if ($duplicate['ucstate'] == FrontUser::DISABLE || $duplicate['delete_time'] > 0) {
                $room_id = Room::alias('a')
                    ->join('front_user b', 'b.id=a.teacher_id')
                    ->where('b.user_account_id', $model['user_account_id'])
                    ->where('b.company_id', $model['company_id'])
                    ->where('b.userroleid', FrontUser::TEACHER_TYPE)
                    ->where('endtime', '>', time())
                    ->value('a.id');
                if (!empty($room_id)) {
                    throw new ValidateException(lang('Please delete the arrangement information before disabling the operation.'));
                }
            }

            Db::name('front_user')
                ->duplicate($duplicate)
                ->insert([
                    'user_account_id' => $model['user_account_id'],
                    'company_id' => $model['company_id'],
                    'create_time' => time(),
                    'username' => $model['username'],
                    'userroleid' => FrontUser::TEACHER_TYPE,
                    'nickname' => $model['username']
                ]);
        }
    }

    /** 启用状态 */
    const ENABLE_STATE = 1;
    /** 停用状态 */
    const DISABLE_STATE = 0;

    public static function onAfterDelete($model)
    {
        if ($model['sys_role'] == AuthGroup::SUPER_ADMIN) {
            throw new ValidateException(lang('cant_delete_manager'));
        }
        // 删除用户后删除用户与角色关系
        $model->role()->detach();
        Cache::delete("companyuser:{$model['user_account_id']}:{$model['company_id']}");
    }

    public static function onAfterUpdate($model)
    {
        Cache::delete("companyuser:{$model['user_account_id']}:{$model['company_id']}");
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->bind(['company_name' => 'companyname', 'logoicon' => 'ico']);
    }

    public function user()
    {
        return $this->belongsTo(UserAccount::class)->bind(['account', 'locale', 'mobile', 'code', 'avatar']);
    }

    public function dep()
    {
        return $this->belongsTo(Department::class)
            ->bind(['depname' => 'name'])
            ->joinType('left');
    }

    public function role()
    {
        return $this->belongsToMany(AuthGroup::class, 'company_user_role');
    }

    public function getRolesAttr()
    {
        return $this->getAttr('role')->column('id');
    }

    public function getRoleNameAttr($value, $data)
    {
        return $data['sys_role'] == AuthGroup::SUPER_ADMIN ? lang('super_role') :
            $this->getAttr('role')->reduce(function ($carry, $item) {
                $carry .= (empty($carry) ? '' : ',') . lang(AuthGroup::ROLE_MAP[$item['name']] ?? $item['name']);
                return $carry;
            });
    }

    public function getSuperUserAttr($value, $data)
    {
        return AuthGroup::SUPER_ADMIN == $data['sys_role'] ? 1 : 0;
    }

    // 启用账号
    public function searchEnableUserAttr($query)
    {
        $query->where('__TABLE__.state', self::ENABLE_STATE);
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('id,state,create_time,username,sys_role')
            ->withJoin([
                'user' => ['account', 'locale'],
                'dep' => ['dep.name' => 'depname']
            ])
            ->with(['role'])
            ->order('create_time', 'desc')
            ->append(['role_name', 'super_user', 'mobile', 'code'])
            ->hidden(['user', 'dep', 'role']);

        if (isset($data['username'])) {
            $query->whereLike('__TABLE__.username', '%' . $data['username'] . '%');
        }

        if (isset($data['depname'])) {
            $query->whereLike('dep.name', '%' . $data['depname'] . '%');
        }
    }

    public function searchAdminAttr($query)
    {
        $query->where('__TABLE__.sys_role', AuthGroup::SUPER_ADMIN);
    }

    public function addUser($data = []): int
    {
        $account = config('countrycode.abbreviation_code.' . ($data['locale'] ?? 'CN')) . $data['mobile'];
        $userAccount = UserAccount::where('account', $account)->findOrEmpty();
        if (!$userAccount->isEmpty() && $this->where('user_account_id', $userAccount['id'])->find()) {
            throw new ValidateException(lang('mobile_exists'));
        }

        $this->startTrans();
        try {
            $user = UserAccount::saveUser($data);
            $data['user_account_id'] = $user['user_account_id'];
            $model = self::create(Arr::except($data, ['sys_role']));
            $model->role()->saveAll($data['roles']);

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return intval($user['user_account_id']);
    }

    public function editUser($data = [])
    {
        $oldUserAccount = $this->user->getData('account');
        $account = config('countrycode.abbreviation_code.' . ($data['locale'] ?? 'CN')) . $data['mobile'];
        if ($oldUserAccount != $account) {
            $user = $this->user->where('account', $account)->findOrEmpty();
            if (!$user->isEmpty()) {
                throw new ValidateException(lang('mobile_exists'));
            }
        }

        $this->transaction(function () use ($data) {
            $roles = $this->getAttr('roles');
            $this->save(Arr::only($data, ['username', 'state', 'department_id', 'roles']));
            $this->user->save(Arr::only($data, ['mobile', 'locale']));
            // 先删除中间关联表数据后新增
            // $this->role()->detach();
            $this->role()->sync($data['roles']);
            // 修改用户角色删除用户缓存
            if (array_diff($data['roles'], $roles) || array_diff($roles, $data['roles'])) {
                Cache::delete("companyuser:$this->user_account_id:$this->company_id:auth");
            }
        });
    }

    public function searchAllAttr($query, $value, $data)
    {
        $query->field([$data['group_id'] == FrontUser::TEACHER_TYPE ? 'b.id' : 'a.id', 'b.username' => 'name', 'a.username'])
            ->alias('a')
            ->join('user_account b', 'a.user_account_id = b.id')
            ->join('company_user_role c', 'c.company_user_id = a.id')
            ->where('auth_group_id', $data['group_id']);
    }

    /**
     * 获取企业账号信息
     *
     * @param int $userAccountId 账号id
     * @param int $companyId 企业id
     * @return array
     */
    public static function getCompanyUserInfo($userAccountId, $companyId)
    {
        return Cache::remember("companyuser:$userAccountId:$companyId", function () use ($userAccountId, $companyId) {
            return self::withoutGlobalScope(['companyId'])
                ->alias('a')
                ->join('user_account aa', 'a.user_account_id=aa.id')
                ->whereExists(function ($query) use ($companyId) {
                    // 主企业超管账号可以以超管身份进入子企业
                    $query->name('company')
                        ->alias('b')
                        ->where('b.id', $companyId)
                        ->where(function ($query) {
                            $query->whereOr([
                                ['a.company_id', 'exp', Db::raw('=b.id')],
                                [
                                    ['a.company_id', 'exp', Db::raw('=b.parentid')],
                                    ['a.sys_role', '=', AuthGroup::SUPER_ADMIN],
                                    ['b.type', '<>', Company::AGENT_TYPE]
                                ]
                            ]);
                        });
                })
                ->where('a.user_account_id', $userAccountId)
                ->where('a.state', self::ENABLE_STATE)
                ->append(['super_user', 'roles'])
                ->field('a.id,aa.live_userid userid,a.username,a.department_id,a.sys_role,a.company_id as user_company_id,' . $companyId . ' company_id')
                ->findOrEmpty()
                ->toArray();
        });
    }

    /**
     * 获取企业账号权限
     *
     * @param int $userAccountId 账号id
     * @param int $companyId 企业id
     * @return array
     */
    public static function getCompanyUserAuth($userAccountId, $companyId): array
    {
        $key = "companyuser:$userAccountId:$companyId:auth";
        return Cache::remember($key, function () use ($userAccountId, $companyId, $key) {
            $user = self::getCompanyUserInfo($userAccountId, $companyId);
            $auth = Db::name('auth_group')
                ->alias('a')
                ->join('auth_rule' . config('app.auth_rule_suffix') . ' b', '1')
                ->leftJoin('auth_group_config c', 'c.auth_group_id=a.id and c.company_id=' . $companyId)
                ->whereRaw('find_in_set(b.id,ifnull(c.rules,a.rules))')
                ->whereIn('a.id', $user['roles'])
                ->column('b.code', 'b.id');

            Cache::tag(array_map(function ($roleId) {
                return 'role:' . $roleId;
            }, $user['roles']))->append($key);
            return $auth;
        });
    }

    public function getNameAttr($value, $data)
    {
        return $data['username'] ?: $data['name'];
    }
}
