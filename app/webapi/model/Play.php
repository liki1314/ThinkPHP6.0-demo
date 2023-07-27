<?php
declare (strict_types = 1);

namespace app\webapi\model;

class Play extends Base
{
    protected $globalScope = ['companyId'];

    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        validate(
            [
                'title' => 'unique:' . get_class($model) . ',title'
            ],
            [
                'title' => lang('title_exists'),
            ]
        )->check($model->toArray());

    }


    public function videos()
    {
        return $this->hasMany(PlayVideo::class);
    }


    public function searchDefaultAttr($query, $value)
    {
        return $query->field('title,tag,id,update_time,create_time,desc')->append(['video_count']);
    }


}
