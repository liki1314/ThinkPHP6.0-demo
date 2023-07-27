<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2020-12-31
 * Time: 15:08
 */

namespace app\webapi\model;


use think\Exception;
use think\facade\Db;

class VideoLog extends Base
{
    protected $table = 'ch_video_log';

    protected $deleteTime = false;

    /*
     * date     日期
     * video_id  视频ID
     * user_id   用户ID
     * terminal 终端（PC,Mobile）
     * system   系统（Linux,W10,W7,MAC）
     * device   设备（google浏览器，火狐浏览器,APPv.1,APPv.2）
     * count    总数
     * duration 总时长  （s）
     * min_duration 最小时长  （s）
     * max_duration 最大时长  （s）
     * avg_duration 平均时长  （s）
     * traffic  总流量 （b）
     * min_traffic  最小流量 （b）
     * max_traffic  最大流量 （b）
     * avg_traffic  平均流量 （b）
     * */

    //period的值受限于dr的值
    //dr的值:today，yesterday，this_week，last_week，7days时，period只能为daily，
    //dr的值:this_month，last_month时，period只能为daily、weekly
    public static $dr = [
        0 => 'today',        //今天
        1 => 'yesterday',    //昨天
        2 => 'this_week',    //本周
        3 => 'last_week',    //上周
        4 => '7days',        //最近7天
        5 => 'this_month',   //本月
        6 => 'last_month',   //上个月
        7 => 'this_year',    //今年
        8 => 'last_year'    //去年
    ];
    public static $period = [
        'daily',    //按日显示
        'weekly',   //按周显示
        'monthly'  //按月显示
    ];


    public static $fieldList = [
        'id as play_id', 'video_id', 'play_duration', 'stay_duration',
        'current_times', 'duration', 'flow_size', 'session_id',
        'ip_address', 'country', 'province', 'city', 'isp',
        'referer', 'user_agent', 'operating_system', 'browser',
        'is_mobile', 'view_source', 'create_time', 'update_time',
        "CURRENT_DATE as current_day", "DATE_FORMAT(now(), '%H') as current_hour"
    ];


    public function searchDefaultAttr($query, $value, $param)
    {

        //获取查询的时间区间，并且进行验证
        $startTime = strtotime($param['date']);
        $endTime = strtotime(date("Y-m-d 23:59:59", $startTime));
        $query->field(self::$fieldList)
            ->where('company_id', request()->company['companyid'])
            ->whereBetween('create_time', [$startTime, $endTime])->where(function ($query) use ($param) {

                if (isset($param['video_id'])) {
                    $query->where('video_id', $param['video_id']);
                }

                if (!isset($param['video_id']) && isset($param['category_id'])) {
                    $query->whereIn('video_id', function ($query) use ($param) {
                        $query->table('ch_video')->where('category_id', $param['category_id'])->field('id');
                    });
                }
                if (isset($param['session_id'])) {
                    $query->where('session_id', $param['session_id']);
                }
            });
    }

    /**
     * 获取pc和移动端统计
     * @param $videoId
     * @param $interval
     * @param $type
     * @return array
     * @throws Exception
     */
    public static function amountOfPlay($videoId, $interval, $type)
    {

        if ($type == self::$period[0]) {
            //日维度
            if (!in_array($interval, self::$dr)) {
                //默认最近7天
                throw new Exception(lang("amount_of_play_error"));
            }

            $field = "FROM_UNIXTIME(create_time,'%Y%m%d') as date";
            $group = "FROM_UNIXTIME(create_time, '%Y-%m-%d')";


        } elseif ($type == self::$period[1]) {
            //周维度
            if (!in_array($interval, [self::$dr[5], self::$dr[6], self::$dr[7], self::$dr[8]])) {
                //默认本月
                throw new Exception(lang("amount_of_play_error"));
            }
            $field = "FROM_UNIXTIME(create_time,'%Y%u') as date";
            $group = "FROM_UNIXTIME(create_time,'%Y-%u')";
        } elseif ($type == self::$period[2]) {
            //月维度
            if (!in_array($interval, [self::$dr[7], self::$dr[8]])) {
                //默认今年
                throw new Exception(lang("amount_of_play_error"));
            }
            $field = "FROM_UNIXTIME(create_time,'%Y%m') as date";
            $group = "FROM_UNIXTIME(create_time, '%Y-%m')";
        } else {
            $field = "FROM_UNIXTIME(create_time,'%Y%m%d') as date";
            $group = "FROM_UNIXTIME(create_time, '%Y-%m-%d')";
            if (!in_array($interval, self::$dr)) {
                //默认最近7天
                $interval = self::$dr[4];
            }
        }
        $interval = getDateBetween($interval);
        $dataMobile = self::dataKVCount($videoId, $field, 1, $interval, $group);
        $dataOut = self::dataKVCount($videoId, $field, 0, $interval, $group);
        $data = [];
        foreach ($dataMobile as $value) {
            $data[$value['date']] = [
                'current_time' => $value->date,
                'mobile_video_view' => $value->count,
                'pc_video_view' => 0,
            ];
        }
        foreach ($dataOut as $value) {
            $data[$value['date']] = [
                'current_time' => $value->date,
                'pc_video_view' => $value->count,
                'mobile_video_view' => isset($data[$value['date']]['mobile_video_view']) && !empty($data[$value['date']]['mobile_video_view']) ? $data[$value['date']]['mobile_video_view'] : 0,
            ];
        }
        krsort($data);
        return array_values($data);
    }

