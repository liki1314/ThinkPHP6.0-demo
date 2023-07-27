<?php

declare(strict_types=1);

namespace app\admin\model;


use think\facade\{Config,Db};

class UserCheck extends Base
{
    protected $deleteTime = false;

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->alias('a')
            ->join(['saas_front_user' => 'b'], 'a.user_id=b.id')
            ->join(['saas_user_account' => 'c'], 'c.id=b.user_account_id')
            ->field('c.account,c.locale,user_id,user_name,sum(due) due,sum(actual) actual,sum(times) times')
            ->group('a.user_id')
            ->append(['mobile'])
            ->when(!empty($data['userroleid']), function ($query) use ($data) {
                $query->where('b.userroleid', $data['userroleid']);
            })->when(!empty($data['user_name']), function ($query) use ($data) {
                $query->whereLike('a.user_name', '%' . $data['user_name'] . '%');
            })->when(!empty($data['start_date']), function ($query) use ($data) {
                $query->whereBetweenTime('a.day', $data['start_date'], $data['end_date']);
            });
    }

    public function searchUserIdAttr($query, $value, $data)
    {
        $query->where('__TABLE__.user_id',$value);
    }

    public function searchUserroleidAttr($query, $value, $data)
    {
        $query->where('userroleid',$value);
    }


    public function searchUserNameAttr($query, $value, $data)
    {
        $query->whereLike('__TABLE__.user_name','%' . $value . '%');
    }


    public function searchStartDateAttr($query, $value, $data)
    {
        $query->whereBetweenTime('__TABLE__.day', $value, $data['end_date']);
    }

    public function getMobileAttr($value, $data)
    {
        return $data['account'] ? substr((String)$data['account'], strlen($this->getAttr('code'))) : '';
    }

    public function getCodeAttr($value, $data)
    {
        return Config::get('countrycode')['abbreviation_code'][$data['locale']] ?? '86';
    }


    public function getInfo($data,$user_id)
    {
        if (!$data) return $data;
        $filter = array_column($data, 'room_id');

        //查询当前前端id对应的身份
        $result = RoomAccessRecord::alias('a')
            ->join(['saas_front_user'=>'b'],'a.company_user_id=b.id')
            ->whereIn('a.room_id',$filter)
            ->where('b.id', $user_id)
            ->where('a.type',1)
            ->field('a.entertime,a.outtime,a.room_id,company_user_id user_id,type')
            ->order('entertime', "asc")
            ->select();

        $list = $result->toArray();
        //进入1秒 即出勤
        $enterTime = 1;
        $roomAccessModel = new RoomAccessRecord;
        $map = $roomAccessModel->getTimeItem($list, $filter);
        $teacherRes = $roomAccessModel->getTeacherTimeBySerial(array_column($data, 'serial'));
        $teacherMap = [];
        if ($teacherRes) {
            foreach ($teacherRes as $v) {
                $teacherMap[$v['serial']][] = $v;
            }
        }

        foreach ($data as &$value) {
            $value['is_attendance'] = lang('no');
            $value['enter_to_out_time'] = '';
            $value['times'] = $value['final_time'] = 0;
            $value['time_info'] = $teacherMap[$value['serial']] ?? [];
            foreach ($map as $key => $v3) {
                if (!$v3) {
                    continue;
                }
                $times = 0;
                if ($value['room_id'] == $key) {

                    foreach ($v3 as $v1) {
                        $entertime = $v1['starttime'];
                        $outtime = $v1['endtime'];
                        $totalTime = $outtime - $entertime;
                        //上课时长 秒
                        $times += $totalTime;
                    }

                    unset($value['user_account_id']);
                    $value['times'] = timetostr($times);
                    $value['final_time'] = $times;
                    $value['is_attendance'] = $times >= $enterTime ? lang('yes') : lang('no');
                    $enter = current($map[$key])['starttime'];
                    $out = end($map[$key])['endtime'];
                    $value['_enter'] = date('Y-m-d H:i:s', $enter) . '-' . date('Y-m-d H:i:s', $out);
                    //存在有效时间才修改进入时间
                    if ($times) {
                        $enter = max($enter, $value['starttime']);
                        $out = min($out, $value['endtime']);
                    }

                    $value['enter_to_out_time'] = $times >= $enterTime ? date('Y-m-d H:i', $enter) . '-' . date('H:i', $out) : '';
                }
            }
        }
        return $data;
    }

    /**
     * 得到该用户的筛选框
     * @param $user_id
     * @return array
     */
    public function getSelect($user_id,&$user_role_id)
    {
        $userroleid = $user_role_id = Db::table('saas_front_user')
            ->where('id', $user_id)
            ->value('userroleid');

        if ($userroleid == FrontUser::STUDENT_TYPE) {
            $list = self::alias('a')
                ->field('d.name course_name,c.course_id,d.type course_type')
                ->join(['saas_room_user' => 'b'], 'a.user_id=b.front_user_id')
                ->join(['saas_room' => 'c'], 'b.room_id=c.id')
                ->leftJoin(['saas_course' => 'd'], 'd.id=c.course_id')
                ->where('a.user_id', $user_id)
                ->where('c.delete_time', 0)
                ->where('d.delete_time', 0)
                ->select()
                ->toArray();
        } else {
            $list = self::alias('a')
                ->field('d.name course_name,c.course_id,d.type course_type')
                ->join(['saas_front_user' => 'b'], 'a.user_id=b.id')
                ->join(['saas_room' => 'c'], 'b.id=c.teacher_id')
                ->leftJoin(['saas_course' => 'd'], 'd.id=c.course_id')
                ->where('a.user_id', $user_id)
                ->where('c.delete_time', 0)
                ->select()
                ->toArray();
        }

        $data = [];
        $data['course'] = $data['course_type'] = [];
        foreach ($list as $value) {
            if (!$value['course_type'] || !$value['course_id']) continue;
            $data['course'][$value['course_id']] = $value['course_name'];
            $data['course_type'][$value['course_type']] = $value['course_type'] == Course::SMALL_TYPE ? '小班课' : '大直播';
        }

        return $data;
    }

    /**
     * 师生明细
     * @param $user_id
     */
    public function getItem($user_id,$filter)
    {
        $userroleid = Db::table('saas_front_user')
            ->where('id',$user_id)
            ->value('userroleid');

        $startTime = strtotime($filter['start_date'] . ' 00:00:00');
        $endTime = strtotime($filter['end_date'] . '23:59:59') + 1;

        if ($userroleid == FrontUser::STUDENT_TYPE) {
            //学生数据
            $result = Db::table('saas_room_user')
                ->alias('a')
                ->field('c.name course_name,c.type course_type,a.room_id,c.id course_id,b.roomname,b.starttime,b.endtime,b.live_serial serial')
                ->join(['saas_room' => 'b'], 'a.room_id=b.id')
                ->join(['saas_course' => 'c'], 'c.id=b.course_id')
                ->where('b.delete_time', 0)
                ->where('c.delete_time', 0)
                ->where('a.front_user_id', $user_id)
                ->whereTime('b.endtime', '>', $startTime)
                ->whereTime('b.endtime', '<=', $endTime)
                ->group('a.room_id')
                ->select();

        } else {
            $result = Db::table('saas_room')
                ->alias('a')
                ->field('c.name course_name,c.type course_type,a.id room_id,c.id course_id,a.roomname,a.starttime,a.endtime,a.live_serial serial')
                ->join(['saas_front_user' => 'b'], 'a.teacher_id=b.id')
                ->join(['saas_course' => 'c'], 'c.id=a.course_id')
                ->where('c.delete_time', 0)
                ->where('a.delete_time', 0)
                ->where('a.teacher_id', $user_id)
                ->whereTime('a.endtime', '>', $startTime)
                ->whereTime('a.endtime', '<=', $endTime)
                ->group('a.id')
                ->select();
        }

        if (isset($filter['course_id']) && $filter['course_id']) {
            $result = $result->where('course_id', $filter['course_id']);
        }

        if (isset($filter['course_type']) && $filter['course_type']) {
            $result = $result->where('course_type', $filter['course_type']);
        }

        $list = $result->toArray();

        if(!$list) return  $list;

        foreach ($list as &$value) {
            $value['lesson_name'] = $value['roomname'];
            $value['start_to_end_time'] = date('Y-m-d H:i',$value['starttime']).'-'.date('H:i',$value['endtime']);
            $value['start_time'] = date('Y-m-d H:i:s',$value['starttime']);
            $value['end_time'] = date('Y-m-d H:i:s',$value['endtime']);
        }

        return array_values($list);
    }

}