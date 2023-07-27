<?php
declare (strict_types = 1);

namespace app\webapi\model;
use think\exception\ValidateException;
class PlayVideo extends Base
{
    protected $table = 'ch_play_video';

    protected $deleteTime = false;
    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        validate(
            [
                'video' => 'unique:' . get_class($model) . ',play_id^video_id'
            ],
            [
                'video' => lang('video_play_exists'),
            ]
        )->check($model->toArray());

    }

    /**
     * 统计该播放列表下的视频条数
     * @param $list
     * @return mixed
     */
    public function countVideo($list)
    {
        if (empty($list)) return $list;

        $count = self::withSearch(['play_id'])
            ->field('count(video_id) as video_count,play_id')
            ->whereIn('play_id', $list->column('id'))
            ->group('play_id')
            ->select();


        foreach ($list as &$value) {
            foreach ($count as $row) {
                if ($row['play_id'] == $value['id']) {
                     $value['video_count']  = $row['video_count'];
                }
            }

            if(!$value['video_count'])  $value['video_count'] = 0;
        }


        return $list;
    }


    /**
     * 移动视频到播放列表
     * @param $params
     * @play_id  播放列表ID
     * @id   视频ID
     */
    public function move($play_id,$id)
    {

        if (!Play::where('id', $play_id)->find()) {
            throw new ValidateException(lang('play_not_exists'));
        }

        if (!Video::where('id', $id)->find()) {
            throw new ValidateException(lang('video_not_exists'));
        }

        if ($this->where(['video_id' => $id, 'play_id' => $play_id])->find()) {
            throw new ValidateException(lang('video_play_exists'));
        }

        $params            = [];
        $params['play_id'] = $play_id;
        $params['video_id'] = $id;

        return $this->save($params);
    }


    /**
     * 将视频从播放列表移除
     * @play_id  播放列表ID
     * @id   视频ID
     */
    public function remove($play_id,$id)
    {

        if (!$this->where(['video_id' => $id, 'play_id' => $play_id])->find()) {
            throw new ValidateException(lang('play_removed'));
        }

        return $this->where(['video_id' => $id, 'play_id' => $play_id])->delete();
    }
}
