<?php

declare(strict_types=1);

namespace app\common\model;

use app\BaseModel;
use think\facade\Cache;
use think\Model;

/**
 * @mixin \think\Model
 */
class Company extends BaseModel
{
    const STATE = [
        ['value' => 0, 'text' => '试用'],
        ['value' => 1, 'text' => '正式'],
        ['value' => 2, 'text' => '正常到期'],
        ['value' => 3, 'text' => '试用到期'],
        ['value' => 4, 'text' => '手动冻结'],
        ['value' => 5, 'text' => '欠费冻结'],
    ];

    /** 正常到期状态 */
    const NORMAL_EXPIRE_STATE = 2;

    protected $json = ['notice_config', 'extra_info'];

    public static function onBeforeInsert(Model $model)
    {
        parent::onBeforeInsert($model);

        $model->set('createtime', date('Y-m-d H:i:s'));
    }

    public function searchAuthkeyAttr($query, $value)
    {
        $query->where('authkey', $value);
    }

    public function searchNormalStateAttr($query, $value)
    {
        $query->where('companystate', '<', self::NORMAL_EXPIRE_STATE);
    }

    public static function onAfterUpdate($model)
    {
        Cache::delete('company:authkey:' . $model['authkey']);
        Cache::delete('company:pk:' . $model->getKey());
        Cache::delete(sprintf("think:%s.%s|%s", self::getConfig('database'), self::getTable(), $model->getKey()));
    }

    public static function getDetailById($id)
    {
        return Cache::remember('company:pk:' . $id, function () use ($id) {
            return self::findOrEmpty($id)->toArray();
        });
    }
}
