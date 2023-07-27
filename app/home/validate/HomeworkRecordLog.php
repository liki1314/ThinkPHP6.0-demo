<?php

declare(strict_types=1);

namespace app\home\validate;

use think\Validate;

class HomeworkRecordLog extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'submit_files' => ['array', 'checkSubmitFiles'],
        'is_draft' => ['require', 'in' => '0,1'],
        'submit_content' => 'requireWithout:submit_files',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'submit_files' => 'homework_resources',
        'submit_content' => 'homework_submit_content',
        'is_draft' => 'homework_is_draft',
    ];

    protected function checkSubmitFiles($value)
    {
        foreach ($value as $item) {
            if (!isset($item['id']) || !isset($item['source']) || !in_array($item['source'], [1, 2])) {
                return lang('homework_resources');
            }
        }
        return true;
    }
}
