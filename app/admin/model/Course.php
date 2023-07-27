<?php

declare(strict_types=1);

namespace app\admin\model;

use app\common\Collection;
use app\gateway\model\UserAccount;
use Exception;
use think\exception\ValidateException;

class Course extends Base
{
    /** 小班课 */
    const SMALL_TYPE = 1;

    /** 大直播 */
    const BIG_TYPE = 2;

    /** 未开始 */
    const UNSTART_STATE = 1;

    /** 进行中 */
    const ING_STATE = 2;

    /** 已结课 */
    const END_STATE = 3;

    protected $json = ['students', 'resources', 'extra_info'];

    const WEEK_MAP = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    public static function onBeforeUpdate($model)
    {
        self::onBeforeDelete($model);
    }

    public static function onBeforeDelete($model)
    {
        $rooms = $model->getAttr('rooms')->column('starttime');
        sort($rooms);
        // 已开始的课程无法删除
        if (isset($rooms[0]) && $rooms[0] < time()) throw new ValidateException(lang('cannotdel_course'));
    }

    public function setResourcesAttr($value)
    {
        if (empty($value) || !is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $v) {
            if (is_numeric($v)) {
                $ids[] = strval($v);
            }
        }
        return $ids;
    }


    /**
     * 更新课程冗余的最早课节开始时间和最晚课节结束时间字段
     *
     * @param int $id 课程id
     * @return void
     */
    public static function updateStateTime($id)
    {
        $rooms = self::findOrFail($id)->getAttr('rooms');
        $updateData['first_start_time'] = $rooms->order('starttime')->first()['starttime'] ?? 0;
        $updateData['latest_end_time'] = $rooms->order('endtime')->last()['endtime'] ?? 0;
        self::where('id', $id)->update($updateData);
    }

    public static function onAfterUpdate($model)
    {
        // 更新教室关联数据
        $changed_data = $model->getChangedData();
        $data = [];

        if (!empty($changed_data['students'])) {
            $data['students'] = $changed_data['students'];
        }

        /* if (!empty($data)) {
            $model->transaction(function () use ($model, $data) {
                $model->rooms->where('starttime', '>', time())->each(function ($room) use ($data) {
                    $room->save($data);
                });
            });
        } */
    }

    //课节
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    //创建者
    public function creater()
    {
        return $this->belongsTo(UserAccount::class, 'create_by')->joinType('left')->bind(['creater_name' => 'username']);
    }

    //教室模板
    public function template()
    {
        return $this->belongsTo(RoomTemplate::class)->bind(['room_template_name' => 'name']);
    }

    //课程类型
    public function getTypeNameAttr($value, $data)
    {
        return $data['type'] == self::SMALL_TYPE ? lang('small_course') : lang('big_course');
    }

    //排课周期
    public function getPeriodAttr()
    {
        $rooms = $this->getAttr('rooms')
            ->sort(function ($a, $b) {
                return $a['starttime'] <=> $b['starttime'];
            });

        return !$rooms->isEmpty() ? $rooms->first()['start_date'] . '~' . $rooms->last()['start_date'] : '';
    }

    //循环周期
    public function getForPeriodAttr()
    {
        $data = $this->getAttr('rooms')->column('starttime');

        $week = [];
        foreach ($data as $time) {
            $week[date('N', $time)] = lang(self::WEEK_MAP[date('w', $time)]);
        }
        ksort($week);

        return implode('、', $week);
    }

    //课程进度
    public function getScheduleAttr()
    {
        $rooms = $this->getAttr('rooms');
        return $rooms->where('endtime', '<', time())->count() . '/' . $rooms->count();
    }

    //老师
    public function getTeacherAttr()
    {
        $teacher = [];

        foreach ($this->getAttr('rooms') as $room) {
            if (isset($room['teacher'])) {
                $teacher[$room['teacher']['id']] = $room['teacher'];
            }
        }

        return array_values($teacher);
    }

    //当前课节
    public function getCurrentRoomAttr($value, $data)
    {
        $rooms = $this->getAttr('rooms')->visible(['serial', 'roomname', 'starttime', 'endtime']);

        if ($data['latest_end_time'] < time() && $data['latest_end_time'] > 0) { // 所有课节已结束取结束最晚的课节
            return $rooms->order('endtime')->last();
        } elseif ($data['first_start_time'] > 0 && $data['first_start_time'] > time()) { // 所有课节未开始取未开始的第一个课节
            return $rooms->order('starttime')->first();
        } else {
            foreach ($rooms as $room) {
                if ($room['starttime'] <= time() && $room['endtime'] >= time()) { // 进行中课节
                    return $room;
                }
            }
            // 取已结束并且结束时间最晚的课节
            return $rooms->where('endtime', '<', time())->order('endtime')->last();
        }
    }

