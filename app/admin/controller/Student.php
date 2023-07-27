<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-08-05
 * Time: 15:09
 */

namespace app\admin\controller;


use app\admin\model\FrontUser;
use app\admin\model\Room;
use app\admin\model\Course as CourseModel;
use app\admin\model\RoomAccessRecord;
use think\exception\ValidateException;

/**
 * 学生管理
 * Class Student
 * @package app\admin\controller
 */
class Student extends Base
{
    /**
     * 获取学生教室列表
     * @param $student_id
     * @return \think\response\Json
     */
    public function lesson($student_id)
    {
        $this->validate($this->param,
            [
                'start_date' => 'date',
                'end_date' => 'date'
            ],
            [
                'start_date' => 'company_id_Illegal',
                'end_date' => 'company_id_Illegal'
            ]
        );
        $field = ['r.id', 'r.roomname as lesson_name', 'sc.name as course_name', 'sfu.nickname as teacher', 'r.starttime', 'r.endtime'];
        $list = Room::alias('r')->field($field)
            ->join(['saas_front_user' => 'sfu'], 'r.teacher_id=sfu.id')
            ->join(['saas_course' => 'sc'], 'r.course_id=sc.id')
            ->join(['saas_room_user' => 'sru'], 'r.id=sru.room_id')
            ->where(function ($query) {
                if (isset($this->param['start_date']) && !empty($this->param['start_date'])) {
                    $query->where('starttime', '>', strtotime(sprintf("%s 00:00:00", $this->param['start_date'])));
                }
                if (isset($this->param['end_date']) && !empty($this->param['end_date'])) {
                    $query->where('starttime', '<', strtotime(sprintf("%s 23:59:59", $this->param['end_date'])));
                }
            })
            ->where('sru.front_user_id', $student_id)
            ->order('starttime')
            ->append(['start_to_end_time', 'start_date'])
            ->select();
        if (count($list) > 0) {
            $end_time = $list[0]['endtime'];
            $list[0]['status'] = 0;
            foreach ($list as $key => $value) {
                if (isset($list[$key + 1])) {
                    if ($end_time > $list[$key + 1]['starttime']) {
                        $list[$key]['status'] = 1;
                        $list[$key + 1]['status'] = 1;
                    } else {
                        $list[$key + 1]['status'] = 0;
                    }
                    if ($end_time < $list[$key + 1]['endtime']) {
                        $end_time = $list[$key + 1]['endtime'];
                    }
                }
            }
        }
        return $this->success($list);
    }


    /**
     * 获取课程列表
     * @param $student_id
     * @return \think\response\Json
     */
    public function course($student_id)
    {
        return $this->success($this->searchList(CourseModel::class)->each(function ($value) {
            $value['status'] = $value['state'];
            $value->append(['period', 'schedule', 'teacher', 'type_name', 'students_num'])
                ->hidden([
                    'rooms',
                    'template',
                    'creater',
                    'students',
                    'resources',
                    'create_time',
                    'update_time',
                    'delete_time',
                    'create_by',
                    'company_id',
                    'state',
                    'room_template_id',
                    'first_start_time',
                    'latest_end_time',
                    'extra_info',
                ]);
        }));
    }

    /**
     * @param $student_id
     * @param $course_id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function courseLesson($student_id, $course_id)
    {

        $user_account_id = FrontUser::where('id', $student_id)->value('user_account_id');
        if (empty($user_account_id)) throw new ValidateException(lang("company_not_students"));
        $room_ids = [];
        $list = Room:: field('id, roomname, starttime, endtime, live_serial')
            ->join(['saas_room_user' => 'sru'], '__TABLE__.id=sru.room_id')
            ->withJoin([
                'teacher' => ['id', 'avatar', 'nickname', 'sex', 'userroleid']
            ])
            ->with([
                'helpers' => function ($query) {
                    $query->getQuery()
                        ->field('__TABLE__.id,__TABLE__.username,__TABLE__.user_account_id')
                        ->with(['user' => function ($query) {
                            $query->field(['id', 'avatar', 'account', 'locale']);
                        }]);
                },
                'roomTime' => function ($query) {
                    $query->getQuery()->order('starttime', 'desc');
                },
                'teacher' => function ($query) {
                    $query->getQuery()->field(['__TABLE__.id', '__TABLE__.avatar', '__TABLE__.nickname', '__TABLE__.sex', '__TABLE__.userroleid']);
                }
            ])
            ->where('course_id', $course_id)
            ->where('sru.front_user_id', $student_id)
            ->order('starttime', 'asc')
            ->append(['start_lesson_time', 'helpers.name'])
            ->select()
            ->each(function ($value) use (&$room_ids) {
                $room_ids[] = $value->id;
                $value['teacher']['name'] = $value['teacher']['nickname'];
            });

        $login = [];
        $time = time();
        if (!empty($room_ids)) {
            RoomAccessRecord::field(['room_id', 'entertime', 'outtime'])
                ->whereIn('room_id', $room_ids)
                ->where('user_account_id', $user_account_id)
                ->select()->each(function ($value) use (&$login) {
                    $login[$value->room_id][] = $value;
                });
        }
        // 1  出勤 2 未出勤 3上课中 已出勤 4 上课中 未出勤 5 未开课
        $list->each(function ($value) use ($login, $time) {

            $status = 2;
            //未点击上课
            if ($value['roomTime']->isEmpty()) {
                if ($value['endtime'] >= $time) {
                    //未结束 + 未开始
                    $status = 5; //未开课
                } else {
                    //已结束
                    $status = 2; //未出勤
                }
            } else {
                if ($value['roomTime'][0]['endtime'] > 0) {
                    $status = 2;   //1课节结束
                } else {
                    $status = 4;   //2课节进行中
                }
                if (isset($login[$value['id']]) && !empty($login[$value['id']])) {
                    foreach ($value['roomTime'] as $rv) {
                        $st = $rv['starttime'];
                        $et = $rv['endtime'] > 0 ? $rv['endtime'] : $time;
                        foreach ($login[$value['id']] as $lv) {
                            $lst = $lv['entertime'];
                            $let = $lv['outtime'] > 0 ? $lv['outtime'] : $time;
                            if ($lst <= $et && $let >= $st) {
                                $status = $status == 2 ? 1 : 3;
                                break 2;
                            }
                        }
                    }
                }

            }
            $value->status = $status;
        })->hidden([
            'starttime',
            'endtime',
            'helpers.pivot',
            'teacher.sex',
            'teacher.nickname',
            'teacher.userroleid',
            'helpers.username',
            'helpers.locale',
            'helpers.mobile',
            'helpers.code',
            'helpers.account',
            'helpers.user_account_id',
            'roomTime',
            'live_serial',
        ]);

        return $this->success($list);
    }
}
