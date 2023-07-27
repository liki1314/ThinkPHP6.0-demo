<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-07-02
 * Time: 09:29
 */

namespace app\common\service\room;


use app\admin\model\Room;
use app\admin\model\RoomUser;
use app\home\model\saas\RoomCompanyUser;
use think\facade\Cache;
use app\admin\model\Company;

/**
 * 在线教室学生消息推送
 * Class RoomStudentsMsg
 * @package app\common\service\message
 */
class RoomStudentsMsg
{
    /**
     * 学生进入教室信息
     * 1、过期时间24小时
     * 2、关闭教室进行关闭
     */
    const LOGIN_STATUS = 'notice:room:students:time:%s';
    /**
     *  延迟消息队列 zset
     * 1、教室结束时间到了，不在写入
     * 2、关闭教室 写入标识到COMPANY_ROOM_BLACK_HOUSE
     * 3、全部清空 写入标识到COMPANY_ROOM_BLACK_HOUSE
     */
    const ROOM_INFO_ALL = 'notice:room:company:info';
    /**
     *某公司消息 zset
     * 1、打开教室 追加过期时间 + 24 * 3600
     * 2、关闭教室清空该课节消息
     * 3、课节结束时间到了 获取消息进行清除过期消息
     */
    const COMPANY_MSG_ALL = 'notice:company:room:msg:%s';
    /**
     * black house set
     * 1、打开教室初始化过期时间
     * 2、关闭教室，延迟15分钟结束
     */
    const COMPANY_ROOM_BLACK_HOUSE = 'notice:company:room:blacklist%s:%s';

    const MSG_TYPE_NO = 1;
    const MSG_TYPE_EXIT = 2;

    const LANG_MAP = [
        '中途退出超过'=>'room_time_out',
        '已上课'=>'in_class',
        '未进入教室'=>'not_in_class_room',
    ];


    /**
     * @return object
     */
    private static function redis()
    {

        return Cache::store('redis')->handler();
    }

    /**
     * 处理学生手机号
     * @param $account
     * @param $locale
     * @return bool|string
     */
    private static function getMobileAttr($account, $locale)
    {
        $code = config('countrycode.abbreviation_code.' . $locale) ?: '86';

        return $account ? substr((string)$account, strlen($code)) : '';
    }


    /**
     * 设置学生进出入状态
     * @param $room_id  教室ID
     * @param $students_id  学生ID
     * @param $status   状态  1 进入 2离开
     */
    public static function setStudentsStatus($room_id, $students_id, $status)
    {
        $redis = self::redis();
        $key = sprintf(self::LOGIN_STATUS, $room_id);
        $redis->HSET($key, $students_id, $status);
        $time = $redis->TTL($key);
        if ($time < 0) {
            $redis->EXPIRE($key, 24 * 3600);
        }
    }


