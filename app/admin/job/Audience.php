<?php

/**
 * 师生考勤导出
 */

namespace app\admin\job;

use app\admin\model\UserCheck;
use app\common\facade\Excel;
use app\common\service\Math;
use think\facade\{Filesystem, Db, Lang};
use think\queue\Job;

class Audience
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

        $data = UserCheck::withSearch(['default'], $queParams)->select();

        $math = new Math;
        $export = [];
        if (!$data->isEmpty()) {
            $data->each(function (&$value) use (&$export, $math) {
                $value['actual'] = max(0, $value['actual']);
                $value['times'] = max(0, $value['times']);
                $value['due'] = max(0, $value['due']);
                $temp = $math->div(min($value['actual'], $value['due']), $value['due']);
                $time = $math->div($value['times'], $value['actual']);
                $row = [
                    'user_name' => $value['user_name'],
                    'mobile' => $value['mobile'],
                    'due' => (string)$value['due'],
                    'actual' => (string)min($value['actual'], $value['due']),
                    'absence' => (string)max($value['due'] - $value['actual'], 0),
                    'attendance_rate' => (int)$math->mul([$temp, 100]) . '%',
                    'avg_time' => timetostr($time),
                    //'user_id' => $value['user_id'],
                ];
                $value = $export[] = $row;
                return $row;
            });
        }

        $userName = $queParams['type'] == 1 ? lang('Teacher') : lang('Student');
        //$userId = $queParams['type'] == 1 ? lang('Teacher ID') : lang('Student ID');

        $header = [$userName, lang('Phone Number'), lang('Expected Attendance Lesson'), lang('Actual Attendance Lesson'), lang('Absence'), lang('Attendance Rate'), lang('Average Length')];

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
