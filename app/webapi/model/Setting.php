<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2021/1/11
 * Time: 17:59
 */

namespace app\webapi\model;




class Setting extends Base
{

    protected $withTrashed = true;

    protected  $table = 'ch_user_config';

    protected $globalScope = ['companyId'];



    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        validate(
            [
                'userid' => 'unique:' . get_class($model) . ',company_id^userid'
            ],
            [
                'userid' => lang('userid_exists'),
            ]
        )->check($model->toArray());

    }

}