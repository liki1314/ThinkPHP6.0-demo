<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-29
 * Time: 11:39
 */

namespace app\admin\validate;


use think\Validate;
use app\admin\model\FrontUser;

class Homework extends Validate
{


    protected $rule = [
        'day' => ['require', 'date'],
        'title' => ['require', 'max:200'],
        'students' => ['require', 'array', 'checkStudentsCount'],
        'resources' => ['array', 'checkArrayObjField'],
        'submit_way' => ['require', 'in:0,1,2,3'],
        'issue_status' => ['require', 'in:1,2'],
        'is_draft' => ['in:0,1']
    ];


    protected $message = [
        "day.require" => 'homework_day_require',
        "day.date" => 'homework_day_date',
        "title.require" => 'homework_title_require',
        "title.max" => 'homework_title_max',
        "students.require" => 'homework_students_require',
        "students.array" => 'homework_students__array',
        "students.checkStudentsCount" => 'homework_students_count',
        "resources" => 'homework_resources',
        "submit_way.require" => 'homework_submit_way_require',
        "submit_way.in" => 'homework_submit_way_in',
        "issue_status.require" => 'homework_issue_status_require',
        "issue_status.in" => 'homework_issue_status_in',
        "is_draft" => 'homework_is_draft'
    ];

    public function checkStudentsCount($value)
    {
        return FrontUser::whereIn('id', $value)->count() === count($value);
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
