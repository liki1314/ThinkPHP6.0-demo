<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2020-12-31
 * Time: 14:30
 */

namespace app\webapi\controller\v1;


use app\webapi\controller\Base;
use app\webapi\model\VideoLog;
use think\App;

/**
 * 视频统计相关的接口
 * Class Statistics
 * @package app\webapi\controller\v1
 */
class StatisticsVideo extends Base
{

    /**
     * 单日观看日志
     * @param $date
     * @return \think\response\Json
     */
    public function viewLog($date)
    {
        $this->validate(
            $this->param,
            [
                'video_id' => ['integer'],
                'category_id' => ['integer'],
                'date' => ['date'],
            ]
        );
        return $this->success($this->searchList(VideoLog::class));
    }

    /**
     * 视频播放量统计
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function videoView()
    {

        $videoId = isset($this->param['video_id']) ? $this->param['video_id'] : '';
        $dr = isset($this->param['dr']) ? $this->param['dr'] : '';
        $period = isset($this->param['period']) ? $this->param['period'] : '';
        return $this->success(VideoLog::amountOfPlay($videoId, $dr, $period));
    }

    /**
     * 视频播放环境统计
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function device()
    {

        $where = $this->param();

        $browser = [];
        {
            //将浏览器移动端PC端数据进行合并
            VideoLog::getDevice('browser', array_merge(['isMobile' => 1], $where))->each(function ($value) use (&$browser) {
                $browser[$value['browser_name']] = [
                    'browser_name' => $value['browser_name'],
                    'mobile_play_duration' => $value['play_duration'],
                    'mobile_video_view' => $value['video_view'],
                    'mobile_unique_viewer' => $value['unique_viewer'],
                    'mobile_percentage' => $value['percentage'],
                    'pc_play_duration' => 0,
                    'pc_video_view' => 0,
                    'pc_unique_viewer' => 0,
                    'pc_percentage' => 0,
                ];
            });
            VideoLog::getDevice('browser', array_merge(['isMobile' => 0], $where))->each(function ($value) use (&$browser) {
                $key = $value['browser_name'];
                $browser[$key] = [
                    'browser_name' => $key,
                    'mobile_play_duration' => isset($browser[$key]['mobile_play_duration']) ? $browser[$key]['mobile_play_duration'] : 0,
                    'mobile_video_view' => isset($browser[$key]['mobile_video_view']) ? $browser[$key]['mobile_video_view'] : 0,
                    'mobile_unique_viewer' => isset($browser[$key]['mobile_unique_viewer']) ? $browser[$key]['mobile_unique_viewer'] : 0,
                    'mobile_percentage' => isset($browser[$key]['mobile_percentage']) ? $browser[$key]['mobile_percentage'] : 0,
                    'pc_play_duration' => $value['play_duration'],
                    'pc_video_view' => $value['video_view'],
                    'pc_unique_viewer' => $value['unique_viewer'],
                    'pc_percentage' => $value['percentage'],
                ];
            });
        }
        return $this->success([
            'device' => VideoLog::getDevice('is_mobile', $where),
            'operating_system' => VideoLog::getDevice('operating_system', $where),
            'browser' => array_values($browser),
        ]);
    }


    /**
     * dr,start_date,end_date
     */
    private function param()
    {
        $where = [];
        {
            $this->validate(
                $this->param,
                [
                    'dr' => [function ($value) {
                        if (!empty($value) && !in_array($value, VideoLog::$dr))
                            return false;
                        else
                            return true;
                    }],
                    'start_date' => ['date'],
                    'end_date' => ['date'],
                    'video_id' => ["number"],
                ]
            );
            if (isset($this->param['dr']) && !empty(isset($this->param['dr']))) {
                $interval = getDateBetween($this->param['dr']);
                $where['startTime'] = $interval[0];
                $where['endTime'] = $interval[1];
            } else {
                if (isset($this->param['start_date']) && !empty($this->param['start_date']))
                    $where['startTime'] = strtotime($this->param['start_date']);
                if (isset($this->param['end_date']) && !empty($this->param['end_date']))
                    $where['endTime'] = strtotime(date('Y-m-d 23:59:59', strtotime($this->param['end_date'])));
            }
            if (isset($this->param['video_id']) && !empty($this->param['video_id'])) {
                $where['videoId'] = $this->param['video_id'];
            }
        }
        return $where;
    }

    /**
     * 视频观众量统计
     * @return \think\response\Json
     */
    public function visitor()
    {
        $where = $this->param();
        $where['videoId'] = isset($this->param['video_id']) ? $this->param['video_id'] : '';
        return $this->success(VideoLog::getVisitor($where));
    }

    /**
     * 视频播放时长统计
     * @param $id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function duration()
    {
        $where = $this->param();
        return $this->success(VideoLog::getDuration($where));
    }

    /**
     * 视频某个时段播放量统计
     * @param $id
     * @return \think\response\Json
     */
    public function traffic($id)
    {
        return $this->success(VideoLog::getTraffic($this->param(), $id));

    }

    /**
     * 完整
     * @param $video_id
     * @param $viewer_id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function engagement($video_id, $viewer_id)
    {
        //这里通过开始时间进行排序，为后面时间片段进行处理最准备
        $list = VideoLog::where('video_id', $video_id)
            ->where('company_id', request()->company['companyid'])
            ->field(["current_times as start_time", "play_duration", "current_times + play_duration as end_time", "duration"])
            ->where('session_id', $viewer_id)
            ->order('current_times', 'asc')
            ->select();
        if (empty($list)) return $this->success(0);
        $duration = 0;
        $durationPlay = 0;
        //时间片段存储，没有交集的进行从新存储
        $data = [];
        foreach ($list as $k => $v) {
            $duration = $v->duration;
            //取最新的时间片段

            $durationPlay += $v->play_duration;
            /* $index = count($data) - 1;
             if ($k == 0) {
                 $data[] = ['start' => $v->start_time, 'end' => $v->end_time];
             } else {
                 if ($v->start_time > $data[$index]['end']) {
                     //和上一个没有交集，从新开启一个时间段
                     $data[] = [
                         'start' => $v->start_time,
                         'end' => $v->end_time
                     ];
                 } else {
                     //这里处理有交集
                     if ($v->end_time > $data[$index]['end']) {
                         //如果新的时间段结束时间大于以前的就进行扩容
                         $data[$index]['end'] = $v->end_time;
                     }
                 }

             }*/
        }
        foreach ($data as $v) {
            $durationPlay += $v["end"] - $v['start'];
        }
        $res = $duration > 0 ? round($durationPlay / $duration, 2) : round($durationPlay, 2);
        return $this->success($res);
    }
}
