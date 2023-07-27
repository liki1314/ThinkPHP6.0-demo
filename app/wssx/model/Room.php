<?php

declare(strict_types=1);

namespace app\wssx\model;

use app\Request;
use think\Model;

/**
 * @mixin \think\Model
 */
class Room extends Base
{

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->set('custom_id', uniqid('', true));
        $model->set('roomname', request()->user['account'] . '的房间');
        $model->set('starttime', time());
        $model->set('endtime', strtotime('2099-01-01'));
        $model->set('roomtype', 1);
    }

    public static function onAfterRead(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            if (!isset($request->user['company_id'])) {
                $request->user = array_merge(
                    $request->user ?? [],
                    [
                        'company_id' => $model['company_id']
                    ]
                );
            }
        });
    }

    public function scopeAccountId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (isset($request->user['user_account_id']) && in_array('create_by', $query->getTableFields())) {
                $query->where('__TABLE__.create_by', $request->user['user_account_id']);
            }
        });
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->alias('a')
            ->field('a.id,a.roomname name,b.username master')
            ->join(['saas_user_account' => 'b'], '__TABLE__.create_by=b.id')
            ->where('__TABLE__.create_by',request()->user['user_account_id'])
            ->order('__TABLE__.create_time', 'desc');
    }
}
