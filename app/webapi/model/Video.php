<?php

declare(strict_types=1);

namespace app\webapi\model;

use app\common\http\HwCloud;
use think\file\UploadedFile;
use app\common\http\Base as BaseHttpClient;
use app\common\service\Bytes;
use think\exception\ValidateException;

class Video extends Base
{
    protected $globalScope = ['companyId'];

    /** 拉取的音视频文件类型 */
    const TYPE = [
        'MP4', 'TS', 'MOV', 'MXF', 'MPG', 'FLV', 'WMV', 'AVI', 'M4V', 'F4V', 'MPEG', '3GP', 'ASF', 'MKV', //视频文件
        'MP3', 'OGG', 'WAV', 'WMA', 'APE', 'FLAC', 'AAC', 'AC3', 'MMF', 'AMR', 'M4A', 'M4R', 'WV', 'MP2', //音频文件
    ];

    public function watermark()
    {
        return $this->morphMany(Watermark::class, null, '1');
    }

    /**
     * 查询华为云视频列表信息
     * @param $ids
     * @param $filter
     */
    public function getVideo($ids, $filter)
    {
        $asset_id_list = $this->selectOrFail($ids)->toArray();

        $queryString = '';
        $video_id    = [];

        foreach ($asset_id_list as $item) {
            $queryString .= 'asset_id=' . $item['asset_id'] . '&';
            $video_id[$item['asset_id']] = $item['id'];
        }

        $queryString = trim($queryString, '&');

        $api_resopose = HwCloud::httpGet('asset/info?' . $queryString);

        return $this->filterVideo($api_resopose, $filter, $video_id);
    }


    /**
     * 根据过滤规则做数据筛选
     * @param $videoData
     * @param $filter
     */
    private function filterVideo($videoData, $filter, $video_id)
    {
        $result = [];

        $show_content = $filter ? explode(',', $filter) : [];

        foreach ($videoData['asset_info_array'] as $key => $value) {
            $item = [];
            $item['id'] = isset($video_id[$value['asset_id']]) ? $video_id[$value['asset_id']] : null;

            //基础信息
            if (!$show_content || in_array('basic', $show_content)) {
                $item['basic']['title'] = $value['base_info']['title'];
                $item['basic']['description'] = $value['base_info']['description'];
                $item['basic']['duration'] = $value['base_info']['meta_data']['duration'];
                $item['basic']['cover'] = isset($value['base_info']['cover_info_array'][0]['cover_url']) ? $value['base_info']['cover_info_array'][0]['cover_url'] : '';
                $item['basic']['creation_time'] = $value['base_info']['create_time'];
                $item['basic']['update_time'] = $value['base_info']['last_modified'];
                $item['basic']['size'] = $value['base_info']['meta_data']['video_size'];
                $item['basic']['status'] = $value['status'];
                $item['basic']['category_id'] = $value['base_info']['category_id'];
                $item['basic']['category_name'] = $value['base_info']['category_name'];
                $item['basic']['tags'] = $value['base_info']['tags'];
                $item['basic']['uploader'] = '';
            }

            //元数据
            if (in_array('meta', $show_content)) {
                $item['meta']['size'] = $value['base_info']['meta_data']['video_size'];
                $item['meta']['format'] = $value['base_info']['video_type'];
                $item['meta']['duration'] = $value['base_info']['meta_data']['duration'];
                $item['meta']['bitrate'] = $value['base_info']['meta_data']['bit_rate'];
                $item['meta']['fps'] =  $value['base_info']['meta_data']['frame_rate'];
                $item['meta']['height'] = $value['base_info']['meta_data']['hight'];
                $item['meta']['width'] = $value['base_info']['meta_data']['width'];
                $item['meta']['codec'] = $value['base_info']['meta_data']['codec'];
            }

            //转码信息
            if (in_array('transcode', $show_content)) {

                $item['transcode'] = [];
                if (isset($value['play_info_array']) && $value['play_info_array']) {
                    foreach ($value['play_info_array'] as $_k => $_v) {

                        $_ts = [];
                        if (!$_v['meta_data']['quality']) continue;

                        $_ts['play_url'] = isset($_v['url']) ? $_v['url'] : '';
                        $_ts['definition'] = $_v['meta_data']['quality'];
                        $_ts['bitrate'] = $_v['meta_data']['bit_rate'];
                        $_ts['duration'] = $_v['meta_data']['duration'];
                        $_ts['encrypt'] = $_v['encrypted'];
                        $_ts['format'] = '';
                        $_ts['fps'] = $_v['meta_data']['frame_rate'];
                        $_ts['height'] = $_v['meta_data']['hight'];
                        $_ts['width'] = $_v['meta_data']['width'];
                        $_ts['status'] = '';

                        $item['transcode'][] = $_ts;
                    }
                }
            }


            //截图信息
            if (in_array('snapshot', $show_content)) {
                $item['snapshot']['image_url']  = [];
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * 上传视频封面图
     *
     * @param array $category_id 视频分类id数组
     * @param array $video_id 视频id数组
     * @param UploadedFile|string $image 上传图片
     * @return bool
     */
    public function uploadCover($image, $video_id = [], $category_id = [])
    {
        if (!empty($video_id)) {
            $query = $this->whereIn('id', $video_id);
        } else {
            $query = $this->whereIn('category_id', $category_id);
        }

        $asset_id = $query->column('asset_id');

        // $md5 = base64_encode(Bytes::toStr(unpack("c*", md5($image->openFile()->fread(1024*8), true))));
        $type = strtoupper(is_string($image) ? pathinfo(strpos($image, '&') === false ? $image : substr($image, 0, strpos($image, '&')), PATHINFO_EXTENSION) : $image->extension());
        if ($type == 'JPEG' || $type == 'JPG') {
            $contentType = 'image/jpeg';
            $type = 'JPG';
        } elseif ($type == 'PNG') {
            $contentType = 'image/png';
        } else {
            throw new ValidateException(lang('cover_image_ext'));
        }

        $httpClient = new class extends BaseHttpClient{};
        foreach ($asset_id as $value) {
            $cover_upload_url = HwCloud::httpJson(
                'asset',
                ['asset_id' => $value, 'cover_type' => $type, 'cover_md5' => ''],
                [],
                'PUT'
            )['cover_upload_url'];

            $promises[$value] = $httpClient->putAsync(
                $cover_upload_url,
                [
                    'body' => is_string($image) ? file_get_contents($image) : fopen($image->getRealPath(), 'r'),
                    'headers' => [
                        'Content-Type' => $contentType,
                        'Content-MD5' => ''
                    ],
                ]
            );
        }

        \GuzzleHttp\Promise\unwrap($promises);
    }
}
