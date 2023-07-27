<?php
declare (strict_types = 1);

namespace app\clientapi\model;
use app\common\http\HwCloud;
use app\clientapi\model\Watermark as WatermarkModel;
use think\helper\Arr;

class Video extends Base
{


    /**
     * 查询华为云视频列表信息
     * @param $ids
     * @param $filter
     */
    public function getVideo($ids,$filter)
    {
        $asset_id_list = $this->selectOrFail($ids)->toArray();

        $queryString = '';
        $video_id    = $company = [];

        foreach ($asset_id_list as $item) {
            $queryString .= 'asset_id=' . $item['asset_id'] . '&';
            $video_id[$item['asset_id']] = $item['id'];
            $company[$item['id']]['company_id'] = $item['company_id'];
            $company[$item['id']]['category_id'] = $item['category_id'];
        }

        $queryString = trim($queryString, '&');

        $api_resopose = HwCloud::httpGet('asset/info?' . $queryString);

        return $this->filterVideo($api_resopose, $filter,$video_id,$company);
    }


    /**
     * 根据过滤规则做数据筛选
     * @param $videoData
     * @param $filter
     */
    private function filterVideo($videoData,$filter,$video_id,$company)
    {
        $result = [];
        $WatermarkModel = new WatermarkModel;
        $show_content = $filter ? explode(',',$filter) : [];

        foreach($videoData['asset_info_array'] as $key=>$value)
        {
            $item = [];
            $item['id'] = isset($video_id[$value['asset_id']]) ? $video_id[$value['asset_id']] : null;

            //基础信息
            if(!$show_content || in_array('basic',$show_content))
            {
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
            if(in_array('meta',$show_content))
            {
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
            if(in_array('snapshot',$show_content))
            {
                $item['snapshot']['image_url']  = [];
            }

            //水印
            if(in_array('watermark',$show_content))
            {
                //分类ID
                $cate_filter = [
                    ['watermark_type','=',2],
                    ['watermark_id','=',$company[$item['id']]['category_id']]
                ];

                //视频ID
                $video_filter = [
                    ['watermark_type','=',1],
                    ['watermark_id','=',$item['id']]
                ];

                //企业ID
                $company_filter = [
                    ['watermark_type','=',3],
                    ['watermark_id','=',$company[$item['id']]['company_id']]
                ];

                $waterList = $WatermarkModel
                    ->whereOr([$cate_filter,$video_filter,$company_filter])
                    ->select()
                    ->order('watermark_type')
                    ->toArray();

                $waterInfo = [];
                $waterInfo['url'] = $waterInfo['location'] = '';

                if ($waterList) {
                    $_temp = Arr::first($waterList);
                    $waterInfo['url'] = $_temp['url'];
                    $waterInfo['location'] = (string)$_temp['location'];
                }

                $item['watermark'] = $waterInfo;
            }

            $result[] = $item;
        }

        return $result;
    }


}
