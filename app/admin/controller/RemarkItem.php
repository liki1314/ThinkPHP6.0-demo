<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-04-07
 * Time: 09:27
 */

namespace app\admin\controller;


use app\admin\model\RemarkItem as RemarkItemModel;

/**
 * 课堂点评
 * Class RemarkItem
 * @package app\admin\controller
 */
class RemarkItem extends Base
{
    /**
     * 课堂点评列表
     * @return \think\response\Json
     */
    public function index()
    {
        $remarkItemModel = new RemarkItemModel();
        $remarkItemModel->isPage = false;
        $data = [];
        $this->searchList($remarkItemModel)->each(function ($value) use (&$data) {
            $data = $value->content;
        });
        return $this->success($data);
    }

    /**
     * 课堂点评设置
     * @return \think\response\Json
     */
    public function save()
    {
        $this->validate(
            $this->param,
            [
                'remarks' => ['require', 'array', function ($value) {
                    foreach ($value as $v) {
                        if (!is_string($v) || empty($v)) return false;
                    }
                    return true;
                }]
            ],
            [
                'remarks' => 'remarks_require'
            ]
        );

        (new RemarkItemModel())->where('company_id', $this->request->user['company_id'])->findOrEmpty()
            ->save(['content' => $this->param['remarks']]);

        return $this->success();
    }
}
