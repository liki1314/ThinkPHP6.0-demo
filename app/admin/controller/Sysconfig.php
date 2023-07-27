<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Company;
use app\admin\model\Setconfig;
use app\admin\validate\CompanyConfig;
use think\facade\Db;

class Sysconfig extends Base
{
    public function index()
    {
        return $this->success(Setconfig::field('id,type,max_num,price')->select());
    }

    public function save()
    {
        $this->validate(
            $this->param,
            [
                'config' => [
                    'array',
                    'each' => [
                        'type' => 'in:' . Setconfig::MAX_USER . ',' . Setconfig::MAX_ROLE . ',' . Setconfig::MAX_DEP_LEVEL,
                        'max_num' => [
                            'integer',
                            function ($value, $data) {
                                switch ($data['type']) {
                                    case Setconfig::MAX_USER:
                                        return $value >= $this->app->config->get('app.min_user_num') && $value <= $this->app->config->get('app.max_user_num') ?: lang('user_max');
                                    case Setconfig::MAX_ROLE:
                                        return $value >= $this->app->config->get('app.min_role_num') && $value <= $this->app->config->get('app.max_role_num') ?: lang('role_max');
                                    case Setconfig::MAX_DEP_LEVEL:
                                        return $value >= $this->app->config->get('app.min_dep_level') && $value <= $this->app->config->get('app.max_dep_level') ?: lang('dep_max');
                                    default:
                                        break;
                                }
                                return true;
                            }
                        ],
                        'price' => function ($value) {
                            return is_numeric($value);
                        },
                    ]
                ]
            ]
        );

        Setconfig::duplicate(['max_num' => Db::raw('VALUES(max_num)')])->insertAll($this->param['config']);

        return $this->success();
    }

    public function getConfig()
    {
        $model = Company::findOrFail($this->request->user['company_id']);

        return $this->success(array_merge(config('app.company_default_config')[$this->request->route('configname')], $model['notice_config'][$this->request->route('configname')] ?? []));
    }

    public function setConfig()
    {
        $this->validate($this->param, CompanyConfig::class . '.' . $this->request->route('configname'));

        $model = Company::findOrFail($this->request->user['company_id']);
        $info = $model['notice_config'];
        $info[$this->request->route('configname')] = array_intersect_key($this->param, config('app.company_default_config')[$this->request->route('configname')]);
        $model->notice_config = $info;
        $model->save();

        return $this->success();
    }
}
