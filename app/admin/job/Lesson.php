<?php

/**
 * 课节考勤导出
 */

namespace app\admin\job;

use app\admin\model\Course;
use app\admin\model\LessonCheck;
use app\admin\model\RoomAccessRecord;
use app\common\facade\Excel;
use think\facade\{Filesystem, Db, Lang};
use think\queue\Job;

class Lesson
{

    /**
     * 消费
     * @param Job $job
     * @param
     */
    public function fire(Job $job, $queParams)
    {
        Lang::load(app()->getBasePath() . 'admin/lang/' . $queParams['lang'] . '.php');

        request()->user = array_merge(request()->user ?? [], ['company_id' => $queParams['company_id']]);

        $data = LessonCheck::withSearch(['lesson'], $queParams)->select();

        $import = [];
        if (!$data->isEmpty()) {
            $data->withAttr('class_time', function ($value, $data) {
                return $data['actual_end_time'] && $data['actual_start_time'] ? date('Y-m-d H:i:s', $data['actual_start_time']) . '~' . date('H:i:s', $data['actual_end_time']) : '';
            });
            $nameList = (new RoomAccessRecord)->getBatchItem(array_column($data->toArray(), 'serial'));
            $import = array_map(function ($value) use ($nameList) {
                if (!$value) return $value;
                $value['course_type'] = $value['course_type'] == Course::SMALL_TYPE ? lang('Small Interactive Class') : lang('Large Live Class');
                $value['due'] = $value['due'] . ' ';
                $value['actual'] = $value['actual'] . ' ';
                $value['late'] = $value['late'] . ' ';
                $value['leave_early'] = $value['leave_early'] . ' ';
                if ($value['due'] > 0 && isset($nameList[$value['serial']]['due_item']) && $nameList[$value['serial']]['due_item']) {
                    $value['due'] .= '(' . $nameList[$value['serial']]['due_item'] . ')';
                }

                if ($value['actual'] > 0 && isset($nameList[$value['serial']]['actual_item']) && $nameList[$value['serial']]['actual_item']) {
                    $value['actual'] .= '(' . $nameList[$value['serial']]['actual_item'] . ')';
                }

                if ($value['late'] > 0 && isset($nameList[$value['serial']]['late_item']) && $nameList[$value['serial']]['late_item']) {
                    $value['late'] .= '(' . $nameList[$value['serial']]['late_item'] . ')';
                }

                if ($value['leave_early'] > 0 && isset($nameList[$value['serial']]['early_item']) && $nameList[$value['serial']]['early_item']) {
                    $value['leave_early'] .= '(' . $nameList[$value['serial']]['early_item'] . ')';
                }
                unset($value['course_id'], $value['serial'], $value['actual_start_time'], $value['actual_end_time']);

                $temp = [
                    "lesson_name" => $value['lesson_name'],
                    "course_name" => $value['course_name'],
                    "course_type" => $value['course_type'],
                    "teacher" => $value['teacher'],
                    "class_time" => $value['class_time'],
                    "times" => $value['times'],
                    "due" => $value['due'],
                    "actual" => $value['actual'],
                    "late" => $value['late'],
                    "leave_early" => $value['leave_early'],
                ];
                return $temp;
            }, $data->toArray());
        }

        $header = [lang('Class'), lang('Course'), lang('Type'), lang('Teacher'), lang('Class time'),
            lang('Length'), lang('Expected Attendance'), lang('Actual Attendance'), lang('Be Late'), lang('Leave Early')];

        $obj = Excel::export($import, $header, date('Y-m-d'));

        $path = 'excel/' . $queParams['fileName'] . '.xls';
        Filesystem::write($path, $obj->getContent());

        $save = [];
        $save['size'] = Filesystem::getSize($path);
        $save['path'] = $path;


        Db::table('saas_file_export')->where('id', $queParams['fileId'])->update($save);
        $job->delete();
    }
}
