<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-24
 * Time: 09:27
 */

namespace app\common\http;


use app\common\model\Company;
use think\Exception;

class FileHttp extends WebApi
{

    const NO_ERROR = true;

    /**
     *通过文件ids，获取文件详情
     * @param $fileIds
     * @param string $keywords
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getIdsToFile($fileIds, $keywords = '')
    {
        $params = [
            'fileidarr' => $fileIds,
            'key' => Company::cache(true, 12 * 3600)->find(request()->user['company_id'])['authkey']
        ];

        if (!empty($keywords)) $params['keywords'] = $keywords;

        $res = self::httpPost("/WebAPI/fileInfo", $params);

        // if (isset($res['result']) && $res['result'] > 0) throw new Exception($res['result']);

        $data = [];

        foreach ($res['data']??[] as $value) {
            $data[] = [
                'id' => $value['fileid'],
                'name' => $value['filename'],
                'size' => $value['size'],
                'status' => $value['status'],
                'type' => $value['filetype'],
                'update_time' => $value['uploadtime'],
                'preview_url' => $value['preview_url'],
                'download_url' => $value['download_url']
            ];
        }

        return $data;
    }

    /**
     * 删除私有资源
     * @param $fileIds
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function deletePrivate($fileIds)
    {
        if (!empty($fileIds)) {

            $res = self::httpPost("/WebAPI/deletefile", [
                'fileidarr' => $fileIds,
                'delete_private' => 1,
                'key' => Company::cache(true, 12 * 3600)->find(request()->user['company_id'])['authkey']
            ]);

            if (isset($res['result']) && $res['result'] > 0 && $res['result'] != 4105) {
                throw new Exception($res['result']);
            }
        }
    }

    /**
     * 获取接口远程文件信息
     */
    public function getCloudFile($ids, $map = [])
    {
        if (!$ids) return [];
        $res = [];
        $apiRes = self::httpPost('/WebAPI/fileInfo?error=0', ['fileidarr' => $ids]);
        if (isset($apiRes['data'])) {
            foreach ($apiRes['data'] as $item) {
                $res[] = [
                    'id' => $item['fileid'],
                    'name' => $item['filename'],
                    'size' => human_filesize($item['size']),
                    'type' => $item['filetype'],
                    'url' => $item['download_url'],
                    'duration' => $map[$item['fileid']]['duration'] ?? 0,
                    'preview_url' => $item['preview_url'],
                ];
            }
        }
        return $res;
    }
}
