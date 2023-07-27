<?php

declare(strict_types=1);

namespace app\webapi\controller\v1;
use app\webapi\controller\Base;
use app\webapi\model\Play as PlayModel;
use app\webapi\model\PlayVideo as PlayVideoModel;
use think\helper\Arr;
use app\webapi\validate\Play as ValidatePlay;

class Play extends Base
{
    /**
     * 列表
     * @return \think\response\Json
     */
    public function index()
    {
        $playVideoModel = new PlayVideoModel;
        return $this->success($playVideoModel->countVideo($this->searchList(PlayModel::class)));
    }

    /**
     * 新建播放列表
     * @return \think\response\Json
     */
    public function save()
    {
        $this->validate($this->param, ValidatePlay::class);
        $model = PlayModel::create($this->param);

        return $this->success($model);
    }

    public function read($id)
    {
        //
    }

    /**
     * 更新播放列表
     * @param $id
     */
    public function update($id)
    {
        $this->validate($this->param, ValidatePlay::class);

        $model = PlayModel::findOrFail($id);

        $model->save(Arr::only($this->param,['title','tag','desc']));

        return $this->success($model);

    }


    /**
     * @param $id
     */
    public function delete($id)
    {

    }


    /**
     * 移动视频到播放列表
     * @param $play_id
     * @param $id
     */
    public function move($play_id,$id)
    {

        $playVideoModel = new PlayVideoModel;

        $playVideoModel->move($play_id,$id);

        return $this->success();
    }


    /**
     * 将视频从播放列表移除
     * @param $play_id
     * @param $id
     * @return \think\response\Json
     */
    public function remove($play_id,$id)
    {

        $playVideoModel = new PlayVideoModel;

        $playVideoModel->remove($play_id,$id);

        return $this->success();
    }



}
