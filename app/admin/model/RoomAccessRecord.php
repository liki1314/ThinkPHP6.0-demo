<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-02
 * Time: 08:56
 */

namespace app\admin\model;


use app\BaseModel;
use app\common\http\Network;
use think\exception\ValidateException;
use think\facade\Db;

class RoomAccessRecord extends BaseModel
{

    /**
     * @param $room_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function statisticalNumber($room_id)
    {

        $data = [
            'people_number' => 0,
            'access_number' => 0,
            'avg_time' => 0,
        ];

        $room = Room::field(['starttime', 'endtime', 'roomtype', 'id', 'live_serial'])->findOrFail($room_id);
        $time = time();
        $time = $room['endtime'] > $time ? $time : $room['endtime'];

        $teacherTime = Db::table('saas_room_timeinfo')
            ->field(["CASE endtime WHEN 0 THEN $time ELSE endtime END as endtime", "starttime"])
            ->where('serial', $room['live_serial'])
            ->order('starttime')
            ->select()
            ->toArray();
        $count = count($teacherTime);
        if (empty($count)) {
            $teacherTime = [
                [
                    'starttime' => $room['starttime'],
                    'endtime' => $room['endtime']
                ]
            ];
            $count = 1;
        }
        $startTime = $count > 0 ? $teacherTime[0]['starttime'] : $room['starttime'];
        $endtime = $count > 0 ? $teacherTime[$count - 1]['endtime'] : $room['endtime'];

        $loginTime = RoomAccessRecord::field(['user_account_id', "CASE outtime WHEN 0 THEN $time ELSE outtime END as outtime", 'entertime'])
            ->where("room_id", $room['id'])
            ->where('entertime', '<', $endtime)
            ->where(function ($query) use ($startTime) {
                $query->whereOr('outtime', '>', $startTime);
                $query->whereOr('outtime', 0);
            })
            ->order('entertime')
            ->select();

        $user = [];

        foreach ($teacherTime as $value) {
            $c = count($loginTime);
            for ($i = 0; $i < $c; $i++) {
                //有交集
                if ($loginTime[$i]['entertime'] <= $value['endtime'] && $loginTime[$i]['outtime'] >= $value['starttime']) {
                    $user[$loginTime[$i]['user_account_id']] = ($user[$loginTime[$i]['user_account_id']] ?? 0) + 1;
                    $sTime = $loginTime[$i]['entertime'] > $value['starttime'] ? $loginTime[$i]['entertime'] : $value['starttime'];
                    $eTime = $loginTime[$i]['outtime'] > $value['endtime'] ? $value['endtime'] : $loginTime[$i]['outtime'];
                    $data['avg_time'] += ($eTime - $sTime);
                }
            }
        }

        $data['people_number'] = count($user);
        $data['access_number'] = array_sum($user);
        $data['avg_time'] = !empty($data['avg_time']) ? Sec2Time(ceil($data['avg_time'] / $data['people_number'])) : '0秒';

        return $data;

    }


    /**
     * @param $info
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function statisticalInterval($info)
    {
        return self::field(['entertime', 'outtime'])
            ->where("room_id", $info['id'])
            ->where('entertime', '<', $info['endtime'])
            ->where(function ($query) use ($info) {
                $query->whereOr('outtime', '>', $info['starttime']);
                $query->whereOr('outtime', 0);
            })
            ->order('entertime', 'asc')
            ->select()
            ->toArray();
    }


    /**
     * 课堂报告
     * @param $id
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function report($id)
    {
        $info = Room::field(['id', 'starttime', 'live_serial', 'endtime', 'company_id', 'teacher_id', 'actual_start_time', 'actual_end_time'])->find($id);
        if (empty($info)) return [];
        $info = $info->toArray();
        $info['starttime'] = $info['actual_start_time'] > 0 ? $info['actual_start_time'] : $info['starttime'];
        $info['endtime'] = $info['actual_end_time'] > 0 ? $info['actual_end_time'] : $info['endtime'];

        $userIds = [];
        $userName = [];
        $Ids = RoomUser::where('room_id', $info['id'])->column('front_user_id');
        $Ids[] = $info['teacher_id'];
        $userList = FrontUser::alias('fu')
            ->leftJoin(['saas_user_account' => 'sua'], "sua.id = fu.user_account_id")
            ->field(['fu.id', 'fu.user_account_id', 'fu.userroleid', 'fu.nickname as username', 'sua.live_userid'])
            ->whereIn('fu.id', $Ids)
            ->order('fu.userroleid')
            ->order('fu.id')
            ->select()
            ->each(function ($value) use (&$userIds, &$userName, $info) {
                $userIds[] = $value->id;
                $userName[$value->id] = [
                    'name' => $value->username,
                    'role' => $info['teacher_id'] == $value->id ? "老师" : "学生",
                    'role_value' => $info['teacher_id'] == $value->id ? 0 : 1,
                ];
            })->toArray();

        $data = [];
        $time = time();
        $list = self::where('room_id', $info['id'])
            ->where('entertime', '>', $info['starttime'] - 12 * 60 * 60)
            ->whereIn('company_user_id', $userIds)
            ->order('entertime', "asc")
            ->select();

        $gift = (new Network())->getGift($info['live_serial']);

        foreach ($list as $k => $value) {
            $dataKey = $value['company_user_id'];
            //开始时间
            $entertime = $value['entertime'] < $info['starttime'] ? $info['starttime'] : $value['entertime'];
            //结束时间
            if ($value['outtime'] == 0) {
                //正在进行去当前时间
                $outtime = $time > $info['endtime'] ? $info['endtime'] : $time;
            } else {
                $outtime = $value['outtime'] > $info['endtime'] ? $info['endtime'] : $value['outtime'];
            }
            // 不在课程时间区间的交集和并集进行剔除
            if ($outtime > $info['starttime'] && $entertime < $info['endtime']) {
                $data[$dataKey]['starttime'] = $info['starttime'];
                $data[$dataKey]['endtime'] = $info['endtime'];
                //进行考勤记录
                {
                    //最早时间
                    if (!isset($data[$dataKey]['min_time'])) {
                        $data[$dataKey]['min_time'] = $entertime;
                    }
                    //最晚时间
                    $data[$dataKey]['max_time'] = $outtime;

                }
                {
                    $data[$dataKey]['username'] = $userName[$dataKey] ? $userName[$dataKey]['name'] : '';
                    $data[$dataKey]['rolename'] = $userName[$dataKey] ? $userName[$dataKey]['role'] : '';
                    $data[$dataKey]['userroleid'] = $userName[$dataKey] ? $userName[$dataKey]['role_value'] : 2;
                    //把一个时间区间放进一个里面
                    $data[$dataKey]['time_line'][] = [
                        'starttime' => $entertime,
                        'endtime' => $outtime,
                    ];
                }
                //计算每个时间区间的时长，如果早进来的就用课程开始时间，如果晚走就用课程结束时间
                {
                    if (isset($data[$dataKey]['time'])) {
                        $data[$dataKey]['time'] += $outtime - $entertime;
                    } else {
                        $data[$dataKey]['time'] = $outtime - $entertime;
                    }
                }


            }
        }
        //进行考勤统计
        foreach ($userList as $k => $v) {

            $userKey = $v['id'];

            if (isset($data[$userKey])) {
                //出勤
                $userList[$k] = $data[$userKey];
                $userList[$k]['check']['attendance'] = true;
                //是否迟到
                if ($userList[$k]['min_time'] > $info['starttime']) {

                    $userList[$k]['check']['late'] = true;
                } else {
                    $userList[$k]['check']['late'] = false;
                }
                //是否早退
                if ($userList[$k]['max_time'] < $info['endtime'] && $info['endtime'] < $time) {

                    $userList[$k]['check']['early'] = true;
                } else {
                    $userList[$k]['check']['early'] = false;
                }
                $userList[$k]['user_account_id'] = $v['user_account_id'];
                $userList[$k]['id'] = $v['id'];

                unset($userList[$k]['max_time']);
                unset($userList[$k]['min_time']);

                if ($userList[$k]['time'] > 0) {
                    $h = intval($userList[$k]['time'] / 60 / 60);
                    $m = intval(($userList[$k]['time'] - $h * 60 * 60) / 60);
                    $s = $userList[$k]['time'] - $h * 60 * 60 - $m * 60;
                    $userList[$k]['time'] = '';
                    if ($h > 0) {
                        $userList[$k]['time'] = sprintf("%d小时", $h);
                    }
                    if ($m > 0) {
                        $userList[$k]['time'] = sprintf("%s%d分钟", $userList[$k]['time'], $m);
                    }
                    if ($s > 0) {
                        $userList[$k]['time'] = sprintf("%s%d秒", $userList[$k]['time'], $s);
                    }

                } else {
                    $userList[$k]['time'] = "0秒";
                }

                //礼物
                if (!empty($gift) && isset($gift[$v['live_userid']])) {
                    $userList[$k]['cup'] = intval($gift[$v['live_userid']]);
                } else {
                    $userList[$k]['cup'] = 0;
                }


            } else {
                $userList[$k]['check']['attendance'] = false;
                $userList[$k]['check']['late'] = false;
                $userList[$k]['check']['early'] = false;
                $userList[$k]['time'] = "0秒";
                $userList[$k]['cup'] = 0;
                $userList[$k]['starttime'] = $info['starttime'];
                $userList[$k]['endtime'] = $info['endtime'];
                $userList[$k]['time_line'] = [];
                $userList[$k]['userroleid'] = $userName[$userKey] ? $userName[$userKey]['role_value'] : 2;
                $userList[$k]['rolename'] = $userName[$userKey] ? $userName[$userKey]['role'] : '';
            }

        }


        return $userList;
    }


    /**
     * 得到师生符合要求的考勤时间
     * @param $data  array 师生进出教室记录
     * @param $roomIdList  array 符合要求的教室主键ID
     */
    public function getUserTime($data = [], $roomIdList = [])
    {
        $userMap = $teacherMap = $result = [];

        foreach ($data as $v1) {
            $userMap[$v1['room_id']][$v1['user_id']][] = $v1;
        }

        $teacher = $this->getTeacherTime($roomIdList);

        if (!$teacher) {
            return $result;
        }

        foreach ($teacher as $v2) {
            $teacherMap[$v2['room_id']][] = $v2;
        }

        foreach ($userMap as $room_id => $v3) {
            if (isset($teacherMap[$room_id])) {
                foreach ($v3 as $userId => $userItem) {
                    if (!isset($result[$room_id][$userId])) {
                        $result[$room_id][$userId] = 0;
                    }

                    //返回符合考勤要求的时间段
                    $finalTimeLine = $this->getFinalTimeLine($userItem, $teacherMap[$room_id]);

                    if ($finalTimeLine) {
                        $result[$room_id][$userId] = $this->getTotal($finalTimeLine);
                    }
                }
            }
        }

        return $result;
    }


