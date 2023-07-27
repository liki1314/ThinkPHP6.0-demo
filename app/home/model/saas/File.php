<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-18
 * Time: 11:45
 */

namespace app\home\model\saas;

use app\common\service\Upload;
use app\common\http\WebApi;

class File extends \app\BaseModel
{
    /** 本地文件 */
    const LOCAL_TYPE = 1;

    /** 网盘文件 */
    const CLOUD_TYPE = 2;

    protected $json = ['live_fileinfo'];

    /**
     * 获取接口远程文件信息
     */
    public function getCloudFile($ids, $map = [])
    {
        if (!$ids) return [];
        $res = [];
        $apiRes = WebApi::httpPost('/WebAPI/fileInfo?error=0', ['fileidarr' => $ids]);
        if (isset($apiRes['data'])) {
            foreach ($apiRes['data'] as $item) {
                $res[] = [
                    'id' => $item['fileid'],
                    'name' => $item['filename'],
                    'size' => human_filesize($item['size']),
                    'type' => $item['filetype'],
                    'url' => $item['download_url'],
                    'duration' => $map[$item['fileid']]['duration'] ?? 0,
                    'source' => self::CLOUD_TYPE,
                    'preview_url' => $item['preview_url'],
                ];
            }
        }
        return $res;
    }

    /**
     * 获取本地文件信息
     * @param $ids
     * @param $map
     */
    public function getLocalFile($ids, $map = [])
    {
        $res = [];
        $localList = $this->whereIn('id', $ids)->select();

        foreach ($localList as $item) {
            $res[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'size' => human_filesize($item['size']),
                'type' => $item['type'],
                'url' => Upload::getFileUrl($item['path']),
                'duration' => $map[$item['id']]['duration'] ?? 0,
                'source' => self::LOCAL_TYPE,
                'preview_url' => $item['live_fileinfo']['preview_url'] ?? get_office_preview(Upload::getFileUrl($item['path'])),
            ];
        }
        return $res;
    }
}
