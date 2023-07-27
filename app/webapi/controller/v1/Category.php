<?php

declare(strict_types=1);

namespace app\webapi\controller\v1;
use app\webapi\controller\Base;
use app\webapi\model\Category as cateModel;
use app\common\http\HwCloud;
use app\webapi\validate\Category as ValidateCategory;

class Category extends Base
{
    public function index()
    {
        //
    }

    /**
     *创建视频分类
     *
     */
    public function save()
    {

        $this->validate($this->param,ValidateCategory::class);

        $api_params = [];
        $api_params['name'] = $this->param['name'];

        if (isset($this->param['parent_id']) && $this->param['parent_id']) {
            $model = cateModel::findOrFail($this->param['parent_id']);
            $api_params['parent_id'] = $model->category_id;
        }


        $this->param['category_id'] = HwCloud::httpJson('asset/category', $api_params)['id'];

        $model = cateModel::create($this->param);

        return $this->success($model);

    }

    /**
     * 得到视频分类信息
     * @param $id
     */
    public function read($id)
    {
        $cateModel = new cateModel;

        $this->param['parent_id'] = $id;

        $list = $this->searchList(cateModel::class, ['parent_id']);
        $data = $cateModel->getCateList($list, $id);

        return $this->success($data);
    }

    /**
     * 修改视频分类名称
     * @param $id
     */
    public function update($id)
    {
        $this->validate($this->param,ValidateCategory::class. '.update');

        $model = new cateModel;

        $model->updateInfo($this->param['name'],$id);

        return $this->success();

    }


    /**
     * 删除视频分类
     * @param $id
     */
    public function delete($id)
    {
        $model = new cateModel;

        $model->doDelete($id);

        return $this->success();
    }
}
