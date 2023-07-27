<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2020-12-31
 * Time: 15:08
 */

namespace app\clientapi\model;


use think\facade\Db;

/**
 * 视频播放量统计 or  视频观众量统计 or 视频播放时长统计
 * Class VideoLogStatsPlay
 * @package app\webapi\model
 */
class VideoLogStatsPlay extends Base
{
    protected $deleteTime = false;

    /*
     * id
     * date                          日期
     * video_id                      视频ID
     * terminal                      终端
     * play_num                      播放数量(观看多少次)
     * audience_total                人数（有多少人来观看）
     * traffic_total                 流量
     * duration_total                播放总时长
     * duration_avg                  播放平均时长
     * duration_audience_avg         人均播放时长
     * company_id
     * */

    /**
     * 获取昨天的sql语句
     * @return mixed
     */
    public static function getYesterdaySql()
    {
        $subQuery = VideoLog::field([
            'company_id',
            "video_id",
            'is_mobile',
            "session_id",

            "COUNT(id) as num",
            'SUM(flow_size) as traffic',
            'SUM(play_duration) as duration_total'])
            ->where(function ($query) {
                $query->whereBetween('create_time', VideoLog::getYesterdaySETime());
            })
            ->group("company_id,video_id,is_mobile,session_id")
            ->buildSql();

        $date = sprintf("%s as date", strtotime(date("Y-m-d 01:00:00", strtotime("-1 day"))));
        $sql = Db::table($subQuery . 'll')->field([
            $date,
            "ll.company_id",
            "ll.video_id",
            "is_mobile as terminal",

            "SUM(ll.num) as play_num",      //视频观众量统计
            "COUNT(ll.session_id) as audience_total",       //视频播放时长统计
            "SUM(ll.traffic) as traffic_total", //视频某个时段播放量统计
            "SUM(ll.duration_total) as duration_total", //视频播放量统计
            "SUM(ll.duration_total)/SUM(ll.num) as duration_avg",   //视频播放时长统计
            "SUM(ll.duration_total)/COUNT(ll.session_id) as duration_audience_avg"])//视频播放时长统计
        ->group("ll.company_id,ll.video_id, ll.is_mobile")
            ->buildSql();

        return $sql;
    }
}
