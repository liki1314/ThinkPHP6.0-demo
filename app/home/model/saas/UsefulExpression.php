<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-31
 * Time: 10:38
 */

namespace app\home\model\saas;

class UsefulExpression extends Base
{
    protected $deleteTime = false;

    const COMPANY = 2;
    const ACCOUNT = 1;


    public static function onBeforeInsert($model)
    {
        $model->set('useful_id', request()->user['user_account_id']);

        if (
            $model->where('useful_id', $model['useful_id'])
            ->where('type', self::ACCOUNT)
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
            ->order('sort', 'desc');
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
}
