<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\AuthGroup;
use app\admin\model\AuthRule;
use app\admin\validate\Role as RoleValidate;
use think\exception\ValidateException;
use think\helper\Arr;
use think\facade\Db;
use think\facade\Cache;

class Role extends Base
{
    public function index()
    {
        return $this->success($this->searchList(AuthGroup::class));
    }

    public function save()
    {
        $this->validate($this->param, RoleValidate::class);

        $model = AuthGroup::create($this->param);

        return $this->success($model);
    }

    public function read($id)
    {
        $model = AuthGroup::scope('config')->findOrFail($id)
            ->withAttr('rules', function ($value) {
                $fids = AuthRule::whereExp('', Db::raw("FIND_IN_SET(`fid`,'$value')"))->distinct(true)->column('fid');
                return array_values(array_diff(explode(',', $value), $fids));
            })
            ->withAttr('rules_name', function ($value, $data) {
                return AuthRule::whereIn('id', $data['rules'])->select()->column('name');
            })
            ->withAttr('data_rule_name', function ($value, $data) {
                return in_array($data['data_rule'], array_keys(AuthGroup::DATA_RULE)) ? AuthGroup::DATA_RULE[$data['data_rule']] : "";
            })
            ->withAttr('rules_tree', function ($value, $data) {
                return AuthRule::whereIn('id', $data['rules'])->field('id,name,fid')->select()->tree();
            })
            ->append(['rules_name', 'data_rule_name', 'rules_tree']);

        return $this->success($model);
    }

    public function update($id)
    {
        $this->validate($this->param, RoleValidate::class);
        $model = AuthGroup::findOrFail($id);

        if ($model->company_id == AuthGroup::DEFAULT_ROLE_COMPANY_ID) {
            Db::name('auth_group_config')
                ->duplicate(['rules' => $model->setRulesAttr($this->param['rules'])])
                ->insert(['auth_group_id' => $id, 'company_id' => $this->request->user['company_id'], 'rules' => $model->setRulesAttr($this->param['rules'])]);
        } else {
            $model->save(Arr::only($this->param, ['name', 'desc', 'rules', 'enable', 'data_rule']));
        }

        if (isset($this->param['rules'])) {
            Cache::tag('role:' . $model->getKey())->clear();
        }

        return $this->success();
    }

    public function batchDel($id)
    {
        AuthGroup::destroy($id);
        return $this->success();
    }

    public function enable($id)
    {
        $defaultIds = AuthGroup::where('company_id', AuthGroup::DEFAULT_ROLE_COMPANY_ID)->column('id');
        if (!empty(array_intersect($defaultIds, $id))) {
            throw  new ValidateException(lang('default_role_error'));
        }
        AuthGroup::whereIn('id', $id)->update(['enable' => AuthGroup::ENABLE]);
        return $this->success();
    }

    public function disable($id)
    {
        $defaultIds = AuthGroup::where('company_id', AuthGroup::DEFAULT_ROLE_COMPANY_ID)->column('id');
        if (!empty(array_intersect($defaultIds, $id))) {
            throw  new ValidateException(lang('default_role_error'));
        }
        AuthGroup::whereIn('id', $id)->update(['enable' => AuthGroup::DISABLE]);
        return $this->success();
    }
}
