<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-28
 * Time: 09:19
 */

namespace app\admin\controller;


use app\admin\model\Room;
use app\admin\model\RoomAccessRecord;
use app\common\http\Chat;
use app\common\http\Network;
use app\common\http\Record;
use app\common\facade\Excel;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

class RoomNetwork extends Base
{

    /**
     * 课堂报告
     * check  time
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function report($course_id, $id)
    {
        $data = (new RoomAccessRecord)->getReportByRoomId($id);
        return $this->success($data);
    }

    /**change
     * 获取某人网络情况
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function getNetwork($course_id, $id)
    {

        $this->validate(
            $this->param,
            ['user_id' => ['require']]
        );

        return $this->success((new Network())->getNetwork($id, $this->param['user_id'], $this->param['gettype']));

    }

    /**
     * 监课报告
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function monitor($course_id, $id)
    {
        $keywords = $this->param['keywords'] ?? '';
        $page = $this->param['page'] ?? 1;
        $rows = $this->param['rows'] ?? 50;
        return $this->success((new Network())->getMonitor($id, $keywords, $page, $rows));
    }

    /**
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function monitorStudents($course_id, $id)
    {
        return $this->success((new Network())->getMonitorStudents($id));
    }

    /**
     * 录课数据
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function record($course_id, $id)
    {
        return $this->success((new Record())->getList($id));
    }

    /**
     * 删除录课报告
     * @param $course_id
     * @param $id
     * @param $record_id
     * @return \think\response\Json
     */
    public function recordDel($course_id, $id, $record_id)
    {
        (new Record())->del($record_id);
        return $this->success();
    }

    /**
     * 获取教室下面聊天记录
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function chat($course_id, $id)
    {
        $handler = Cache::store('redis')->handler();
        $handler->SETEX(sprintf("chat-%s-%s", $course_id, $id), 60 * 10, 1);
        return $this->success((new Chat())->getList($id, $this->page, $this->rows));
    }

    /**
     * 聊天记录进行导出
     * @param $course_id
     * @param $id
     * @return void
     * @throws \think\Exception
     */
    public function chatExport($course_id, $id)
    {
        $handler = Cache::store('redis')->handler();
        $token = $handler->GET(sprintf("chat-%s-%s", $this->param['course_id'], $this->param['id']));
        if ($token === false) throw new ValidateException(lang('chat_err'));

        $data = [];
        $res = (new Chat())->getList($id, false);
        foreach ($res as $value) {
            $data[] = [
                'ts' => $value['ts'],
                'nickname' => $value['nickname'],
                'msg' => $value['msg'],
                'role_name' => $value['role_name'],
            ];
        }
        Excel::export(array_values($data), ["发送时间", "名称", "内容", "角色"], sprintf("chat-%s-%s", $this->param['course_id'], $id))
            ->header(['Access-Control-Allow-Origin' => '*'])
            ->send();

    }

    /**
     * 课节统计数量
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function statisticalNumber($course_id, $id)
    {

        return $this->success(RoomAccessRecord::statisticalNumber($id));
    }

    /**
     * 课节统计每5分钟统计
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function statisticalInterval($course_id, $id)
    {
        $info = Room::field(['starttime', 'endtime', 'id', 'live_serial'])->findOrFail($id)->toArray();

        $time = time();
        $time = $info['endtime'] > $time ? $time : $info['endtime'];
        $teacherTime = Db::table('saas_room_timeinfo')
            ->field(["CASE endtime WHEN 0 THEN $time ELSE endtime END as endtime", "starttime"])
            ->where('serial', $info['live_serial'])
            ->where('starttime', '>', 0)
            ->order('starttime')
            ->select()
            ->toArray();
        $count = count($teacherTime);
        if (empty($count)) {
            $teacherTime = [['starttime' => $info['starttime'], 'endtime' => $info['endtime']]];
            $count = 1;
        }
        $startTime = $count > 0 ? $teacherTime[0]['starttime'] : $info['starttime'];
        $endtime = $count > 0 ? $teacherTime[$count - 1]['endtime'] : $info['endtime'];
        //这里生产每隔5分钟的时间轴
        $intervalArray = [];
        $interval = 0;
        //形成每5分钟一个间隔的时间轴，初始参数为0
        $timeInterval = intval(ceil(($endtime - $startTime) / 60 / 5) * 5);
        while ($interval <= $timeInterval) {
            $intervalArray[$interval] = 0;
            $interval += 5;
        }

        $loginTime = RoomAccessRecord::field(['user_account_id', "CASE outtime WHEN 0 THEN $time ELSE outtime END as outtime", 'entertime'])
            ->where("room_id", $info['id'])
            ->where('entertime', '<', $endtime)
            ->where(function ($query) use ($startTime) {
                $query->whereOr('outtime', '>', $startTime);
                $query->whereOr('outtime', 0);
            })
            ->order('entertime')
            ->select();


        foreach ($teacherTime as $value) {
            $c = count($loginTime);
            for ($i = 0; $i < $c; $i++) {
                //有交集
                if ($loginTime[$i]['entertime'] <= $value['endtime'] && $loginTime[$i]['outtime'] >= $value['starttime']) {

                    $sTime = $loginTime[$i]['entertime'] > $value['starttime'] ? $loginTime[$i]['entertime'] : $value['starttime'];
                    $eTime = $loginTime[$i]['outtime'] > $value['endtime'] ? $value['endtime'] : $loginTime[$i]['outtime'];
                    //获取当前人的在时间抽的开始点
                    $intervalStart = intval(ceil(($sTime - $startTime) / 60 / 5) * 5);
                    //获取当前人的在时间抽的结束点
                    $intervalEnd = intval(ceil(($eTime - $startTime) / 60 / 5) * 5);
                    //在这2个点之间进行累加
                    while ($intervalStart <= $intervalEnd) {
                        $intervalArray[$intervalStart] += 1;
                        $intervalStart += 5;
                    }

                }
            }
        }


        return $this->success(array_values($intervalArray));

    }
}
