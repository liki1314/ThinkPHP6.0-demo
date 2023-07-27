<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-31
 * Time: 10:38
 */

namespace app\admin\model;

use think\facade\Db;

class UsefulExpression extends Base
{
    protected $deleteTime = false;

    const COMPANY = 2;
    const ACCOUNT = 1;


    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);
        $model->set('useful_id', $model['type'] == self::ACCOUNT ? request()->user['user_account_id'] : request()->user['company_id']);

        if (
            $model->where('useful_id', $model['useful_id'])
            ->where('type', $model['type'])
            ->where('expression', $model['expression'])
            ->value('id')
        ) {
            return false;
        }
    }

    /**
     * 默认搜索器
     * @param $query
     */
    public function searchDefaultAttr($query)
    {
        $query->field(['id', 'expression'])
            ->order([
                'type' => 'asc',
                'sort' => 'desc'
            ]);
    }

    /**
     * 是否是自己的 1 自己的， 2公司的  0 全部
     * @param $query
     * @param $value
     */
    public function searchIsSelfAttr($query, $value)
    {
        if ($value == 1) {
            $query->where('useful_id', request()->user['user_account_id']);
        } elseif ($value == 2) {
            $query->where('useful_id', request()->user['company_id']);
        } else {
            $query->whereIn('useful_id', [request()->user['user_account_id'], request()->user['company_id']]);
        }
    }

    /**
     * 通过id在数组里面索引位置进行排序
     * @param $ids array
     */
    public static function sort($ids)
    {
        /**
         * UPDATE saas_useful_expression SET sort=(CASE `id` WHEN 19 THEN 999 WHEN 20 THEN 1000 WHEN 21 THEN 1024 END) WHERE id IN (19,20,21);
         */
        $sqlSprintf = "UPDATE saas_useful_expression SET sort=(CASE `id` %s  END) WHERE id IN (%s)";
        $count = count($ids);
        $idsWhere = [];
        $sqlUpdate = '';

        foreach ($ids as $k => $v) {
            $idsWhere[] = $v;
            $sqlUpdate .= sprintf("WHEN %d THEN %d ", $v, $count - $k);
            if (count($idsWhere) >= 100) {
                Db::execute(sprintf($sqlSprintf, $sqlUpdate, implode(',', $idsWhere)));
                $idsWhere = [];
                $sqlUpdate = '';
            }
        }

        if (!empty($idsWhere)) {
            Db::execute(sprintf($sqlSprintf, $sqlUpdate, implode(',', $idsWhere)));
        }
    }
}
