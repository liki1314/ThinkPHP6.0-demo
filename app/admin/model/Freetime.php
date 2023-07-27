<?php

declare(strict_types=1);

namespace app\admin\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Freetime extends Model
{
    /** 空闲 */
    const FREE_STATUS = 1;
    /** 已排课 */
    const BUSY_STATUS = 2;

    public function searchTeacherAttr($query, $value, $data)
    {
        $query->field("b.id,b.nickname name,b.user_account_id,a.start_time,a.end_time")
            ->alias('a')
            ->join('front_user b', 'a.create_by=b.user_account_id')
            ->where('a.start_time', '>=', time())
            ->where('b.company_id', $data['user']['company_id'])
            ->where('b.userroleid', FrontUser::TEACHER_TYPE)
            ->where('b.delete_time', 0)
            ->where('b.ucstate', FrontUser::ENABLE)
            ->when(!empty(request()->user['company_model']['notice_config']['repeat_lesson']['switch'] ?? config('app.repeat_lesson')), function ($query) {
                $query->where('a.status', self::FREE_STATUS);
            });
    }


    public function searchStartDateAttr($query, $value)
    {
        if (!empty($value)) {
            $query->whereTime('start_time', '>=', $value);
        }
    }

    public function searchEndDateAttr($query, $value)
    {
        if (!empty($value)) {
            $query->whereTime('end_time', '<=', date('Y-m-d 23:59:59', strtotime($value)));
        }
    }

    public function searchTeacherIdAttr($query, $value, $data)
    {
        $query->field('a.id,a.status,a.start_time,a.end_time,0 room_id')
            ->alias('a')
            ->join('front_user b', 'a.create_by=b.user_account_id')
            ->where('b.userroleid', FrontUser::TEACHER_TYPE)
            ->where('b.id', $value)
            ->where('b.company_id', request()->user['company_id'])
            ->union(function ($query) use ($data, $value) {
                $query->field(sprintf('0 id,%d status,starttime start_time,endtime end_time,id room_id', self::BUSY_STATUS))
                    ->name('room')
                    ->alias('r')
                    ->whereTime('starttime', '>=', $data['start_date'])
                    ->whereTime('endtime', '<=', date('Y-m-d 23:59:59', strtotime($data['end_date'])))
                    ->where('delete_time', 0)
                    ->where('teacher_id', $value)
                    ->where('company_id', request()->user['company_id'])
                    ->whereNotExists(function ($query) {
                        $query->name('freetime_room')->alias('fr')->whereColumn('fr.room_id', 'r.id');
                    });
            });
    }

    public function searchLessonAttr($query)
    {
        $query->field('c.name course_name,b.roomname lesson_name,b.starttime,b.endtime')
            ->alias('a')
            ->join('room b', 'a.room_id=b.id')
            ->join('course c', 'b.course_id=c.id')
            ->where('a.status', self::BUSY_STATUS)
            ->withAttr('times', function ($value, $data) {
                return date('H:i', $data['starttime']) . '~' . date('H:i', $data['endtime']);
            })
            ->append(['times']);
    }
}
