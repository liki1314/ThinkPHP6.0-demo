<?php

declare(strict_types=1);

namespace app\home\model\saas;

use think\exception\ValidateException;
use think\facade\Db;

class Freetime extends Base
{
    protected $deleteTime = false;
    /** 空闲 */
    const FREE_STATUS = 1;
    /** 已排课 */
    const BUSY_STATUS = 2;

    protected $globalScope = ['creater'];

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $exist = Db::name('room')->alias('a')
            ->join('front_user b', 'a.teacher_id=b.id')
            ->join('company c', 'b.company_id=c.id')
            ->where('b.user_account_id', $model['create_by'])
            ->where('a.starttime', '<', $model['end_time'])
            ->where('a.endtime', '>', $model['start_time'])
            ->where('a.delete_time', 0)
            ->json(['notice_config'])
            ->field('notice_config')
            ->find();
        if (!is_null($exist) && !empty($exist['notice_config']['repeat_lesson']['switch'] ?? config('app.repeat_lesson'))) {
            throw new ValidateException(lang('空闲时间已排课'));
        }
    }

    public static function onBeforeDelete($model)
    {
        if ($model['status'] == self::BUSY_STATUS) {
            throw new ValidateException(lang('已排课无法取消'));
        }
    }

    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        $exist = $model->where('start_time', '<', $model['end_time'])
            ->where('end_time', '>', $model['start_time'])
            ->when($model->isExists(), function ($query) use ($model) {
                $query->where($model->getPk(), '<>', $model->getKey());
            })
            ->value($model->getPk());
        if ($exist) {
            throw new ValidateException(lang('空闲时间重叠'));
        }
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

    public function setFromTimeAttr($value, $data)
    {
        $this->set('start_time', strtotime($data['current_date'] . ' ' . $value));
    }

    public function setToTimeAttr($value, $data)
    {
        $this->set('end_time', strtotime($data['current_date'] . ' ' . $value));
    }

    public function searchTeacherIdAttr($query, $value, $data)
    {
        $query->field('id,status,start_time,end_time,0 room_id')
            ->union(function ($query) use ($data) {
                $query->field(sprintf('0 id,%d status,starttime start_time,endtime end_time,r.id room_id', self::BUSY_STATUS))
                    ->name('room')
                    ->alias('r')
                    ->join('front_user f', 'r.teacher_id=f.id')
                    ->whereTime('r.starttime', '>=', $data['start_date'])
                    ->whereTime('r.endtime', '<=', date('Y-m-d 23:59:59', strtotime($data['end_date'])))
                    ->where('r.delete_time', 0)
                    ->where('f.user_account_id', request()->user['user_account_id'])
                    ->whereNotExists(function ($query) {
                        $query->name('freetime_room')->alias('fr')->whereColumn('fr.room_id', 'r.id');
                    });
            });
    }
}
