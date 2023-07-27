<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-29
 * Time: 11:39
 */

namespace app\home\validate;


use app\home\model\saas\FrontUser;
use think\Validate;

class Homework extends Validate
{


    protected $rule = [
        'day' => ['date'],
        'issue_status' => ['in:1,2'],
        'lesson_id' => ['require'],
        'title' => ['require', 'max:200'],
        'resources' => ['array', 'checkArrayObjField'],
        'submit_way' => ['require', 'in:0,1,2,3'],
        'is_draft' => ['in:0,1'],
        'content' => ['requireWithout:resources'],
        'students' => ['array', 'checkStudentsCount'],
    ];


    protected $message = [
        "day.require" => 'homework_day_require',
        "day.date" => 'homework_day_date',
        "issue_status.in" => 'homework_issue_status_in',
        "title.require" => 'homework_title_require',
        "title.max" => 'homework_title_max',
        "resources" => 'homework_resources',
        "submit_way.require" => 'homework_submit_way_require',
        "submit_way.in" => 'homework_submit_way_in',
        "is_draft" => 'homework_is_draft',
        "content" => 'homework_content',
        "students.array" => 'homework_students__array',
        "students.checkStudentsCount" => 'homework_students_count',
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
            if (!isset($value['id']) || !isset($value['source']) ||
                empty($value['id']) || !is_numeric($value['id']) ||
                !in_array($value['source'], [1, 2])
            ) return false;
        }
        return true;
    }
}
