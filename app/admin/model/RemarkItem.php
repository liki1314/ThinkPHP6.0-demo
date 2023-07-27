<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-04-07
 * Time: 09:23
 */

namespace app\admin\model;


class RemarkItem extends Base
{
    protected $deleteTime = false;

    protected $pk = 'company_id';

    protected $json = ['content'];

    /**
     * 默认搜索器
     * @param $query
     */
    public function searchDefaultAttr($query)
    {
        $query->where(function ($query) {
            $query->whereOr('company_id', 0)->whereOr('company_id', request()->user['company_id']);
        })->order('company_id');
    }

}