    //课程状态
    public function getStateAttr($value, $data)
    {
        if ($data['first_start_time'] == 0 || $data['first_start_time'] > time()) {
            return self::UNSTART_STATE;
        } elseif ($data['first_start_time'] <= time() && $data['latest_end_time'] >= time()) {
            return self::ING_STATE;
        } elseif ($data['latest_end_time'] < time() && $data['latest_end_time'] > 0) {
            return self::END_STATE;
        } else {
            return 0;
        }
    }

    public function searchNameAttr($query, $value)
    {
        $query->whereLike('name', "%$value%");
    }

    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    public function searchStateAttr($query, $value)
    {
        if (!empty($value)) {
            $value = explode(',', $value);

            $query->where(function ($query) use ($value) {
                if (in_array(self::UNSTART_STATE, $value)) {
                    $query->where('first_start_time', '>', time())->whereOr('first_start_time', 0);
                }

                if (in_array(self::ING_STATE, $value)) {
                    $query->whereOr(function ($query) {
                        $query->whereBetweenTimeField('first_start_time', 'latest_end_time');
                    });
                }

                if (in_array(self::END_STATE, $value)) {
                    $query->whereOr(function ($query) {
                        $query->where('latest_end_time', '<', time())->where('latest_end_time', '>', 0);
                    });
                }
            });
        }
    }

    public function searchStartDateAttr($query, $value)
    {
        $query->whereTime('latest_end_time', '>=', $value);
    }

    public function searchEndDateAttr($query, $value)
    {
        $query->whereTime('latest_end_time', '<=', sprintf('%s 23:59:59', $value));
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->with(['rooms' => function ($query) {
            $query->field('*,id serial')
                ->with([
                    'student',
                    'teacher' => function ($query) {
                        $query->field(['id', 'avatar', 'nickname', 'sex', 'userroleid'])->append(['http_avatar']);
                    }
                ]);
        }])
            ->order($data['sort'] ?? ['create_time' => 'desc'])
            ->append(['period', 'schedule', 'teacher', 'type_name', 'current_room', 'students_num'])
            ->hidden(['rooms', 'template', 'creater']);

        if (isset($data['teacher_id'])) {
            $query->alias('c')->whereExists(function ($query) use ($data) {
                $query->name('room')->alias('r')->whereColumn('c.id', 'r.course_id')
                    ->where('teacher_id', $data['teacher_id'])
                    ->where('delete_time', 0);
            });
        }
    }


    public function searchDetailAttr($query)
    {
        $query->withJoin(['template' => ['name'], 'creater' => ['username']])
            ->with(['rooms' => function ($query) {
                $query->with(['student']);
            }])
            ->append(['period', 'schedule', 'for_period', 'type_name', 'students_num'])
            ->hidden(['rooms', 'template', 'creater']);
    }

    public function searchStudentIdAttr($query, $student_id)
    {
        $query->whereIn('id', function ($query) use ($student_id) {
            $query->table('saas_room')
                ->alias('r')
                ->field(['r.course_id'])
                ->join(['saas_room_user' => 'sru'], 'r.id=sru.room_id')
                ->where('sru.front_user_id', $student_id);
        });
    }

    public function getStudentsNumAttr($value, $data)
    {
        $students = [];

        $this->getAttr('rooms')->each(function ($item) use (&$students) {
            $students = array_merge($students, $item->getAttr('student')->column('id'));
        });

        return $students ? count(array_unique($students)) : count($data['students'] ?: []);
    }

    /**
     * 批量|单次新增课节
     *
     * @param array $rooms
     * @return Collection
     * @throws Exception
     */
    public function createRoom($rooms = [])
    {
        if (!isset($rooms[0])) {
            $rooms = [$rooms];
        }

        foreach ($rooms as &$room) {
            $room['roomtype'] = $this->template['type'];
        }

        $this->startTrans();
        try {
            $models = new Collection($this->rooms()->saveAll($rooms));
            $models = $models->filter(function ($model) {
                return $model !== false;
            })
                ->each(function ($model) {
                    /**@var Room $model */
                    //同步学生
                    if (!empty($this->getAttr('students'))) {
                        $model->syncStudents($this->getAttr('students'));
                    }
                    //同步助教
                    $model->syncHelpers();
                });

            self::updateStateTime($this->getKey());

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        if ($models->isEmpty()) {
            throw new ValidateException(lang('老师未分配空闲时间'));
        }

        return $models->values();
    }


    public static function findModelExceptTeacher($id, $teacher_id): Course
    {
        $model = self::field('id,name,students,room_template_id')
            ->withJoin('template')
            ->findOrFail($id);

        $model['students'] = $model['students'] ? FrontUser::alias('a')
            ->whereIn('id', $model['students'])
            ->whereNotExists(function ($query) use ($teacher_id) {
                $query->name('front_user')
                    ->alias('b')
                    ->whereColumn('a.user_account_id', 'b.user_account_id')
                    ->where('id', $teacher_id);
            })
            ->column('id') : [];
        return $model;
    }
}
