<?php

declare(strict_types=1);

namespace app\admin\validate;

use app\admin\model\CompanyUser;
use app\admin\model\Course;
use think\Validate;
use app\admin\model\FrontUser;
use app\admin\model\AuthGroup;
use app\admin\model\Room as RoomModel;

class Room extends Validate
{
    public function field()
    {
        $this->field = [
            'roomname' => lang('room_name'),
            'teacher_id' => lang('teacher'),
            'helper' => lang('helper'),
            'resource' => lang('course_resource'),
            'time' => lang('room_batch_time'),
            'week' => lang('room_week'),
            'start_num' => lang('Class start number'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'roomname' => ['require', 'max' => 50],
        'resource' => 'array',
        'start_num' => ['integer','>:0'],
        'week' => [
            'requireWithout:time',
            'each' => [
                'start_date' => ['require', 'date', 'after' => 'today'],
                'num' => ['require', 'integer', '<=:100'],
                'time' => [
                    'require',
                    'array',
                    'max' => 7,
                    'each' => [
                        'week_id' => ['require', 'integer', 'between:1,7'],
                        'start_time' => ['require', 'dateFormat' => 'H:i'],
                        'end_time' => ['require', 'dateFormat' => 'H:i'],
                    ]
                ]
            ]
        ],
        'time' => 'each:' . self::class . ';start_date^start_time^end_time',
        'teacher_id' => ['require', 'integer', 'exist:' . FrontUser::class . ',userroleid=' . FrontUser::TEACHER_TYPE],
        'helper' => ['array', 'max' => 5, 'each' => 'integer', 'checkHelper'],
        'start_date' => ['require', 'date', 'after' => 'today'],
        'start_time' => ['require', 'dateFormat' => 'H:i', 'checkStartTime'],
        'end_time' => ['require', 'dateFormat' => 'H:i'],
        'resources' => ['each' => ['ids' => ['require', 'array'], 'number' => ['require', 'integer']]],
        'lesson_id' => ['array', 'each' => 'integer'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [];

    protected function checkHelper($value)
    {
        return empty($value) || CompanyUser::withSearch(['all'], ['group_id' => AuthGroup::HELPER_ROLE])->whereIn('__TABLE__.id', $value)->count() === count($value) ?: lang('course_helper_validate');
    }

    public function sceneBatch()
    {
        return $this->only(['roomname', 'teacher_id', 'helper', 'week', 'time', 'dir_id', 'file_rule_type']);
    }

    public function sceneSingle()
    {
        return $this->only(['roomname', 'teacher_id', 'helper', 'start_date', 'start_time', 'end_time', 'resource']);
    }

    public function sceneFreetime()
    {
        return $this->only(['week', 'time', 'lesson_id'])->remove('week', 'requireWithout');
    }

    public function checkStartTime($value, $rule, $data)
    {
        $time = $data['start_date'] . ' ' . $value . ':59';

        if (!isset($data['id'])) {
            return strtotime($time) >= time() ? true : lang('lesson_start_time_error');
        }

        $model = RoomModel::findOrFail($data['id'])->append(['state']);
        if ($model['state'] == Course::ING_STATE || $model['state'] == Course::UNSTART_STATE) {
            return true;
        }

        return strtotime($time) >= time() ? true : lang('lesson_start_time_error');
    }
}
