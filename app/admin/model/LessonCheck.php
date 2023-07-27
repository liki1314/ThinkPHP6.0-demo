<?php

declare(strict_types=1);

namespace app\admin\model;

class LessonCheck extends Base
{
    protected $deleteTime = false;

    public function searchLessonAttr($query, $value, $data)
    {
        $query->alias('t')
            ->join(['saas_course' => 'a'], 't.course_id=a.id')
            ->join(['saas_room' => 'b'], 't.room_id=b.id')
            ->field('lesson_name,course_name,course_type,teacher,times,due,actual,late,leave_early,room_id serial,t.course_id,actual_start_time,actual_end_time')
            ->append(['class_time'])
            ->where('a.delete_time', 0)
            ->where('b.delete_time', 0)
            ->when(!empty($data['course_id']), function ($query) use ($data) {
                $query->where('t.course_id', $data['course_id']);
            })->when(!empty($data['teacher_id']), function ($query) use ($data) {
                $query->where('t.teacher_id', $data['teacher_id']);
            })->when(!empty($data['lesson_name']), function ($query) use ($data) {
                $query->whereLike('t.lesson_name', '%' . $data['lesson_name'] . '%');
            })->when(!empty($data['start_date']) && !empty($data['end_date']), function ($query) use ($data) {
                $query->whereBetweenTime('t.day', $data['start_date'], $data['end_date']);
            });
    }

    public function searchCourseIdAttr($query, $value, $data)
    {
        $query->where('__TABLE__.course_id', $value);
    }

    public function searchTeacherIdAttr($query, $value, $data)
    {
        $query->where('__TABLE__.teacher_id', $value);
    }

    public function searchLessonNameAttr($query, $value, $data)
    {
        $query->whereLike('__TABLE__.lesson_name', '%' . $value . '%');
    }

    public function searchStartDateAttr($query, $value, $data)
    {
        $query->whereBetweenTime('__TABLE__.day', $value, $data['end_date']);
    }

    public function getTimesAttr($value)
    {
        return timetostr($value);
    }

    public function getClassTimeAttr($value, $data)
    {
        return $data['actual_start_time'] && $data['actual_end_time'] ? date('Y-m-d H:i', $data['actual_start_time']) . '~' . date('H:i', $data['actual_end_time']) : '';
    }
}
