<?php

/**
 * 师生考勤导出
 */

namespace app\admin\job;

use app\admin\model\Course;
use app\common\facade\Excel;
use think\facade\{Filesystem, Db, Lang};
use think\queue\Job;

class Attendance
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

        $export = [];

        if (!empty($queParams['data'])) {
            $export = array_map(function ($value) {
                $temp = [];
                $temp['lesson_name'] = $value['lesson_name'];
                $temp['course_name'] = $value['course_name'];
                $temp['course_type'] = $value['course_type'] == Course::SMALL_TYPE ? lang('Small Interactive Class') : lang('Large Live Class');
                $timeString = '';
                if (!empty($value['time_info'])) {
                    foreach ($value['time_info'] as $t) {
                        $timeString .= date('Y-m-d Y:i:s', $t['starttime']) . '-' . date('i:s', $t['endtime'])."\r\n";
                    }
                }
                $temp['time_info'] = $timeString;
                $temp['times'] = $value['times'] ?: timetostr($value['times']);
                $temp['is_attendance'] = $value['is_attendance'];
                return $temp;
            }, $queParams['data']);

        }

        $header = [
            lang('Class'), lang('Course'), lang('Type'), lang('room_start_time'), lang('Length'), lang('Whether to attend')
        ];

        $obj = Excel::export($export, $header, date('Y-m-d'));

        $path = 'excel/' . $queParams['fileName'] . '.xls';
        Filesystem::write($path, $obj->getContent());

        $save = [];
        $save['size'] = Filesystem::getSize($path);
        $save['path'] = $path;

        Db::table('saas_file_export')->where('id', $queParams['fileId'])->update($save);
        $job->delete();
    }
}
