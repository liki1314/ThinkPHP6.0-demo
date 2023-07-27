<?php

declare(strict_types=1);

namespace app\admin\model;

use app\Request;
use think\exception\ValidateException;
use Carbon\Carbon;
use think\facade\{Db, Route};
use thans\jwt\facade\JWTAuth;
use think\facade\Cache;
use app\common\http\WebApi;

class Room extends Base
{
    const WEEK = [
        null,
        '周一',
        '周二',
        '周三',
        '周四',
        '周五',
        '周六',
        '周日',
    ];
    const ROOMTYPE_ONEROOM = 0; // 小班课1对1
    const ROOMTYPE_CROWDROOM = 3; // 小班课1对多
    const ROOMTYPE_LARGEROOM = 4; // 大班课（直播）

    /** 已过期 */
    const DUE_STATE = 4;

    const WEEK_MAP = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    /** @var int 批量创建课节起始编号 */
    protected static $num = 1;

    protected $type = [
        'starttime' => 'int',
        'endtime' => 'int',
    ];

    // 设置课节开始和结束时间
    public function setStartDateAttr($value, $data)
    {
        $this->set('starttime', strtotime($value . ' ' . $data['start_time']));
        $this->set('endtime', strtotime($value . ' ' . $data['end_time']));

        if ($this->endtime - $this->starttime < 300) {
            throw new ValidateException(lang('room_time_too_short'));
        }
    }

    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        // 判断老师当前时间段是否已经安排了课节
        if (array_intersect(array_keys($model->getChangedData()), ['starttime', 'endtime', 'teacher_id'])) {
            if (!empty(request()->user['company_model']['notice_config']['repeat_lesson']['switch'] ?? config('app.repeat_lesson'))) {
                $exist = $model->where('teacher_id', $model['teacher_id'])
                    ->where('starttime', '<', $model['endtime'])
                    ->where('endtime', '>', $model['starttime']);
                if ($model->isExists()) {
                    $exist->where('__TABLE__.' . $model->getPk(), '<>', $model->getKey());
                }
                if ($exist->value('__TABLE__.' . $model->getPk())) {
                    if (!empty(request()->user['company_model']['notice_config']['scheduling']['freetime_switch'])) {
                        return false;
                    }
                    throw new ValidateException(date('Y-m-d H:i', $model['starttime']) . '~' . date('Y-m-d H:i', $model['endtime']) . lang('room_teacher_exists'));
                }
            }

            // 老师是否设置了空闲时间
            if (
                isset(request()->user['company_model']['notice_config']['scheduling']) &&
                request()->user['company_model']['notice_config']['scheduling']['freetime_switch'] == 1
            ) {
                $isFree = Freetime::alias('a')
                    ->join('front_user b', 'b.user_account_id=a.create_by')
                    ->where('a.start_time', '<=', $model['starttime'])
                    ->where('a.end_time', '>=', $model['endtime'])
                    ->when(!empty(request()->user['company_model']['notice_config']['repeat_lesson']['switch'] ?? config('app.repeat_lesson')), function ($query) {
                        $query->where('a.status', Freetime::FREE_STATUS);
                    })
                    ->where('b.id', $model['teacher_id'])
                    ->value('a.id');
                if (!$isFree) {
                    return false;
                }
            }
        }

