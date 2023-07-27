<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-17
 * Time: 14:40
 */

namespace app\home\controller\v1;


use app\common\http\WebApi;
use app\home\controller\Base;
use app\home\model\saas\Room;
use think\paginator\driver\Bootstrap;

class Resource extends Base
{
    const FILE_TYPE = 2;

    const DIR_TYPE  = 1;

    public function index()
    {

        Room::findOrFail($this->param['lesson_id']);

        $params = [
            'catalogId' => $this->param['dir_id'] ?? 0,
            'keywords' => $this->param['keywords'] ?? '',
            'page' => $this->page,
            'pageSize' => $this->rows,
        ];

        $apiRes = WebApi::httpPost('/WebAPI/fileList', $params);
        $data         = [];
        foreach ($apiRes['data']['list'] as $value) {
            $temp = [];
            if ($value['data_type'] == self::DIR_TYPE) {
                $temp['id'] = $value['catalogid'];
                $temp['name'] = $value['title'];
                $temp['type_id'] = self::DIR_TYPE;
                $temp['type'] = '';
                $temp['size'] = human_filesize(0);
                $temp['update_time'] = $value['uploadtime'];
                $temp['preview_url'] = '';
                $temp['status'] = 0;
                $temp['downloadpath'] = '';
            } else {
                $temp['id'] = $value['fileid'];
                $temp['name'] = $value['filename'];
                $temp['type_id'] = self::FILE_TYPE;
                $temp['type'] = $value['filetype'];
                $temp['size'] = human_filesize($value['size']);
                $temp['update_time'] = $value['uploadtime'];
                $temp['preview_url'] = $value['preview_url'];
                $temp['status'] = $value['status'];
                $temp['downloadpath'] = $value['download_url'];
            }

            $data[] = $temp;
        }

        $total = $apiRes['data']['count'] ?? 0;
        $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
        return $this->success($res);

    }
}
