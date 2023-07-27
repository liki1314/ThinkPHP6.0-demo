<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\job\Attendance as AttendanceQueue;
use app\admin\job\Lesson;
use app\admin\job\Audience;
use app\common\facade\Excel;
use app\admin\model\{LessonCheck, UserCheck, FrontUser, Course, RoomAccessRecord};
use app\common\service\Math;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Queue;

class Attendance extends Base
{

    /**
     * 课节考勤
     */
    public function lesson()
    {
        $model = new LessonCheck;
        if (!empty($this->param['export'])) {
            $model->isPage = false;
        }
        $this->param['lesson'] = true;
        $data = $this->searchList($model);

        if (!empty($this->param['export'])) {
            $save = [];
            $save['name'] = 'export_user_attendance';
            $save['type'] = 'lesson_attendance';
            $save['company_id'] = $this->request->user['company_id'];
            $save['create_time'] = time();
            $save['create_by'] = $this->request->user['user_account_id'];

            $fileId = Db::table('saas_file_export')->insertGetId($save);

            $queParams = array_merge($this->request->param(), [
                'company_id' => $this->request->user['company_id'],
                'create_by' => $this->request->user['user_account_id'],
                'lang' => Lang::getLangSet(),
                'fileId' => $fileId,
                'fileName' => MD5(microtime(true) . $this->request->user['user_account_id']),
            ]);

            Queue::push(Lesson::class, $queParams, 'lesson');
        }
        return $this->success($data);
    }

    /**
     * 师生考勤
     * @param $type
     */
    public function index($type)
    {
        $model = new UserCheck;
        if (!empty($this->param['export'])) {
            $model->isPage = false;
        }
        $this->param['userroleid'] = ($type == 1 ? FrontUser::TEACHER_TYPE : FrontUser::STUDENT_TYPE);
        $export = [];
        $math = new Math;
        $data = $this->searchList($model)->each(function (&$value) use (&$export, $math) {
            $value['actual'] = max(0, $value['actual']);
            $value['times'] = max(0, $value['times']);
            $value['due'] = max(0, $value['due']);
            $temp = $math->div(min($value['actual'], $value['due']), $value['due']);
            $time = $math->div($value['times'], $value['actual']);
            $row = [
                'user_name' => $value['user_name'],
                'mobile' => $value['mobile'],
                'due' => $value['due'],
                'actual' => min($value['actual'], $value['due']),
                'absence' => max($value['due'] - $value['actual'], 0),
                'attendance_rate' => (int)$math->mul([$temp, 100]) . '%',
                'avg_time' => timetostr($time),
                'user_id' => $value['user_id'],
            ];
            $value = $export[] = $row;
            return $row;
        });

        if (!empty($this->param['export'])) {
            $save = [];
            $save['name'] = 'export_user_attendance';
            $save['type'] = $type == 1 ? 'Teacher Attendance' : 'Student Attendance';
            $save['company_id'] = $this->request->user['company_id'];
            $save['create_time'] = time();
            $save['create_by'] = $this->request->user['user_account_id'];

            $fileId = Db::table('saas_file_export')->insertGetId($save);

            $queParams = array_merge($this->request->param(), [
                'userroleid' => ($type == 1 ? FrontUser::TEACHER_TYPE : FrontUser::STUDENT_TYPE),
                'type' => $type,
                'company_id' => $this->request->user['company_id'],
                'create_by' => $this->request->user['user_account_id'],
                'lang' => Lang::getLangSet(),
                'fileId' => $fileId,
                'fileName' => MD5(microtime(true) . $this->request->user['user_account_id']),
            ]);

            Queue::push(Audience::class, $queParams, 'audience');
        }
        return $this->success($data);
    }

    /**
     * 考勤明细
     * @param $user_id
     */
    public function info($user_id)
    {
        $rule = [
            'start_date' => ['require', 'date'],
            'end_date' => ['require', 'date'],
            'course_id' => ['integer'],
            'course_type' => ['integer'],
            'export' => ['integer'],
        ];

        $message = [
            'start_date.require' => 'start_date_empty',
            'end_date.require' => 'end_date_empty',
        ];

        $this->validate($this->param, $rule, $message);
        $obj = new UserCheck;
        $data = $obj->getItem($user_id, $this->param);
        $res = $obj->getSelect($user_id, $userroleid);
        $res['list'] = $obj->getInfo($data, $user_id);
        $res['count'] = count($res['list']);

        if (!empty($this->param['export'])) {
            $save = [];
            $save['name'] = 'export_user_attendance'; //考勤统计
            $save['type'] = $userroleid == FrontUser::STUDENT_TYPE ? 'student_atten_item' : 'teacher_atten_item';  //师生明细
            $save['company_id'] = $this->request->user['company_id'];
            $save['create_time'] = time();
            $save['create_by'] = $this->request->user['user_account_id'];

            $fileId = Db::table('saas_file_export')->insertGetId($save);

            $queParams = array_merge($this->request->param(), [
                'company_id' => $this->request->user['company_id'],
                'create_by' => $this->request->user['user_account_id'],
                'lang' => Lang::getLangSet(),
                'fileId' => $fileId,
                'fileName' => MD5(microtime(true) . $this->request->user['user_account_id']),
                'data' => $res['list']
            ]);

            Queue::push(AttendanceQueue::class, $queParams, 'attendance_export');
        }

        return $this->success($res);
    }
}
