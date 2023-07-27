<?php

declare(strict_types=1);

namespace app\webapi\model;

use app\BaseModel;
use think\model\concern\SoftDelete;
use app\Request;

/**
 * @mixin think\Model
 */
abstract class Base extends BaseModel
{
    use SoftDelete;
    protected $defaultSoftDelete = 0;

    protected $globalScope = ['companyId'];

    public function scopeCompanyId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (isset($request->company) && in_array('company_id', $query->getTableFields())) {
                $query->where('__TABLE__.company_id', $request->company['id']);
            }
        });
    }

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->invoke(function (Request $request) use ($model) {
            if (!empty($request->company)) {
                $model->set('company_id', $request->company['id']);
            }
        });
    }
}
