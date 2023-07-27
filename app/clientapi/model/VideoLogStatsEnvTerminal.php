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
 * 视频播放环境统计-终端-is_mobile
 * Class VideoLogStatsPlay
 * @package app\webapi\model
 */
class VideoLogStatsEnvTerminal extends Base
{
    protected $deleteTime = false;

    /*
    * date             日期
    * terminal         类型（pc,Mobile）
    * play_total       播放总数量
    * audience_total   观众数量
    * duration_total   总时长
    * duration_avg     平均时长
    * duration_audience_avg 人均时长
    * company_id
    * */

    /**
     * 获取昨天的sql语句
     * @return mixed
     */
    public static function getYesterdaySql()
    {
        // 指令输出
        $subQuery = VideoLog::field([
            'is_mobile',
            "company_id",
            "session_id",

            "COUNT(id) as num",
            'SUM(flow_size) as traffic',
            'SUM(play_duration) as duration_total'])
            ->where(function ($query) {
                $query->whereBetween('create_time', VideoLog::getYesterdaySETime());
            })
            ->group("company_id,is_mobile,session_id")
            ->buildSql();

        $date = sprintf("%s as date", strtotime(date("Y-m-d 01:00:00", strtotime("-1 day"))));
        $sql = Db::table($subQuery . 'll')->field([
            $date,
            "ll.company_id",
            "ll.is_mobile as terminal",

            "SUM(ll.num) as play_total",
            "COUNT(ll.session_id) as audience_total",
            "SUM(ll.duration_total) as duration_total",
            "SUM(ll.duration_total)/SUM(ll.num) as duration_avg",
            "SUM(ll.duration_total)/COUNT(ll.session_id) as duration_audience_avg"])
            ->group("ll.company_id,ll.is_mobile")
            ->buildSql();

        return $sql;
    }

}
