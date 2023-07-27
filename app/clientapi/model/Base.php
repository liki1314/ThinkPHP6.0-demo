<?php
declare (strict_types=1);

namespace app\clientapi\model;

use app\BaseModel;
use think\model\concern\SoftDelete;

/**
 * @mixin think\Model
 */
abstract class Base extends BaseModel
{
    use SoftDelete;
    protected $defaultSoftDelete = 0;

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        if (isset(request()->company['companyid'])) {
            $model->set('companyid', request()->company['companyid']);
            $model->set('company_id', request()->company['companyid']);
        }

    }

}
