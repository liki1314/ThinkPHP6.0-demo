<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\validate\ShareTemplate as ShareTemplateValidate;
use app\admin\model\ShareTemplate as ShareTemplateModel;
use think\facade\Db;

/**
 * 分享模版
 * Class ShareTemplate
 * @package app\admin\controller
 */
class ShareTemplate extends Base
{
    /**
     * 模板列表
     * @return \think\response\Json
     */
    public function index()
    {
        $shareTemplateModel = new ShareTemplateModel();
        $shareTemplateModel->isPage = false;
        return $this->success($this->searchList($shareTemplateModel));
    }

    /**
     * 新建模板
     * @return \think\response\Json
     */
    public function save()
    {
        $this->validate($this->param, ShareTemplateValidate::class);

        Db::transaction(function () {
            //如果是默认，就把现有默认取消掉
            if ($this->param['is_default'] == ShareTemplateModel::DEFAULT_YES) {
                (new ShareTemplateModel)->where('type', $this->param['type'])->where("is_default", ShareTemplateModel::DEFAULT_YES)
                    ->update(["is_default" => ShareTemplateModel::DEFAULT_NO]);
            }
            (new ShareTemplateModel)->save([
                'type' => $this->param['type'],
                'pic' => $this->param['pic'] ?? '',
                'content' => $this->param['content'],
                'is_default' => $this->param['is_default']
            ]);

        });

        return $this->success();
    }

    /**
     * 模板详情
     * @param $id
     * @return \think\response\Json
     */
    public function read($id)
    {
        return $this->success(ShareTemplateModel::where('type', $this->param['type'])
            ->field([
                'id',
                'pic',
                'is_default',
                'content'
            ])->findOrFail($id)->append(['cover']));
    }

    /**
     * 进行更新模板
     * @param $id
     * @return \think\response\Json
     */
    public function update($id)
    {
        $this->validate($this->param, ShareTemplateValidate::class);

        $data = [
            'content' => $this->param['content'],
            'is_default' => $this->param['is_default']
        ];

        if (isset($this->param['pic'])) $data['pic'] = $this->param['pic'];

        Db::transaction(function () use ($id, $data) {
            //如果是默认，就把现有默认取消掉
            if ($this->param['is_default'] == ShareTemplateModel::DEFAULT_YES) {
                (new ShareTemplateModel)->where('type', $this->param['type'])->where("is_default", ShareTemplateModel::DEFAULT_YES)
                    ->update(["is_default" => ShareTemplateModel::DEFAULT_NO]);
            }
            (new ShareTemplateModel)->where('id', $id)->where('type', $this->param['type'])->save($data);
        });

        return $this->success();
    }

    /**
     * 删除模板
     * @param $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        ShareTemplateModel::destroy($id);
        return $this->success();
    }
}
