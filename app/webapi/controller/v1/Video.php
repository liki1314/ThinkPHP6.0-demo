<?php

declare(strict_types=1);

namespace app\webapi\controller\v1;

use app\webapi\controller\Base;
use app\webapi\model\Video as VideoModel;
use app\common\http\HwCloud;
use app\webapi\validate\Video as VideoValidate;
use think\exception\ValidateException;
use think\helper\Arr;
use app\webapi\model\Category;
use app\webapi\model\Watermark;
use app\webapi\validate\VideoCover;

class Video extends Base
{

    /**
     * 视频列表信息查询
     *
     */
    public function index()
    {
        $rule = [
            'id' => ['require']
        ];

        $message = [

            'id.require' => 'ids_epmty',
        ];

        $this->validate($this->param, $rule, $message);

        $videoModel = new VideoModel;

        $data = $videoModel->getVideo(explode(',', $this->param['id']), isset($this->param['filter']) ? $this->param['filter'] : '');

        return $this->success($data);
    }


    public function save()
    {
        //
    }

    public function read($id)
    {
        //
    }

    public function update($id)
    {
        //
    }

    /**
     * 删除视频
     * @param $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $model = VideoModel::findOrFail($id);

        $model->delete();

        return $this->success();
    }

    // 远程批量上传视频
    public function remote()
    {
        $this->validate($this->param, VideoValidate::class);

        $upload_metadatas = $data = [];
        foreach ($this->param['files'] as $file) {
            $file_type = strtoupper(trim(strrchr($file['url'], '.'), '.'));
            if (!in_array($file_type, VideoModel::TYPE)) {
                throw new ValidateException(lang('error_video_type'));
            }

            $upload_metadatas[] = [
                'url' => $file['url'],
                'title' => $file['title'],
                'video_type' => $file_type,
                'template_group_name' => config('vod.default_template_group_name'),
                'category_id' => empty($this->param['category_id']) ? -1 : Category::findOrFail($this->param['category_id'])['category_id'],
            ];

            $data[$file['url']] = $file + Arr::except($this->param, ['files']);
        }

        $upload_assets = HwCloud::httpJson('asset/upload_by_url', ['upload_metadatas' => $upload_metadatas])['upload_assets'];

        foreach ($upload_assets as $file) {
            $data[$file['url']]['asset_id'] = $file['asset_id'];
        }

        $model = new VideoModel();

        $models = $model->transaction(function () use ($model, $data) {
            $models = $model->saveAll($data);
            if (isset($this->param['watermark'])) {
                $models->each(function ($item) {
                    $item->watermark()->save(['url' => $this->param['watermark'], 'location' => $this->param['watermark_location'] ?? 0]);
                });
            }
            return $models;
        });

        return $this->success($models);
    }

    // 上传视频水印
    public function watermark()
    {
        $this->validate(
            $this->param,
            [
                'image|' . lang('watermark') => ['require', 'image'],
                'category_id|' . lang('category') => ['integer','>:0', 'exist' => Category::class . ',parent_id=0'],
                'location|' . lang('watermark_location') => 'in:0,1,2,3,4',
            ],

            [
                'category_id.integer'=>'category_id_type_error',
                'category_id.exist'=>'category_id_exist_error',
            ]
        );

        $model = Watermark::create($this->param);

        return $this->success($model);
    }

    // 上传多个视频的预览图
    public function cover($vids = [], $cataids = [])
    {
        $this->validate($this->param, VideoCover::class . '.image');

        (new VideoModel())->uploadCover($this->param['image'], $vids, $cataids);

        return $this->success();
    }

    // 上传多个视频预览图的url
    public function coverUrl($vids = [], $cataids = [])
    {
        $this->validate($this->param, VideoCover::class . '.url');

        (new VideoModel())->uploadCover($this->param['file_url'], $vids, $cataids);

        return $this->success();
    }

    public function encrypt($video_id)
    {
        $model = VideoModel::findOrFail($video_id);

        HwCloud::httpJson(
            'asset/process',
            [
                'asset_id' => $model['asset_id'],
                'template_group_name' => config('vod.encrypt_template_group_name'),
                'auto_encrypt' => 1,
            ]
        );

        return $this->success();
    }
}
