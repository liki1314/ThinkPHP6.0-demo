<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2020-12-31
 * Time: 15:08
 */

namespace app\clientapi\model;


class VideoLog extends Base
{
    protected $table = 'ch_video_log';

    protected $deleteTime = false;


    /**
     * 获取昨天开始和结束时间
     * @return array
     */
    public static function getYesterdaySETime()
    {

        return [
            strtotime(date("Y-m-d 00:00:00", strtotime("-1 day"))),
            strtotime(date("Y-m-d 23:59:59", strtotime("-1 day")))
        ];
    }

}