    /**
     * 根据roomid批量获取对应的教师多条上下课记录
     * @param $roomIdList
     */
    public function getTeacherTime($roomIdList)
    {
        return Db::table('saas_room_timeinfo')
            ->alias('a')
            ->leftJoin(['saas_room' => 'b'], 'a.serial=b.live_serial')
            ->field('a.starttime,a.endtime,b.id room_id,b.teacher_id user_id,a.serial')
            ->whereIn('b.id', $roomIdList)
            ->where('a.starttime', '<>', 0)
            ->where('a.endtime', '<>', 0)
            ->order('a.starttime', 'asc')
            ->select()
            ->toArray();
    }


    /**
     * 将学生时间段 和 教师时间段做匹配 返回符合考勤规则的时间段
     * @param $userData
     * @param $teacherData
     */
    public function getFinalTimeLine($userData, $teacherData)
    {
        $data = [];
        if (!$userData || !$teacherData) {
            return $data;
        }

        foreach ($teacherData as $tValue) {
            foreach ($userData as $sValue) {
                //教师身份,直接返回上课时间端
                if ($tValue['user_id'] == $sValue['user_id']) {
                    $data = $teacherData;
                    break 2;
                } else {
                    //学生在下课之后进教室的,忽略
                    if ($sValue['entertime'] >= $tValue['endtime']) {
                        continue;
                    }

                    //学生在上课之前 出教室的,忽略
                    if ($sValue['outtime'] <= $tValue['starttime']) {
                        continue;
                    }

                    $starttime = $sValue['entertime'];
                    $endtime = $sValue['outtime'];

                    //提前进去则按上课时间算
                    if ($sValue['entertime'] < $tValue['starttime']) {
                        $starttime = $tValue['starttime'];
                    }

                    //延后出去,按下课时间算
                    if ($sValue['outtime'] > $tValue['endtime']) {
                        $endtime = $tValue['endtime'];
                    }

                    $data[] = [
                        'starttime' => $starttime,
                        'endtime' => $endtime,
                        'serial' => $tValue['serial'],
                        'room_id' => $sValue['room_id'],
                        'user_id' => $sValue['user_id'],
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * 计算出某个师生单个用户的总时长
     * @param $timeLine
     */
    private function getTotal($timeLine)
    {
        $total = 0;
        if (!$timeLine) {
            return $total;
        }

        foreach ($timeLine as $value) {
            $total += $value['endtime'] - $value['starttime'];
        }

        return $total;
    }

    /**
     * 得到师生ID为key 对应教室的 符合考勤要求的时间段
     * @param array $data 原始师生进出教室时间段
     * @param array $roomIdList 教室ID集合
     * @return array
     */
    public function getUserTimeLine($data = [], $roomIdList = [])
    {
        $userMap = $teacherMap = $result = [];

        foreach ($data as $v1) {
            $userMap[$v1['room_id']][$v1['user_id']][] = $v1;
        }

        $teacher = $this->getTeacherTime($roomIdList);

        if (!$teacher) {
            return $result;
        }

        foreach ($teacher as $v2) {
            $teacherMap[$v2['room_id']][] = $v2;
        }

        foreach ($userMap as $room_id => $v3) {
            if (isset($teacherMap[$room_id])) {
                foreach ($v3 as $userId => $userItem) {
                    if (!isset($result[$userId])) {
                        $result[$userId] = [];
                    }
                    //返回符合考勤要求的时间段
                    $finalTimeLine = $this->getFinalTimeLine($userItem, $teacherMap[$room_id]);

                    if ($finalTimeLine) {
                        $result[$userId] = array_merge($result[$userId], $finalTimeLine);
                    }
                }
            }
        }
        return $result;
    }


    /**
     * 得到某一个用户下,多个教室的 符合要求的时间段
     * @param $data
     * @param $roomIdList
     */
    public function getTimeItem($data, $roomIdList)
    {
        $userMap = $teacherMap = $result = [];
        foreach ($data as $v1) {
            $userMap[$v1['room_id']][$v1['user_id']][] = $v1;
        }

        $teacher = $this->getTeacherTime($roomIdList);

        if (!$teacher) {
            return $result;
        }

        foreach ($teacher as $v2) {
            $teacherMap[$v2['room_id']][] = $v2;
        }

        foreach ($userMap as $room_id => $v3) {
            if (isset($teacherMap[$room_id])) {
                foreach ($v3 as $userId => $userItem) {
                    if (!isset($result[$room_id])) {
                        $result[$room_id] = [];
                    }
                    //返回符合考勤要求的时间段
                    $finalTimeLine = $this->getFinalTimeLine($userItem, $teacherMap[$room_id]);

                    if ($finalTimeLine) {
                        $result[$room_id] = array_merge($result[$room_id], $finalTimeLine);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 课堂报告
     * @param $room_id
     */
    public function getReportByRoomId($room_id)
    {
        $roomModel = new Room;
        $rModel = $roomModel->field(['live_serial', 'id', 'custom_id', 'roomname', 'starttime', 'endtime', 'company_id', 'course_id', 'teacher_id'])
            ->where('id', $room_id)
            ->findOrEmpty();

        if ($rModel->isEmpty()) {
            throw new ValidateException(lang('Room_does_not_exist'));
        }

        $info = $rModel->toArray();

        $userIdList = RoomUser::where('room_id', $info['id'])->column('front_user_id');

        $userIdList[] = $info['teacher_id'];

        $userList = FrontUser::alias('a')
            ->whereIn('a.id', $userIdList)
            ->join(['saas_user_account' => 'b'], 'a.user_account_id=b.id')
            ->field('userroleid,nickname username,a.id user_id,b.live_userid')
            ->select()
            ->each(function (&$value) {
                $value->rolename = $value->userroleid == FrontUser::STUDENT_TYPE ? '学生' : '教师';
            })
            ->toArray();
        //最后一次下课时间
        $teacherTime = Db::table('saas_room_timeinfo')
            ->where('serial', $info['live_serial'])
            ->order('starttime', 'desc')
            ->find();

        //统计师生考勤
        $data = self::alias('a')
            ->join(['saas_front_user' => 'b'], 'a.company_user_id=b.id')
            ->join(['saas_room' => 'c'], 'c.id=a.room_id')
            ->where('a.room_id', $info['id'])
            ->where('a.type', 1)
            ->where('a.entertime', '<>', 0)
            ->where('a.outtime', '<>', 0)
            ->field('a.entertime,a.outtime,a.room_id,b.id user_id,a.user_account_id,b.userroleid,c.starttime,c.endtime')
            ->order('entertime', "asc")
            ->select()
            ->toArray();

        $timeLine = $this->getUserTime($data, [$info['id']]);

        $inClassTime = 1;

        $gift = (new Network())->getGift($info['live_serial']);

        $userTimeLine = $this->getUserTimeLine($data, [$info['id']]);

        $map = [];

        foreach ($data as $temp) {
            $map[$temp['user_id']][] = $temp;
        }

        foreach ($userList as &$value) {
            $value['check'] = [
                'late' => false, //未迟到
                'early' => false, //未早退
                'attendance' => false, //未出勤
            ];

            //出勤
            $userTime = $timeLine[$info['id']][$value['user_id']] ?? 0;
            $value['check']['attendance'] = $userTime >= $inClassTime ? true : false;

            $value['time'] = $userTime ? timetostr($userTime) : '0秒';

            $tempMap = $map[$value['user_id']] ?? [];
            //迟倒
            foreach ($tempMap as $v2) {
                $value['check']['late'] = ($value['check']['attendance'] && $v2['entertime'] > $info['starttime']) ? true : false;
                break;
            }

            //早退
            foreach ($tempMap as $v3) {
                $isEarly = $v3['outtime'] > $info['starttime'] && $v3['outtime'] < $info['endtime'] ? true : false;
                $value['check']['early'] = $isEarly;
            }

            $value['cup'] = $gift[$value['live_userid']] ?? 0;
            $value['time_line'] = $userTimeLine[$value['user_id']] ?? [];
            $value['starttime'] = $info['starttime'];
            $value['endtime'] = $info['endtime'];
            $value['in_out_time_line'] = $map[$value['user_id']] ?? [];
        }

        return $userList;
    }


    /**
     * 导出考勤批量获取迟早 早退 实际出勤 应该出勤名字
     * @param array $roomIdList
     * @return mixed
     * @throws \app\common\exception\LiveException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getBatchItem($roomIdList = [])
    {
        $roomModel = new Room;
        $roomRes = $roomModel->field(['live_serial', 'id', 'custom_id', 'roomname', 'starttime', 'endtime', 'company_id', 'course_id', 'teacher_id'])
            ->whereIn('id', $roomIdList)
            ->select()
            ->toArray();

        $roomInfoMap = $userIdMap = [];
        foreach ($roomRes as $v) {
            $roomInfoMap[$v['id']] = $v;
            $userIdMap[$v['id']][] = $v['teacher_id'];
        }

        $userIdRes = RoomUser::whereIn('room_id', $roomIdList)
            ->select()
            ->toArray();

        foreach ($userIdRes as $v1) {
            $userIdMap[$v1['room_id']][] = $v1['front_user_id'];
        }


        //查询每个教室的师生列表
        $userListRes = Room::alias('a')
            ->leftJoin('saas_room_user b', 'b.room_id=a.id')
            ->leftJoin('saas_front_user c', 'c.id=b.front_user_id or a.teacher_id=c.id')
            ->whereIn('a.id', $roomIdList)
            ->field('a.id room_id,c.userroleid,c.nickname,c.id user_id')
            ->select()
            ->toArray();

        $userListMap = [];
        $hash = [];
        foreach ($userListRes as $v3) {
            if (!isset($hash[$v3['room_id']][$v3['user_id']])) {
                $userListMap[$v3['room_id']][] = $v3;
            }
            $hash[$v3['room_id']][$v3['user_id']] = $v3['user_id'];
        }

        //统计师生考勤
        $data = self::alias('a')
            ->join(['saas_front_user' => 'b'], 'a.company_user_id=b.id')
            ->join(['saas_room' => 'c'], 'c.id=a.room_id')
            ->whereIn('a.room_id', $roomIdList)
            ->where('a.type', 1)
            ->where('a.entertime', '<>', 0)
            ->where('a.outtime', '<>', 0)
            ->field('a.entertime,a.outtime,a.room_id,b.id user_id,a.user_account_id,b.userroleid,c.starttime,c.endtime')
            ->order('entertime', "asc")
            ->select()
            ->toArray();

        $timeLine = $this->getUserTime($data, $roomIdList);

        $inClassTime = 1;

        $map = [];

        foreach ($data as $temp) {
            $map[$temp['room_id']][$temp['user_id']][] = $temp;
        }

        $result = [];
        foreach ($userListMap as $roomId => $roomValue) {
            $dueItem = $actualItem = $lateItem = $earlyItem = [];
            foreach ($roomValue as $value) {
                $value['check'] = [
                    'late' => false, //未迟到
                    'early' => false, //未早退
                    'attendance' => false, //未出勤
                ];

                //应到
                $dueItem[] = $value['nickname'];
                //出勤
                $userTime = $timeLine[$roomId][$value['user_id']] ?? 0;
                $value['check']['attendance'] = $userTime >= $inClassTime ? true : false;

                $value['time'] = $userTime ? timetostr($userTime) : '0秒';
                //实到
                if ($value['check']['attendance'] && !in_array($value['nickname'], $actualItem)) {
                    $actualItem[] = $value['nickname'];
                }

                $tempMap = $map[$roomId][$value['user_id']] ?? [];
                //迟倒
                $startTime = $roomInfoMap[$roomId]['starttime'];

                foreach ($tempMap as $v2) {
                    $value['check']['late'] = ($value['check']['attendance'] && $v2['entertime'] > $startTime) ? true : false;
                    //迟到
                    if ($value['check']['late'] && !in_array($value['nickname'], $lateItem)) {
                        $lateItem[] = $value['nickname'];
                    }
                    break;
                }

                $endTime = $roomInfoMap[$roomId]['endtime'];
                //早退
                foreach ($tempMap as $v3) {
                    $isEarly = $v3['outtime'] > $startTime && $v3['outtime'] < $endTime ? true : false;
                    $value['check']['early'] = $isEarly;
                }

                //统计早退名单
                if ($value['check']['early'] && !in_array($value['nickname'], $earlyItem)) {
                    $earlyItem[] = $value['nickname'];
                }
            }

            $result[$roomId] = [
                'due_item' => $dueItem ? $this->genateUserName($dueItem) : '', //应到名单
                'actual_item' => $actualItem ? $this->genateUserName($actualItem) : '', //实到名单
                'late_item' => $lateItem ? $this->genateUserName($lateItem) : '', //迟到名单
                'early_item' => $earlyItem ? $this->genateUserName($earlyItem) : ''// 早退名单
            ];
        }

        return $result;
    }

    /**
     * 生成名单
     * @param array $item
     */
    private function genateUserName($item = [])
    {
        $userName = '';
        foreach ($item as $v) {
            $userName .= $v . ',';
        }
        return trim($userName, ',');
    }

    /**
     * 根据教室号批量获取教师点击时间段
     * @param $serialList
     */
    public function getTeacherTimeBySerial($serialList = [])
    {
        return Db::table('saas_room_timeinfo')
            ->field('starttime,endtime,serial')
            ->whereIn('serial', $serialList)
            ->where('starttime', '<>', 0)
            ->where('endtime', '<>', 0)
            ->order('starttime', 'asc')
            ->select()
            ->toArray();
    }

}
