<?php

declare(strict_types=1);

namespace app\webapi\validate;

use think\Validate;
use app\webapi\model\Video;
use app\webapi\model\Category;

class VideoCover extends Validate
{
    public function field()
    {
        $this->field = [
            'vids' => lang('video'),
            'cataids' => lang('category'),
            'image' => lang('video_cover_image'),
            'file_url' => lang('video_cover_url'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'vids' => ['array', 'requireWithout:cataids', 'checkVideo'],
        'cataids' => ['array', 'requireWithout:vids', 'checkCategory'],
        'image' => ['require', 'image', 'fileExt:jpg,png,jpeg', 'fileMime' => 'image/png,image/jpeg'],
        'file_url' => ['require', 'url', 'existUrl'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'image.fileExt' => 'cover_image_ext',
        'image.fileMime' => 'cover_image_ext',
        'file_url.existUrl' => 'invalid_url',
    ];

    protected $scene = [
        'image'  =>  ['vids', 'cataids', 'image'],
        'url' => ['vids', 'cataids', 'file_url'],
    ];

    protected function checkVideo($value)
    {
        return Video::whereIn('id', $value)->count() === count($value) ? true : lang('video_id_not_exists');
    }

    protected function checkCategory($value)
    {
        return Category::whereIn('id', $value)->count() === count($value) ? true : lang('cataids_not_exists');
    }
}
