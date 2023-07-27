<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-04-07
 * Time: 10:31
 */

namespace app\admin\controller;


use app\admin\model\UsefulExpression as UsefulExpressionModel;

/**
 * 评语
 * Class UsefulExpression
 * @package app\admin\controller
 */
class UsefulExpression extends Base
{
    /**
     * 评语
     * @return \think\response\Json
     */
    public function index()
    {
        $UsefulExpression = new UsefulExpressionModel();
        $UsefulExpression->isPage = false;
        return $this->success($this->searchList($UsefulExpression));
    }

    /**
     * 删除评语
     * @param $id
     * @return \think\response\Json
     */
    public function del($id)
    {
        UsefulExpressionModel::where('id', $id)
            ->when($this->request->route('is_self') == UsefulExpressionModel::ACCOUNT, function ($query) {
                $query->where('useful_id', $this->request->user['user_account_id'])->where('type', UsefulExpressionModel::ACCOUNT);
            })
            ->when($this->request->route('is_self') == UsefulExpressionModel::COMPANY, function ($query) {
                $query->where('useful_id', $this->request->user['company_id'])->where('type', UsefulExpressionModel::COMPANY);
            })
            ->delete();

        return $this->success();
    }

    /**
     * 添加点评语
     * @return \think\response\Json
     */
    public function add()
    {
        $this->validate(
            $this->param,
            [
                'content' => ['require', 'max:2000']
            ],
            [
                'content.require' => 'expression_content_require',
                'content.max' => 'expression_content_max',
            ]
        );

        (new UsefulExpressionModel)->save([
            'expression' => $this->param['content'],
            'type' => UsefulExpressionModel::COMPANY,
        ]);

        return $this->success();
    }

    /**
     * 进行修改
     * @param $id
     * @return \think\response\Json
     */
    public function save($id)
    {
        $this->validate(
            $this->param,
            [
                'content' => ['require', 'max:2000']
            ],
            [
                'content.require' => 'expression_content_require',
                'content.max' => 'expression_content_max',
            ]
        );
        (new UsefulExpressionModel)->where('id', $id)->where('useful_id', $this->request->user['company_id'])
            ->findOrFail()
            ->save(['expression' => $this->param['content']]);
        return $this->success();
    }

    /**
     * 进行排序
     * @return \think\response\Json
     */
    public function sort()
    {
        $this->validate(
            $this->param,
            [
                'ids' => ['require', 'array', 'num_type' => function ($value) {
                    foreach ($value as $v) {
                        if (!is_numeric($v) || empty($v)) return false;
                    }
                    return true;
                }, 'num_count' => function ($value) {
                    return UsefulExpressionModel::whereIn('id', $value)->where('useful_id', $this->request->user['company_id'])->count() === count($value);
                }]
            ],
            [
                'ids.require' => "expression_ids_require",
                'ids.num_type' => "expression_ids_num_type",
                'ids.num_count' => "expression_ids_num_count",
            ]
        );
        UsefulExpressionModel::sort($this->param['ids']);
        return $this->success();
    }
}
