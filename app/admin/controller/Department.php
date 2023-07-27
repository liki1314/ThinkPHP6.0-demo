<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Department as DepartmentModel;
use app\admin\validate\Department as DepartmentValidate;
use think\helper\Arr;

class Department extends Base
{
    public function tree($fid = null)
    {
        $query = DepartmentModel::order('sort');

        if (isset($fid)) {
            $query->where('fid', '=', $fid);
        }

        $models = $query->select();

        return $this->success(isset($fid) ? $models : $models->tree());
    }

    public function save()
    {
        $this->validate($this->param, DepartmentValidate::class);

        $model = DepartmentModel::create(Arr::only($this->param, ['name', 'sort', 'fid']));

        return $this->success($model);
    }

    public function read($id)
    {
        $model = DepartmentModel::findOrFail($id);
        return $this->success($model);
    }

    public function update($id)
    {
        $this->validate($this->param, DepartmentValidate::class);

        $model = DepartmentModel::findOrFail($id);
        $model->save(Arr::only($this->param, ['name', 'sort']));

        return $this->success();
    }

    public function delete($id)
    {
        DepartmentModel::del($id);

        return $this->success();
    }
}
