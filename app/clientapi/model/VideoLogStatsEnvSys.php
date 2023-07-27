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
 * 视频播放环境统计-系统-operating_system
 * Class VideoLogStatsPlay
 * @package app\webapi\model
 */
class VideoLogStatsEnvSys extends Base
{
    protected $deleteTime = false;

    /*
     * id
     * name             系统
     * date             日期
     * play_total       播放总数量
     * audience_total   观众数量
     * duration_total   总时长
     * duration_avg     平均时长
     * audience_total   观众数量
     * */


    /**
     * 获取昨天的sql语句
     * @return mixed
     */
    public static function getYesterdaySql()
    {
        // 指令输出
        $subQuery = VideoLog::field([
            "company_id",
            'operating_system',
            "session_id",

            "COUNT(id) as num",
            'SUM(flow_size) as traffic',
            'SUM(play_duration) as duration_total'])
            ->where(function ($query) {
                $query->whereBetween('create_time', VideoLog::getYesterdaySETime());
            })
            ->group("company_id,operating_system,session_id")
            ->buildSql();
        $date = sprintf("%s as date", strtotime(date("Y-m-d 01:00:00", strtotime("-1 day"))));
        $sql = Db::table($subQuery . 'll')->field([
            $date,
            "ll.company_id",
            "ll.operating_system as name",

            "SUM(ll.num) as play_total",
            "COUNT(ll.session_id) as audience_total",
            "SUM(ll.duration_total) as duration_total",
            "SUM(ll.duration_total)/SUM(ll.num) as duration_avg",
            "SUM(ll.duration_total)/COUNT(ll.session_id) as duration_audience_avg"])
            ->group("ll.company_id,ll.operating_system")
            ->buildSql();

        return $sql;
    }
}
