<?php
declare (strict_types = 1);

namespace app\webapi\validate;

use think\Validate;
use app\webapi\model\Video as VideoModel;
use app\webapi\model\Category;

class Video extends Validate
{
    public function field()
    {
        $this->field = [
            'files' => lang('remote_files'),
            'category_id' => lang('category_id'),
            'luping' => lang('luping'),
            'watermark' => lang('watermark'),
            'watermark_location' => lang('watermark_location'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'files' => ['require', 'array', 'each' => ['url' => ['require', 'url', 'existUrl'], 'title' => 'require']],
        'category_id' => ['integer','>:0', 'exist' => Category::class],
        'luping' => 'in:0,1',
        'watermark' => ['url', 'existUrl'],
        'watermark_location' => 'in:1,2,3,4',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'watermark.existUrl' => 'invalid_url'
    ];
}
