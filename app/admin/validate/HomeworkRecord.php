<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-31
 * Time: 09:59
 */

namespace app\admin\validate;


use think\Validate;
use app\admin\model\HomeworkRecord as HomeworkRecordModel;

class HomeworkRecord extends Validate
{

    protected $rule = [
        'content' => ['max:500', "requireWithout:resources"],
        'useful_expressions' => ['require', 'in:0,1'],
        'rank' => ['require', 'in:0,1,2,3'],
        'students' => ['require', 'array'],
        'resources' => ['array', 'checkArrayObjField', "requireWithout:content"]
    ];


    protected $message = [
        'content.requireWithout' => 'homework_record_content_require',
        'content.max' => 'homework_record_content_max',
        'useful_expressions.require' => 'homework_record_useful_expressions_require',
        'useful_expressions.in' => 'homework_record_useful_expressions_in',
        'rank.require' => 'homework_record_rank_require',
        'rank.in' => 'homework_record_rank_in',
        'students.require' => 'homework_record_students_require',
        'students.array' => 'homework_students__array',
        'resources.array' => 'homework_resources',
        'resources.checkArrayObjField' => 'homework_resources',
        'resources.requireWithout' => 'homework_record_content_require',
    ];

    public function checkStudentsCount($value)
    {
        return HomeworkRecordModel::where('homework_id', request()->param('id'))->whereIn('student_id', $value)->count() === count($value);
    }

    /**
     * 验证数组对象是否存在字段
     * @param $item
     * @return bool
     */
    public function checkArrayObjField($item)
    {

        foreach ($item as $value) {
            if (
                (!isset($value['id']) || !isset($value['source']) || empty($value['id']) || !is_numeric($value['id']) || !in_array($value['source'], [1, 2])) &&
                (!isset($value['name']) || !isset($value['path']) || empty($value['name']) || empty($value['path']))
            ) return false;
        }
        return true;
    }

}
