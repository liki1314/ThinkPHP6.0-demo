<?php

declare(strict_types=1);

namespace app\admin\validate;

use app\admin\model\Course as ModelCourse;
use think\Validate;
use app\admin\model\Fileinfo;
use app\admin\model\RoomTemplate;

class Course extends Validate
{
    public function field()
    {
        return [
            'room_template_id' => lang('room_template'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name' => ['require', 'max' => 50],
        'intro' => 'max:100',
        'students' => ['array', 'each' => 'integer'],
        'room_template_id' => ['require', 'integer', 'checkTemplate'],
        'resources' => ['array'/* , 'checkResources' */],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'name.require' => 'coursename_require',
        'name.length' => 'coursename_length',
        'intro' => 'courseintro_length',
        'students' => 'course_students',
        'room_template_id' => 'course_room_template',
        'resources' => 'course_resources',
    ];

    protected function checkTemplate($value, $rule, $data)
    {
        $templateModel = RoomTemplate::find($value);

        if (empty($templateModel)) {
            return ':attribute ' . lang('not_exist');
        }

        if ($data['type'] == ModelCourse::SMALL_TYPE) {
            return $templateModel['type'] == RoomTemplate::ONE_TO_ONE ||
                $templateModel['type'] == RoomTemplate::ONE_TO_MANY ? true : lang('small_course_room_tempalte');
        }

        if ($data['type'] == ModelCourse::BIG_TYPE) {
            return $templateModel['type'] == RoomTemplate::BIG_LIVE ? true : lang('big_course_room_template');
        }
    }

    /* protected function checkResources($value, $rule, $data)
    {
        if (request()->file('resources')) {
            return $this->rule('resources', ['each' => 'file'])->check($data);
        } else {
            return Fileinfo::select($value)->count() === count($value) ?: lang('resource_file_error');
        }
    } */
}