        if (request()->has('append_num', 'route')) {
            $model->set('roomname', $model['roomname'] . self::$num++);
        }
    }

    public static function onBeforeDelete($model)
    {
        if (!in_array($model->getAttr('schedule'), [Course::UNSTART_STATE, self::DUE_STATE])) {
            throw new ValidateException(lang('cannotdel_lesson'));
        }
    }

    public static function onBeforeUpdate($model)
    {
        if (!in_array($model->getAttr('schedule'), [Course::UNSTART_STATE, self::DUE_STATE, Course::ING_STATE])) {
            throw new ValidateException(lang('cannot_update_lesson'));
        }
    }

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->set('custom_id', uniqid('', true));
    }

    public static function onAfterWrite($model)
    {
        Cache::tag('notice')->set(
            'notice:room_pk:' . $model->getKey(),
            $model->visible(['roomname', 'starttime', 'endtime', 'teacher_id', 'id', 'company_id'])
                ->append(['start_lesson_time'])
                ->toArray(),
            new \DateTime(date('Y-m-d H:i:s', $model['endtime'] + 600))
        );

        Db::transaction(function () use ($model) {
            // 取消空闲时间已排课状态,删除关联信息
            Freetime::where('room_id', $model->getKey())
                ->update([
                    'status' => Freetime::FREE_STATUS,
                    'room_id' => 0
                ]);
            Db::name('freetime_room')->where('room_id', $model->getKey())->delete();

            // 更新空闲时间排课状态,插入关联信息
            $freetimeModel = Freetime::alias('a')
                ->join('front_user b', 'a.create_by=b.user_account_id')
                ->where('b.id', $model['teacher_id'])
                ->where('a.start_time', '<=', $model['starttime'])
                ->where('a.end_time', '>=', $model['endtime'])
                ->field('a.*')
                ->find();
            if (!empty($freetimeModel)) {
                $freetimeModel->save([
                    'status' => Freetime::BUSY_STATUS,
                    'room_id' => $model->getKey(),
                ]);
                Db::name('freetime_room')->extra('IGNORE')->insert(['freetime_id' => $freetimeModel->getKey(), 'room_id' => $model->getKey()]);
            }
        });
    }

    public static function onAfterDelete($model)
    {
        Cache::delete('notice:room_pk:' . $model->getKey());

        // 取消空闲时间已排课状态
        Freetime::where('room_id', $model->getKey())
            ->update([
                'status' => Freetime::FREE_STATUS,
                'room_id' => 0
            ]);
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

    //上下课时间
    public function roomTime()
    {
        return $this->hasMany(RoomTimeinfo::class, 'serial', 'live_serial');
    }


    public function getStartDateAttr($value, $data)
    {
        return date('Y-m-d', $data['starttime']);
    }

    /* public function getStartTimeAttr($value, $data)
    {
        return date('H:i', $data['starttime']);
    }

    public function getEndTimeAttr($value, $data)
    {
        return date('H:i', $data['endtime']);
    } */

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('id,id as serial,roomname,starttime,endtime,live_serial,actual_start_time,actual_end_time,course_id,create_time')
            ->order('starttime ' . ($data['sort'] ?? 'asc'))
            ->withJoin(['teacher' => ['id', 'avatar', 'nickname', 'sex', 'userroleid']]);

        if (!empty($data['course_id'])) {
            $query->where('course_id', $data['course_id']);
        }

        if (isset($data['no_page'])) {
            $this->isPage = false;
        } else {
            $query->with([
                'helpers.user' => function ($query) {
                    $query->getQuery()
                        ->field('__TABLE__.id,__TABLE__.username');
                }
            ])
                ->append(['start_lesson_time', 'schedule', 'users', 'resource_num', 'students', 'enter_students', 'video_ratio_text'])
                ->hidden(['teacher', 'helpers.pivot']);
        }
    }

    // 老师（兼容历史）
    public function getUsersAttr()
    {
        $teacher = $this->getAttr('teacher');
        return [['id' => $teacher['id'], 'nickname' => $teacher['nickname'], 'http_avatar' => $teacher['http_avatar']]];
    }

    public function searchDetailAttr($query)
    {
        $query->field('id,id as serial,roomname,starttime,endtime,course_id,custom_id,live_serial,actual_start_time,actual_end_time')
            ->withJoin(['teacher' => ['id', 'avatar', 'nickname', 'sex', 'userroleid']])
            ->with([
                'helpers.user' => function ($query) {
                    $query->visible(['id', 'username']);
                },
                'course.creater' => function ($query) {
                    $query->field('id,name,create_by');
                }
            ])
            ->withAttr('start_time', function ($value, $data) {
                return date('H:i', $data['starttime']);
            })
            ->withAttr('end_time', function ($value, $data) {
                return date('H:i', $data['endtime']);
            })
            ->append(['start_date', 'start_time', 'end_time', 'users', 'files'])
            ->hidden(['starttime', 'endtime', 'teacher', 'helpers.pivot', 'files.pivot']);
    }


    public function getStartToEndTimeAttr($value, $data)
    {
        $timeFormat = (request()->user['company_model']['notice_config']['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';
        return date($timeFormat, $data['starttime']) . '~' . date($timeFormat, $data['endtime']);
    }

    public function getScheduleAttr($value, $data)
    {
        if (empty($data['actual_start_time']) && time() > $data['endtime']) {
            return self::DUE_STATE;
        }

        if (!empty($data['actual_start_time']) && !empty($data['actual_end_time'])) {
            return Course::END_STATE;
        }

        if (!empty($data['actual_start_time']) && empty($data['actual_end_time'])) {
            return Course::ING_STATE;
        }

        return Course::UNSTART_STATE;
    }

    /**
     * 获取批量新增课节开课时间
     *
     * @param string $startDate 开课日期
     * @param int $num 课节数量
     * @param array $week 循环周期 1-7
     *
     * @return array
     */
    public static function getTimeByWeek($startDate, $num, $week)
    {
        $day = Carbon::create($startDate)->subDays(); //回退一天方便计算
        $weekNum = count($week);
        // sort($week);
        $n = date('N', strtotime($startDate));
        foreach ($week as $value) {
            $sortWeek[$value >= $n ? $value - $n : $value + 7 - $n] = $value;
        }

        ksort($sortWeek);
        $week = array_values($sortWeek);
        $times = [];

        for ($i = 0; $i < $num; $i++) {
            $newDay = $day->copy()->weekday($week[$i % $weekNum]);
            if ($newDay->lte($day)) {
                $newDay->addWeek();
            }
            $day = $newDay;
            $times[] = ['week_id' => $week[$i % $weekNum], 'start_date' => $newDay->format('Y-m-d')];
        }

        return $times;
    }

    /**
     * 更新课节关联学生信息
     *
     * @param array $students 学生id数组
     * @param bool $append 追加or覆盖
     * @return void
     */
    public function syncStudents($students = [], $append = true)
    {
        if ($append !== true) {
            $this->student()->detach();
        }

        Db::name('room_user')
            ->extra('IGNORE')
            ->insertAll(array_map(
                function ($val) {
                    return ['room_id' => $this->getKey(), 'front_user_id' => $val];
                },
                $students
            ));
    }

    /**
     * 更新课节关联助教信息
     *
     * @param array $helpers 助教id数组
     * @param bool $append 追加or覆盖
     * @return void
     */
    public function syncHelpers($helpers = [], $append = true)
    {
        $helpers = $this->getAttr('helper') ?? $helpers;

        if ($append !== true) {
            $this->helpers()->detach();
        }

        Db::name('room_company_user')
            ->extra('IGNORE')
            ->insertAll(array_map(
                function ($val) {
                    return ['room_id' => $this->getKey(), 'company_user_id' => $val];
                },
                $helpers
            ));
    }

    public function searchDayAttr($query, $value, $data)
    {
        $timeFormat = ($data['user']['company_model']['notice_config']['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';
        $query->field('roomname name,id room_id,id,course_id')
            ->whereDay('starttime')
            ->where(function ($query) {
                $query->where('starttime', '<', time())
                    ->whereOr(function ($query) {
                        $query->where('actual_start_time', '<', time())->where('actual_start_time', '>', 0);
                    });
            })
            ->where(function ($query) {
                $query->where('endtime', '>', time())
                    ->whereOr(function ($query) {
                        $query->where('actual_end_time', '=', 0)->where('actual_start_time', '>', 0);
                    });
            })
            ->whereRaw("case when actual_end_time!=0 then actual_end_time>starttime else 1 end")
            ->with([
                'teacher',
                'helpers.user',
            ])
            ->withAttr('times', function ($value, $data) use ($timeFormat) {
                return date($timeFormat, $data['starttime']) . '~' . date($timeFormat, $data['endtime']);
            })
            ->append(['times', 'teacher_name', 'students', 'enter_students', 'is_helper_enter_url', 'is_tour_enter_url'])
            ->hidden(['roomname', 'users', 'helpers', 'teacher', 'serial', 'id']);

        $roles = request()->user['roles'] ?? [];

        if (in_array(AuthGroup::HELPER_ROLE, $roles) && !in_array(AuthGroup::COURSE_ROLE, $roles)) {
            $query->join(['saas_room_company_user' => 'a'], '__TABLE__.id=a.room_id')
                ->where('a.company_user_id', request()->user['id']);
        }
    }


    public function searchDateAttr($query, $value)
    {
        $query->whereMonth('__TABLE__.starttime', $value);
    }


    public function getTeacherNameAttr()
    {
        return $this->getAttr('teacher')->toArray()['nickname'] ?? '';
    }


    public function searchStartDateAttr($query, $value, $data)
    {
        $timeFormat = ($data['user']['company_model']['notice_config']['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';
        if ($value == $data['end_date']) {
            $query->whereDay('starttime', $value);
        } else {
            $query->whereTime('starttime', '>=', $value . ' 00:00:00')
                ->whereTime('starttime', '<=', $data['end_date'] . '23:59:59');
        }
        $query->field('teacher_id')
            ->with([
                'teacher',
                'helpers'
            ])
            ->withAttr('name', function ($value, $data) {
                return $data['roomname'];
            })
            ->withAttr('times', function ($value, $data) use ($timeFormat) {
                return date($timeFormat, $data['starttime']) . '~' . date($timeFormat, $data['endtime']);
            })
            ->append(['name', 'times', 'teacher_name', 'helpers', 'is_helper_enter_url', 'is_tour_enter_url', 'state'])
            ->hidden(['roomname', 'users', 'teacher']);

        $roles = request()->user['roles'] ?? [];

        if (in_array(AuthGroup::HELPER_ROLE, $roles) && !in_array(AuthGroup::COURSE_ROLE, $roles)) {
            $query->join(['saas_room_company_user' => 'a'], '__TABLE__.id=a.room_id')
                ->where('a.company_user_id', request()->user['id']);
        }
    }


    /**
     * 助教链接
     * @param $value
     * @param $data
     */
    public function getIsHelperEnterUrlAttr($value, $data)
    {
        return $this->invoke(function (Request $request) use ($data) {
            return $request->user['super_user'] ||
                in_array(AuthGroup::HELPER_ROLE, $request->user['roles'] ?? []) &&
                in_array($request->user['user_account_id'], $this->getAttr('helpers')->column('user_account_id')) ?
                1 : 0;
        });
    }

    /**
     * 巡课
     * @param $value
     * @param $data
     */
    public function getIsTourEnterUrlAttr($value, $data)
    {
        return $this->invoke(function (Request $request) use ($data) {
            return $request->user['super_user'] || in_array(AuthGroup::COURSE_ROLE, $request->user['roles'] ?? []) ? 1 : 0;
        });
    }

    /**
     * 生成重定向的进入房间地址
     * @param $roleId
     * @param $room_id
     * @return string
     */
    public function getRoomUrl($roleId, $room_id)
    {
        $username = urlencode(request()->user['username']);
        return (string)Route::buildUrl("enterRoom/$room_id-$roleId-$username", ['token' => JWTAuth::token()->get()])
            ->domain(true)->suffix('');
    }

    public function getStateAttr($value, $data)
    {
        return $this->getScheduleAttr($value, $data);
    }
    /**
     * 批量修改课节老师
     *
     * @param \app\common\Collection $models 课节模型数据集
     * @param array $params
     * @return void
     */
    public static function updateTeacher($models, $params)
    {
        Db::transaction(function () use ($models, $params) {
            $models->update(['teacher_id' => $params['teacher_id']]);
        });
    }

    /**
     * 批量修改课节视频分辨率
     *
     * @param \app\common\Collection $models 课节模型数据集
     * @param array $params
     * @return void
     */
    public static function updateRatio($models, $params)
    {
        WebApi::httpJson(
            'WebAPI/batchRoomModify',
            [
                'key' => Company::getDetailById(request()->user['company_id'])['authkey'], //此接口只能通过post传参方式传递企业authkey
                'roomParamList' => array_map(function ($model) use ($params) {
                    return [
                        'roomname' => $model['roomname'],
                        'starttime' => $model['starttime'],
                        'endtime' => $model['endtime'],
                        'thirdroomid' => $model['custom_id'],
                        'videotype' => $params['video_ratio'],
                    ];
                }, $models->toArray())
            ]
        );
    }

    public function searchExportAttr($query, $value, $data)
    {
        $query->alias('a')
            ->field([
                'a.id room_id',
                'a.teacher_id',
                'c.nickname',
                "JSON_UNQUOTE(d.extend_info->'$.domain_account')" => 'domain_account',
                'account',
                'a.starttime',
                'a.endtime',
                'a.create_by as createBy',
                'a.actual_start_time',
                'a.actual_end_time',
                'a.delete_time',
                'a.roomtype',
                'h.video_ratio',
                "group_concat(typename,',')" => 'typename',
                'a.live_serial',
            ])
            ->rightJoin('saas_room_user b', 'a.id=b.room_id')
            ->leftJoin('saas_front_user c', 'c.id=b.front_user_id')
            ->leftJoin('saas_user_account d', 'd.id=c.user_account_id')
            ->leftJoin('saas_frontuser_group e', 'e.front_user_id=c.id')
            ->leftJoin('saas_student_group f', 'e.group_id=f.id')
            ->leftJoin('saas_course g', 'g.id=a.course_id')
            ->leftJoin('saas_room_template h', 'h.id=g.room_template_id')
            ->when(!empty($data['start_date']), function ($query) use ($data) {
                $query->whereTime('a.starttime', '>=', $data['start_date'] . ' 00:00:00');
            })
            ->when(!empty($data['end_date']), function ($query) use ($data) {
                $query->whereTime('a.starttime', '<=', $data['end_date'] . '23:59:59');
            })
            ->when(empty($data['has_del']), function ($query) {
                $query->where('a.delete_time', 0);
            })
            ->when(!empty($data['company_id']), function ($query) use ($data) {
                $query->where('a.company_id', $data['company_id']);
            })
            ->group('b.front_user_id,b.room_id');
    }

    public function searchFreetimeAttr($query, $value, $data)
    {
        $query->field([
            'roomname' => 'lesson_name',
            'starttime',
            'endtime',
        ])
            ->withJoin(['course' => ['name']])
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
            ->hidden(['course', 'starttime', 'endtime']);
    }
}
