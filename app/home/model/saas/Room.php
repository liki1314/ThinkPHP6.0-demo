<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\Request;
use think\exception\ValidateException;
use think\Model;
use app\common\http\WebApi;
use think\facade\Db;
use app\common\service\Math;

class Room extends Base
{
    const WEEK = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    const ROOMTYPE_ONEROOM = 0; // 小班课1对1
    const ROOMTYPE_CROWDROOM = 3; // 小班课1对多
    const ROOMTYPE_LARGEROOM = 4; // 大班课（直播）

    const ROOM_STATUS_UNSTART = 1; //未开课:上课时间未到，老师未点击上课的课节
    const ROOM_STATUS_ING     = 2; //上课中:老师点击上课，还未下课的课节
    const ROOM_STATUS_FINISH  = 3; //已上课：老师已上课的课节
    const ROOM_STATUS_EXPIRE  = 4; //已过期:上课时间已过，老师未点击上课

    protected $globalScope = ['companyId', 'courseId'];

    public function scopeCourseId($query)
    {
        $query->where('__TABLE__.course_id', '>', 0);
    }

    public static function onAfterRead(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            if (isset($request->user['company_id']) && $request->user['company_id'] != $model['company_id']) {
                $request->user = array_merge($request->user, ['company_id' => 0]);
            } elseif (!isset($request->user['company_id'])) {
                $request->user = array_merge($request->user, ['company_id' => $model['company_id']]);
            }
        });
    }

    // 学生
    public function student()
    {
        return $this->belongsToMany(FrontUser::class, RoomUser::class);
    }

    // 老师
    public function teacher()
    {
        return $this->belongsTo(FrontUser::class, 'teacher_id');
    }

    // 助教
    public function helpers()
    {
        return $this->belongsToMany(CompanyUser::class);
    }

    // 课程
    public function course()
    {
        return $this->belongsTo(Course::class)->bind(['course_name' => 'name']);
    }

    // 企业
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // 作业
    public function homeworks()
    {
        return $this->hasMany(Homework::class);
    }

    // 日期搜索器
    public function searchDayAttr($query, $value)
    {
        $query->field('id as serial,roomname,roomtype,starttime,endtime,company_id,id,teacher_id,actual_start_time,actual_end_time')
            ->withJoin([
                'company' => ['notice_config'],
            ])
            ->whereDay('__TABLE__.starttime', $value)
            ->order('starttime')
            ->with([
                'helpers' => function ($query) {
                    $query->getQuery()
                        ->field('__TABLE__.id,__TABLE__.username');
                },
                'homeworks.studentHomeworks' => function ($query) {
                    $query->field('id,room_id,is_draft,company_id');
                },
                'student.user',
                'teacher.user' => function ($query) {
                    $query->field(['nickname', 'avatar', 'username', 'userroleid', 'sex', 'id', 'user_account_id']);
                }
            ])
            ->append([
                'teachers',
                'start_date',
                'week',
                'start_to_end_time',
                'times',
                'type_name',
                'state',
                'student_num',
                'before_enter',
                'prepare_lessons',
                'preview_lessons',
                'students',
                'homeworks',
                'user',
            ])
            ->hidden([
                'helpers',
                'company',
                'teacher',
                'student',
                'actual_start_time',
                'factual_end_time',
            ]);
    }

    // 周搜索器
    public function searchWeekAttr($query, $value)
    {
        $query->field("starttime,SUBSTRING_INDEX(GROUP_CONCAT(roomname ORDER BY starttime ASC SEPARATOR ','),',',1) as roomname")
            ->whereWeek('__TABLE__.starttime', date("Y\WW", strtotime($value)))
            ->group("FROM_UNIXTIME(starttime, '%Y-%m-%d')")
            ->append(['day']);
    }

    // 月搜索器
    public function searchMonthAttr($query, $value)
    {
        $query->field("starttime,SUBSTRING_INDEX(GROUP_CONCAT(roomname ORDER BY starttime ASC SEPARATOR ','),',',1) as roomname")
            ->whereBetweenTime('__TABLE__.starttime', strtotime('-1 month', strtotime($value)), strtotime('+2 month', strtotime($value)))
            ->group("FROM_UNIXTIME(starttime, '%Y-%m-%d')")
            ->append(['day']);
    }

    public function getDayAttr($value, $data)
    {
        return isset($data['starttime']) ? date('Y-m-d', $data['starttime']) : null;
    }

    // 登录用户搜索器
    public function searchUserAttr($query, $value)
    {
        if ($value['current_identity'] == FrontUser::TEACHER_TYPE) {
            $query->join('front_user z', '__TABLE__.teacher_id=z.id and z.delete_time=0');
        } else {
            $query->join('room_user a', '__TABLE__.id=a.room_id')->join('front_user z', 'a.front_user_id=z.id and z.delete_time=0');
        }
        $query->where('z.userroleid', $value['current_identity'])
            ->where('z.user_account_id', $value['user_account_id']);
    }

    // 课节开始日期
    public function getStartDateAttr($value, $data)
    {
        $m = date('n', $data['starttime']);
        $d = date('j', $data['starttime']);
        return get_moth_by_num($m) . ' ' . get_day_by_num($d);
    }

    // 课节时长
    public function getTimesAttr($value, $data)
    {
        return timetostr($data['endtime'] - $data['starttime']);
    }

    public function getWeekAttr($value, $data)
    {
        return lang(self::WEEK[date('w', $data['starttime'])]);
    }

    public function getStartToEndTimeAttr($value, $data)
    {
        $timeFormat = (json_decode($this->getAttr('company')->notice_config ?? '', true)['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';
        return date($timeFormat, $data['starttime']) . '~' . date($timeFormat, $data['endtime']);
    }

    // 课节状态
    public function getStateAttr($value, $data)
    {
        if ($data['starttime'] == 0 || $data['starttime'] > time()) {
            return Course::UNSTART_STATE;
        } elseif ($data['starttime'] <= time() && $data['endtime'] >= time()) {
            return Course::ING_STATE;
        } elseif ($data['endtime'] < time() && $data['endtime'] > 0) {
            return Course::END_STATE;
        } else {
            return 0;
        }
    }




    // 提前进入教室秒数
    public function getBeforeEnterAttr()
    {
        return $this->invoke(function (Request $request) {
            $config = json_decode($this->getAttr('company')->notice_config ?? '', true);
            return $request->user['current_identity'] == FrontUser::STUDENT_TYPE ?
                ($config['student_enter_in_advance'] ?? config('app.notice.student_enter_in_advance')) : ($config['teacher_enter_in_advance'] ?? config('app.notice.teacher_enter_in_advance'));
        });
    }

    //学生预习课件
    public function getPrepareLessonsAttr()
    {
        $config = json_decode($this->getAttr('company')->notice_config ?? '', true);
        $result = $config['prepare_lessons'] ?? config('app.notice.prepare_lessons');
        return $result;
    }

    //学生预习课件
    public function getPreviewLessonsAttr()
    {
        $config = json_decode($this->getAttr('company')->notice_config ?? '', true);
        $result = $config['preview_lessons'] ?? config('app.notice.preview_lessons');
        return $result;
    }

    // 学生数量
    public function getStudentNumAttr()
    {
        return $this->getAttr('student')->count();
    }

    //课节师生
    public function getUserAttr()
    {
        $teacher = $this->getAttr('teacher') ? $this->getAttr('teacher')->toArray() : [];
        $student = $this->getAttr('student') ? $this->getAttr('student')->toArray() : [];
        $data    = [];
        if ($student) {
            $data = $student;
        }
        if ($teacher) {
            $data[] = $teacher;
        }
        foreach ($data as $value) {
            if ($value['userroleid'] == request()->user['current_identity'] && $value['user_account_id'] == request()->user['user_account_id']) {
                return ['id' => $value['id'], 'userid' => $value['live_userid'], 'user_account_id' => $value['user_account_id'], 'nickname' => $value['nickname']];
            }
        }
    }


    public function getHomeworksAttr($value, $data)
    {
        $id = $this->getAttr('user')['id'] ?? 0;
        $res = [];
        if (request()->user['current_identity'] == FrontUser::TEACHER_TYPE) {
            foreach ($value as $v) {
                $info['id'] = $v['id'];
                $info['is_draft'] = $v['is_draft'];
                $info['room_id'] = $v['room_id'];
                $info['company_id'] = $v['company_id'];
                $res[] = $info;
            }
        } else {
            foreach ($value as $v) {
                if ($v['is_draft'] == 1) {
                    continue;
                }
                $current = [];
                //一个课节多个作业
                foreach ($v['studentHomeworks'] as $v2) {
                    if ($v2['student_id'] == $id) {
                        $current = $v2;
                        break;
                    }
                }

                $info['id'] = $v['id'];
                $info['is_draft'] = ($current && !$current['submit_time'] && ($current['submit_content'] || $current['submit_files'])) ? 1 : 0;
                $info['room_id'] = $v['room_id'];
                $info['student_id'] = $id;
                $res[] = $info;
            }
        }

        return $res;
    }



    public function searchFreetimeAttr($query, $value, $data)
    {
        $query->field([
            'roomname' => 'lesson_name',
            'starttime',
            'endtime',
        ])
            ->withJoin(['course' => ['name'], 'company'])
            ->when(!empty($data['id']), function ($query) use ($data) {
                $query->whereExists(function ($query) use ($data) {
                    $query->name('freetime_room')->alias('fr')->whereColumn('fr.room_id', 'room.id')
                        ->where('fr.freetime_id', $data['id']);
                });
            })
            ->when(!empty($data['room_id']), function ($query) use ($data) {
                $query->where('room.id', $data['room_id']);
            })
            ->append(['start_to_end_time'])
            ->hidden(['course', 'starttime', 'endtime', 'company']);
    }
}
