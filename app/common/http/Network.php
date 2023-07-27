<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-28
 * Time: 09:23
 */

namespace app\common\http;

use app\admin\model\Room;
use app\common\exception\LiveException;
use think\Exception;
use think\Paginator;

class Network extends WebApi
{

    const NO_ERROR = true;

    public static $Identity = [
        0 => '主讲',
        1 => '助教',
        2 => '学员',
        3 => '直播用户',
        4 => '巡检员',
        10 => '系统管理员',
        11 => '企业管理员',
        12 => '管理员',
    ];


    /**
     * @param $id
     * @param $userid
     * @param int $gettype
     * @return mixed
     * @throws Exception
     */
    public function getNetwork($id, $userid, $gettype = 0)
    {

        $serial = Room::where('id', $id)->value('live_serial');

        $gettype = in_array($gettype, [0, 1, 2, 3, 5, 6, 7]) ? $gettype : 0;

        $res = self::httpGet("/WebAPI/getNetwork", [
            'serial' => $serial,
            'userid' => $userid,
            'gettype' => $gettype
        ]);

        if (isset($res['result']) && $res['result'] > 0 && $res['result'] != 4007) {
            throw new LiveException($res['result']);
        }
        if ($res['result'] < 0 || $res['result'] == 4007) {
            $res['data'] = [];
        }


        return $res['data'];
    }


    /**
     * @param $id
     * @return array
     * @throws LiveException
     */
    public function getMonitorStudents($id)
    {
        $serial = Room::where('id', $id)->value('live_serial');


        $res = self::httpGet("/WebAPI/monitorStudents", [
            'serial' => $serial
        ]);

        if (isset($res['result']) && $res['result'] > 0 && $res['result'] != 4007) {
            throw new LiveException($res['result']);
        }
        if ($res['result'] < 0 || $res['result'] == 4007) {
            $res['data'] = [
                'total' => 0,
                'uf' => 0
            ];
        } else {
            $res['data'] = [
                'total' => $res['data']['onlinetotalnum'],
                'uf' => $res['data']['faulttotalnum']
            ];
        }

        return $res['data'];
    }


    /**
     * 获取教室礼物
     * @param $serial
     * @return mixed
     * @throws LiveException
     */
    public function getGift($serial)
    {
        $res = parent::httpGet("/WebAPI/getusergift", [
            'serial' => $serial,
            'error' => 0,
        ]);

        if ($res['result'] < 0 || $res['result'] == 4007) {
            $res['giftinfo'] = [];
        }

        $data = [];

        foreach ($res['giftinfo'] as $v) {
            $data[$v['receiveid']] = $v['giftnumber'];
        }
        return $data;
    }


    /**
     * 监课报告
     * @param $id
     * @param $keywords
     * @param $page
     * @param int $limit
     * @return mixed
     * @throws LiveException
     */
    public function getMonitor($id, $keywords, $page, $limit = 50)
    {
        $serial = Room::where('id', $id)->value('live_serial');


        $params['serial'] = $serial;
        $params['p'] = $page;
        if (!empty($keywords)) {
            $params['username'] = $keywords;
        }
        $res = self::httpGet("/WebAPI/monitor", $params);

        if (isset($res['result']) && $res['result'] > 0 && $res['result'] != 4007) {
            throw new LiveException(lang(config('live.lives.talk.error_code')[$res['result']] ?? $res['msg'] ?? '服务异常'), $res['result']);
        }

        if ($res['result'] < 0 || $res['result'] == 4007) {
            $res['data']['data'] = [];
            $res['data']['total'] = 0;
        }

        foreach ($res['data']['data'] as $k => $v) {
            $res['data']['data'][$k]['role_name'] = isset(self::$Identity[$v['userroleid']]) ? self::$Identity[$v['userroleid']] : '未知';
        }


        return Paginator::make($res['data']['data'], $limit, $page, $res['data']['total']);
    }
}