    /**
     * 获取时间区间里面统计
     * @param $videoId
     * @param $field
     * @param $isMobile
     * @param $interval
     * @param $group
     * @return mixed
     */
    public static function dataKVCount($videoId, $field, $isMobile, $interval, $group)
    {
        $list = self::field([$field, "COUNT(id) as count", "create_time"])
            ->where('company_id', request()->company['companyid'])
            ->where(function ($query) use ($videoId) {
                if (!empty($videoId)) {
                    $query->where('video_id', $videoId);
                }
            })
            ->where('is_mobile', $isMobile)
            ->whereBetween('create_time', $interval)
            ->group($group)
            ->select();
        return $list;
    }


    /**
     * 视频播放环境统计
     * @param $field
     * @param array $where
     * @return \think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getDevice($field, $where = [])
    {

        $fields = [
            'operating_system' => 'operate_system_name',
            'browser' => 'browser_name',
            'is_mobile' => 'device_name',
        ];

        $subQuery = self::field([
            sprintf("%s AS %s", $field, $fields[$field]),
            'session_id',
            'COUNT(1) AS video_view',
            'SUM(duration) as play_duration'])
            ->where('company_id', request()->company['companyid'])
            ->where(function ($query) use ($where) {
                if (isset($where['startTime']) && !empty($where['startTime'])) {
                    $query->where('create_time', '>', $where['startTime']);
                }
                if (isset($where['endTime']) && !empty($where['endTime'])) {
                    $query->where('create_time', '<', $where['endTime']);
                }
                if (isset($where['isMobile'])) {
                    $query->where('is_mobile', $where['isMobile']);
                }
            })
            ->group(sprintf("%s,session_id", $field))
            ->buildSql();


        $data = Db::table($subQuery . 'll')->field([
            sprintf("ll.%s", $fields[$field]),
            "COUNT(ll.session_id) as unique_viewer",
            "SUM(ll.video_view) as video_view",
            "round(SUM(ll.play_duration / 3600),2) as play_duration"])
            ->group(sprintf("ll.%s", $fields[$field]))
            ->select();

        $total = 0;
        foreach ($data as $value) {
            $total += $value['video_view'];
        }
        $data->each(function ($value) use ($total, $field) {
            $value['percentage'] = round($value['video_view'] / $total * 100, 2);

            $value['unique_viewer'] = intval($value['unique_viewer']);
            $value['video_view'] = intval($value['video_view']);
            $value['play_duration'] = $value['play_duration'] + 0;
            if ($field == "is_mobile") {
                if ($value['device_name'] == 1) {
                    $value['device_name'] = '移动端';
                } else {
                    $value['device_name'] = 'PC端';
                }
            }
            return $value;
        });


        return $data;
    }

    /**
     * 视频观众量统计
     * @param $where
     * @return array
     */
    public static function getVisitor($where)
    {
        $data = [];
        self::field([
            'is_mobile AS device_name',
            'COUNT(1) as count',
            "FROM_UNIXTIME(create_time, '%Y-%m-%d') as date"])
            ->where('company_id', request()->company['companyid'])
            ->where(function ($query) use ($where) {
                if (isset($where['startTime']) && !empty($where['startTime'])) {
                    $query->where('create_time', '>', $where['startTime']);
                }
                if (isset($where['endTime']) && !empty($where['endTime'])) {
                    $query->where('create_time', '<', $where['endTime']);
                }
                if (isset($where['videoId']) && !empty($where['videoId'])) {
                    $query->where('video_id', $where['videoId']);
                }
            })
            ->group("FROM_UNIXTIME(create_time, '%Y-%m-%d'),is_mobile")
            ->select()->each(function ($value) use (&$data) {
                $data[$value->date]['date'] = $value->date;
                if ($value->device_name == 1) {
                    //移动端
                    $data[$value->date]['pc_unique_viewer'] = isset($data[$value->date]['pc_unique_viewer']) ? $data[$value->date]['pc_unique_viewer'] : 0;
                    $data[$value->date]['mobile_unique_viewer'] = $value->count;
                } else {
                    //PC端
                    $data[$value->date]['pc_unique_viewer'] = $value->count;
                    $data[$value->date]['mobile_unique_viewer'] = isset($data[$value->date]['mobile_unique_viewer']) ? $data[$value->date]['mobile_unique_viewer'] : 0;
                }
                !isset($data[$value->date]['total_unique_viewer']) && $data[$value->date]['total_unique_viewer'] = 0;
                $data[$value->date]['total_unique_viewer'] += $value->count;

            });
        return array_values($data);
    }