    /**
     * 初始化教室信息（上课）
     * @param $room_id
     * @param $time  开始通知的时间（上课时间）
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function openRoom($room_id, $time)
    {
        //教室信息
        $room = Room::field(['id', 'roomname', 'starttime', 'endtime', 'teacher_id', 'company_id'])->findOrEmpty($room_id);
        if (!$room->isEmpty()) {

            //学生信息
            $students = RoomUser::alias("ru")
                ->field(['ru.room_id', 'sfu.id as students_id', 'sfu.user_account_id', 'sfu.nickname', 'sua.account', 'sua.locale'])
                ->join(['saas_front_user' => 'sfu'], 'ru.front_user_id = sfu.id')
                ->leftJoin(['saas_user_account' => 'sua'], 'sfu.user_account_id = sua.id')
                ->where('ru.room_id', $room_id)
                ->select();
            //助教信息
            $auxiliary = RoomCompanyUser::where('room_id', $room_id)->column('company_user_id');
            //通知频率
            $companyConfig = Company::getNoticeConfigByCompanyId($room['company_id']);
            //教室配置信息
            $data = $room->toArray();
            $data['auxiliary'] = [];
            //助教信息
            foreach ($auxiliary as $value) {
                $data['auxiliary'][] = strval($value);
            }

            //上课时间
            //$data['on_time'] = $time;
            //未进入通知频率
            $enter = $companyConfig['room']['late']['switch'] != 1 ? 0 : $companyConfig['room']['late']['mins'] * 60;
            //离开通知频率
            $leave = $companyConfig['room']['leave_early']['switch'] != 1 ? 0 : $companyConfig['room']['leave_early']['mins'] * 60;
            //设置redis 的字段，包括config，students的ID,notice_time
            //都没开启消息推送
            if ($enter == 0 && $leave == 0) {
                return false;
            }
            $data['students'] = [];
            /**
             * 订阅者
             */
            foreach ($students as $v) {
                $data['students'][$v['students_id']] = [
                    'name' => $v['nickname'],
                    'mobile' => self::getMobileAttr($v['account'], $v['locale'])
                ];
            }
            $msgKey = sprintf(self::COMPANY_MSG_ALL, $data['company_id']);
            $ttl = self::redis()->TTL($msgKey);
            self::multi(function ($pipe) use ($data, $time, $enter, $leave, $ttl, $msgKey) {
                if ($enter > 0) {
                    $data['type_msg'] = self::MSG_TYPE_NO;
                    $data['delay_time'] = $enter;
                    $pipe->ZADD(self::ROOM_INFO_ALL, $time + $enter, json_encode($data));
                }
                if ($leave > 0) {
                    $data['type_msg'] = self::MSG_TYPE_EXIT;
                    $data['delay_time'] = $leave;
                    $pipe->ZADD(self::ROOM_INFO_ALL, $time + $leave, json_encode($data));
                }
                $pipe->DEL(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $data['company_id'], $data['id']));
                $pipe->SADD(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $data['company_id'], $data['id']), sprintf('%s-room', $time));
                $pipe->EXPIRE(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $data['company_id'], $data['id']), $data['endtime'] - $time);

                if (($data['endtime'] - $time) > $ttl) {
                    $pipe->ZADD($msgKey, ...[0, 'info']);
                    $pipe->EXPIRE($msgKey, $data['endtime'] - $time);
                }
            });

        }

    }

    /**
     * 管道
     * @param $func
     */
    public static function multi($func)
    {
        $pipe = self::redis()->multi(\Redis::PIPELINE);
        $func($pipe);
        $pipe->exec();
    }


    /**
     * @param $func
     */
    public static function pipeline($func)
    {
        $redis = self::redis();
        $redis->pipeline();
        $func($redis);
        $redis->exec();
    }

    /**
     * 关闭教室（下课）
     * @param $companyId $redis->EXPIRE(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $companyId, $roomId), 20 * 60);
     * @param $roomId
     * @param $endTime
     */
    public static function closeRoom($companyId, $roomId, $endTime)
    {
        self::multi(function ($redis) use ($companyId, $roomId, $endTime) {
            $redis->EXPIRE(sprintf(self::LOGIN_STATUS, $roomId), 20 * 60);
            $redis->SADD(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $companyId, $roomId), 'msg-1', 'msg-2');
            $redis->EXPIRE(sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $companyId, $roomId), 20 * 60);
            $s = self::getRoomId($endTime, $roomId);
            $redis->ZREMRANGEBYSCORE(sprintf(self::COMPANY_MSG_ALL, $companyId), $s, $s);
        });
    }


    /**
     * 消息发布（每分钟执行一次）
     * 获取所有房间redis key 在单个处理消息
     */
    public static function dealWithAllRoomMsg()
    {
        $time = time();
        $student = [];
        $roomKeys = [];
        $roomData = self::getRoomAll($time, function ($room) use (&$student, &$roomKeys) {
            $student[] = sprintf(self::LOGIN_STATUS, $room['id']);
            $roomKeys[] = sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $room['company_id'], $room['id']);
        });

        $student = array_keys(array_flip($student));
        $roomKeys = array_keys(array_flip($roomKeys));
        $studentStatus = self::getStudentsStatus($student);
        $roomStatus = self::getRoomStatus($roomKeys);
        self::pipeline(function ($redis) use ($roomData, $time, $studentStatus, $roomStatus) {
            foreach ($roomData as $v) {
                $key = sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $v['company_id'], $v['id']);
                if ($v['endtime'] > $time && !in_array(sprintf('msg-%s', $v['type_msg']), $roomStatus[$key] ?? [])) {
                    self::dealWithRoomMsg($redis, $v, $studentStatus, $roomStatus, $time);
                }
            }
        });
    }

    /**
     * 获取教室状态
     * @param $room
     * @return array
     */
    public static function getRoomStatus($room)
    {
        $data = [];
        $redis = self::redis();
        $lua = "local rst={}; for i,v in pairs(KEYS) do rst[i]=redis.call('SMEMBERS', v) end; return rst";
        $res = $redis->eval($lua, $room, count($room));
        foreach ($room as $key => $val) {
            $data[$val] = $res[$key];
        }
        return $data;
    }

    /**
     * 获取学生状态
     * @param $student
     * @return array
     */
    private static function getStudentsStatus($student)
    {
        $data = [];
        $redis = self::redis();
        $lua = "local rst={}; for i,v in pairs(KEYS) do rst[i]=redis.call('hgetall', v) end; return rst";
        $res = $redis->eval($lua, $student, count($student));
        foreach ($student as $key => $val) {
            if (!empty($res[$key])) {
                $stud = [];
                $count = count($res[$key]);
                for ($i = 0; $i < $count; $i = $i + 2) {
                    $stud[$res[$key][$i]] = $res[$key][$i + 1];
                }
                if (!empty($stud)) {
                    $data[$val] = $stud;
                }
            }

        }
        return $data;
    }

    /**
     * 获取所有教室
     * @param $time
     * @param string $func
     * @return array
     */
    private static function getRoomAll($time, $func = '')
    {
        $redis = self::redis();
        //获取所有教室信息
        $lua = "
            local room_all = redis.call('ZRANGEBYSCORE',KEYS[1], '-inf',KEYS[2],'WITHSCORES')
            redis.call('ZREMRANGEBYSCORE',KEYS[1], '-inf',KEYS[2])
            return room_all
        ";
        $res = $redis->eval($lua, [self::ROOM_INFO_ALL, $time], 2);
        $count = count($res);
        $roomData = [];
        for ($i = 0; $i < $count; $i = $i + 2) {
            $room = json_decode($res[$i], true);
            $roomData[] = $room;

            if (!empty($func)) {
                $func($room);
            }
        }
        return $roomData;
    }

    /**
     * 处理单个教室的消息
     * notice_enter_time notice_leave_time 是上一次处理时间
     * enter leave 是处理时间间隔 0为不做处理  大于0是时间间隔
     * 学生中的  enter  leave 是否要发送通知 0 否 1是
     * @param $redis
     * @param $roomInfo
     * @param $studentStatus
     * @param $roomStatus
     * @param $time
     */
    private static function dealWithRoomMsg($redis, $roomInfo, $studentStatus, $roomStatus, $time)
    {
        $keyMsg = sprintf(self::COMPANY_MSG_ALL, $roomInfo['company_id']);
        $msgData = [];
        $keyStatus = sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $roomInfo['company_id'], $roomInfo['id']);
        $roomStatus[$keyStatus] = $roomStatus[$keyStatus] ?? [];
        $roomInfo['on_time'] = $roomInfo['endtime'];
        foreach ($roomStatus[$keyStatus] as $k => $v) {
            if (strpos($v, 'room') !== false) {
                list($roomInfo['on_time'],) = explode('-', $v);
            }
        }

        foreach ($roomInfo['students'] as $k => $v) {

            $studentsKey = sprintf(self::LOGIN_STATUS, $roomInfo['id']);
            //学生状态，学生状态写入时间
            list($studentsStatus, $studentsTime) = explode('-',
                $studentStatus[$studentsKey][$k] ?? sprintf('0-%s', $roomInfo['on_time']));
            //学生禁用退出
            if (in_array(sprintf('%s-%s', $k, $roomInfo['type_msg']), $roomStatus[$keyStatus])) continue;
            //类型 未进入-已经进来 不写
            //类型 离开-没有离开 不写
            if (
                $roomInfo['type_msg'] == self::MSG_TYPE_NO && $studentsStatus == 0 ||
                $roomInfo['type_msg'] == self::MSG_TYPE_EXIT && $studentsStatus == 2
            ) {
                $msgData[] = self::getRoomId($roomInfo['endtime'], $roomInfo['id']);
                $msgData[] = serialize([
                    'id' => strval($k),
                    'name' => strval($v['name']),
                    'mobile' => strval($v['mobile']),
                    'room_id' => strval($roomInfo['id']),
                    'room_name' => strval($roomInfo['roomname']),
                    'start_time' => strval($roomInfo['starttime']),           //上课时间
                    'end_time' => strval($roomInfo['endtime']),               //下课时间
                    'on_time' => strval($roomInfo['on_time']),                //实际上课时间
                    'msg_time' => strval($time),
                    'msg_type' => strval($roomInfo['type_msg']),
                    'students_time' => strval($studentsTime),
                    'auxiliary' => $roomInfo['auxiliary']
                ]);
            }

        }
        if (!empty($msgData)) {
            $redis->ZADD($keyMsg, ...$msgData);
        }
        unset($roomInfo['on_time']);
        $redis->ZADD(self::ROOM_INFO_ALL, $time + $roomInfo['delay_time'], json_encode($roomInfo));

    }


    /**
     * 关闭某个教室下某学生某种类型的消息
     * @param $company_id
     * @param $data
     */
    public static function closeRoomStudentsTypeMsg($company_id, $data)
    {
        //所有未0是助教信息，不能删除
        self::pipeline(function ($redis) use ($company_id, $data) {
            $key = sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $company_id, $data['room_id']);
            $value = sprintf('%s-%s', $data['id'], $data['msg_type']);
            $keyMsg = sprintf(self::COMPANY_MSG_ALL, $company_id);
            if ($data['end_time'] > time()) {
                $redis->SADD($key, $value);
            }
            $redis->ZREM($keyMsg, serialize($data));
        });
    }

    /**
     * 获取某个公司下面所有消息
     * @param $company_id
     * @param bool $user_id
     * @return array
     */
    public static function getCompanyMsg($company_id, $user_id = false)
    {
        $redis = self::redis();
        $e = self::getRoomId(time(), 999999);
        $redis->ZREMRANGEBYSCORE(sprintf(self::COMPANY_MSG_ALL, $company_id), 1, $e);
        $list = $redis->ZRANGE(sprintf(self::COMPANY_MSG_ALL, $company_id), 1, -1, true);
        $data = [];
        foreach ($list as $k => $v) {
            if ($k == 'info') continue;
            $info = unserialize($k);
            if ($user_id === false || in_array($user_id, $info['auxiliary'])) {
                if ($info['msg_type'] == self::MSG_TYPE_NO) {
                    $info['content'] = sprintf(lang(self::LANG_MAP['已上课']).'%s'.lang(self::LANG_MAP['未进入教室']), timetostr($info['msg_time'] - $info['on_time']));
                } elseif ($info['msg_type'] == self::MSG_TYPE_EXIT) {
                    $info['content'] = sprintf(lang(self::LANG_MAP['中途退出超过']).'%s', timetostr($info['msg_time'] - $info['students_time']));
                } else {
                    continue;
                }
                $data[] = $info;
            }

        }
        if (!empty($data)) {
            $last = array_column($data, 'msg_time');
            array_multisort($last, SORT_DESC, $data);
        }

        return $data;
    }

    /**
     * 清空消息
     * @param $company_id
     * @param bool $user_id
     */
    public static function closeAll($company_id, $user_id = false)
    {
        $list = RoomStudentsMsg::getCompanyMsg($company_id, $user_id);
        self::pipeline(function ($redis) use ($list, $company_id, $user_id) {
            $keyMsg = sprintf(self::COMPANY_MSG_ALL, $company_id);
            $delData = [];
            $delRoom = [];
            $time = time();
            foreach ($list as $value) {
                if ($user_id !== false) {
                    $data['id'] = strval($value['id']);
                    $data['name'] = strval($value['name']);
                    $data['mobile'] = strval($value['mobile']);
                    $data['room_id'] = strval($value['room_id']);
                    $data['room_name'] = strval($value['room_name']);
                    $data['start_time'] = strval($value['start_time']);
                    $data['end_time'] = strval($value['end_time']);
                    $data['on_time'] = strval($value['on_time']);
                    $data['msg_time'] = strval($value['msg_time']);
                    $data['msg_type'] = strval($value['msg_type']);
                    $data['students_time'] = strval($value['students_time']);
                    $data['auxiliary'] = $value['auxiliary'];
                    $delData[] = serialize($data);
                    if (count($delData) > 50) {
                        $redis->ZREM($keyMsg, ...$delData);
                        $delData = [];
                    }
                }
                $key = sprintf(self::COMPANY_ROOM_BLACK_HOUSE, $company_id, $value['room_id']);
                if ($value['end_time'] > $time) {
                    $delRoom[$key][$value['msg_type']] = 1;
                }
            }
            if (!empty($delData) && $user_id !== false) {
                $redis->ZREM($keyMsg, ...$delData);
            }
            if ($user_id === false) {

                $redis->ZREMRANGEBYSCORE($keyMsg, 1, "+inf");
            }
            if (!empty($delRoom)) {
                foreach ($delRoom as $k => $v) {
                    $data = [];
                    foreach ($v as $key => $value) {
                        $data[] = sprintf('msg-%s', $key);
                    }
                    $redis->SADD($k, ...$data);
                }
            }
        });

    }

    /**
     * 消息ID
     * @param $time
     * @param $roomId
     * @return string
     */
    private static function getRoomId($time, $roomId)
    {
        $r = intval($roomId / 1000000);
        // $s = $time - intval($time / 1000000) * 1000000;
        return ($time - $r) * 1000000 + $roomId;
    }
}
