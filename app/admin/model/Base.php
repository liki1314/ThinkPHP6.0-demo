<?php

declare(strict_types=1);

namespace app\admin\model;

use app\BaseModel;
use app\Request;
use think\model\concern\SoftDelete;

/**
 * @mixin \think\Model
 */
abstract class Base extends BaseModel
{
    use SoftDelete;
    protected $defaultSoftDelete = 0;

    protected $globalScope = ['companyId'];

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->invoke(function (Request $request) use ($model) {
            $model->set('company_id', $request->user['company_id']);
        });
    }

    public function scopeCompanyId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (isset($request->user['company_id']) && in_array('company_id', $query->getTableFields())) {
                $query->where('__TABLE__.company_id', $request->user['company_id']);
            }
        });
    }
}