    /**
     * 视频某个时段播放量统计
     * @param $where
     * @param $id
     * @return array
     */
    public static function getTraffic($where, $id)
    {
        $data = [];

        VideoLog::field([
            'is_mobile AS device_name',
            'sum(flow_size) as flow_size',
            "FROM_UNIXTIME(create_time, '%Y-%m-%d') as date"])
            ->where('company_id', request()->company['companyid'])
            ->where('video_id', $id)
            ->where(function ($query) use ($where) {
                if (isset($where['startTime']) && !empty($where['startTime'])) {
                    $query->where('create_time', '>', $where['startTime']);
                }
                if (isset($where['endTime']) && !empty($where['endTime'])) {
                    $query->where('create_time', '<', $where['endTime']);
                }
            })
            ->group("FROM_UNIXTIME(create_time, '%Y-%m-%d'),is_mobile")
            ->select()->each(function ($value) use (&$data) {
                $data[$value->date]['date'] = $value->date;
                if ($value->device_name == 1) {
                    //移动端
                    $data[$value->date]['pc_flow_size'] = isset($data[$value->date]['pc_flow_size']) ? $data[$value->date]['pc_flow_size'] : 0;
                    $data[$value->date]['mobile_flow_size'] = $value->flow_size + 0;
                } else {
                    //PC端
                    $data[$value->date]['pc_flow_size'] = $value->flow_size + 0;
                    $data[$value->date]['mobile_flow_size'] = isset($data[$value->date]['mobile_flow_size']) ? $data[$value->date]['mobile_flow_size'] : 0;
                }
                !isset($data[$value->date]['total_flow_size']) && $data[$value->date]['total_flow_size'] = 0;
                $data[$value->date]['total_flow_size'] += $value->flow_size;

            });
        return array_values($data);
    }

    /**
     * 视频播放时长统计
     * @param $where
     * @return \think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getDuration($where)
    {
        $data = [];
        $subQuery = self::field([
            "FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date",
            'is_mobile AS device_name',
            'session_id',
            "sum( play_duration ) AS play_duration",
            "COUNT(1) as num"])
            ->where('company_id', request()->company['companyid'])
            ->where(function ($query) use ($where) {
                if (isset($where['startTime']) && !empty($where['startTime'])) {
                    $query->where('create_time', '>', $where['startTime']);
                }
                if (isset($where['endTime']) && !empty($where['endTime'])) {
                    $query->where('create_time', '<', $where['endTime']);
                }
                if (isset($where['videoId']) && !empty($where['videoId'])) {
                    $query->where('video_id', $where['videoId']);
                }
            })
            ->group("FROM_UNIXTIME( create_time, '%Y-%m-%d' ),`is_mobile`,session_id")
            ->buildSql();

        Db::table($subQuery . 'll')->field([
            "ll.date as current_day",
            "ll.device_name",
            "SUM(ll.play_duration) as play_duration",
            "AVG(ll.play_duration) as play_duration_person_avg",
            "SUM(ll.play_duration)/SUM(num) as play_duration_video_avg"])
            ->group("ll.date, ll.device_name")
            ->select()
            ->each(function ($value) use (&$data) {
                $key = $value['current_day'];
                $data[$key]['current_day'] = $key;
                if ($value['device_name'] == 1) {
                    //移动端
                    //端播放时长，单位秒
                    $data[$key]['mobile_play_duration'] = intval($value['play_duration']);
                    //PC端视频平均播放时长，单位秒
                    $data[$key]['mobile_play_duration_video_avg'] = round($value['play_duration_video_avg'], 2);
                    //PC端人均播放时长
                    $data[$key]['mobile_play_duration_person_avg'] = round($value['play_duration_person_avg'], 2);
                    //数据初始化操作
                    $data[$key]['pc_play_duration'] = isset($data[$key]['pc_play_duration']) ? $data[$key]['pc_play_duration'] : 0;
                    $data[$key]['pc_play_duration_video_avg'] = isset($data[$key]['pc_play_duration_video_avg']) ? $data[$key]['pc_play_duration_video_avg'] : 0;
                    $data[$key]['pc_play_duration_person_avg'] = isset($data[$key]['pc_play_duration_person_avg']) ? $data[$key]['pc_play_duration_person_avg'] : 0;
                } else {
                    //PC端 同上
                    $data[$key]['pc_play_duration'] = intval($value['play_duration']);
                    $data[$key]['pc_play_duration_video_avg'] = round($value['play_duration_video_avg']);
                    $data[$key]['pc_play_duration_person_avg'] = round($value['play_duration_person_avg']);
                    $data[$key]['mobile_play_duration'] = isset($data[$key]['mobile_play_duration']) ? $data[$key]['mobile_play_duration'] : 0;
                    $data[$key]['mobile_play_duration_video_avg'] = isset($data[$key]['mobile_play_duration_video_avg']) ? $data[$key]['mobile_play_duration_video_avg'] : 0;
                    $data[$key]['mobile_play_duration_person_avg'] = isset($data[$key]['mobile_play_duration_person_avg']) ? $data[$key]['mobile_play_duration_person_avg'] : 0;
                }

            });

        return array_values($data);
    }
}
