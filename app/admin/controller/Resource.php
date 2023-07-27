<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\validate\Resource as ResourceValidate;
use app\common\http\WebApi;
use thans\jwt\facade\JWTAuth;
use think\facade\Route;
use think\helper\Arr;
use think\facade\Db;
use think\paginator\driver\Bootstrap;

/**
 * 企业网盘，全部调用webapi接口
 */
class Resource extends Base
{
    const FILE_TYPE = 2;

    const DIR_TYPE  = 1;

    /**
     * 文件(夹) 列表
     */
    public function index()
    {
        $params = [
            'catalogId' => $this->param['dir_id'] ?? 0,
            'keywords' => $this->param['name'] ?? '',
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
                $temp['size'] = '';
                $temp['update_time'] = $value['uploadtime'];
                $temp['preview_url'] = '';
                $temp['status'] = 0;
                $temp['downloadpath'] = '';
                $temp['path'] = '';
            } else {
                $temp['id'] = $value['fileid'];
                $temp['name'] = $value['filename'];
                $temp['type_id'] = self::FILE_TYPE;
                $temp['type'] = $value['filetype'];
                $temp['size'] = $value['size'];
                $temp['update_time'] = $value['uploadtime'];
                $temp['preview_url'] = $value['preview_url'];
                $temp['status'] = $value['status'];
                $temp['downloadpath'] = (string)Route::buildUrl("resourceDownload/{$value['fileid']}", ['token' => JWTAuth::token()->get()])
                    ->domain(true)->suffix('');
                $temp['path'] = $value['download_url'];
            }

            $data[] = $temp;
        }

        $total = $apiRes['data']['count'] ?? 0;
        $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
        return $this->success($res);
    }

    /**
     * 文件/文件夹重命名
     * @param $type
     * @param $id
     *
     */
    public function update($type, $id)
    {
        $rule = [
            'name' => [
                'require',
                'max' => 100
            ]
        ];

        $message = [
            'name.require' => 'file_name_empty',
        ];

        $this->validate($this->param, $rule, $message);

        $params = [
            'id' => $id,
            'name' => $this->param['name'],
        ];

        if ($type == self::DIR_TYPE) {
            $apiRes = WebApi::httpPost('/WebAPI/renameCatalog', $params);
        } else {
            $apiRes = WebApi::httpPost('/WebAPI/renameFile', $params);
        }

        return $this->success($apiRes);
    }


    /**
     * 单个文件上传或批量上传文件
     */
    public function uploadFile($course_id = 0)
    {
        $this->validate(
            $this->param,
            [
                'dir_id' => 'integer',
                'files' => [
                    'require',
                    'array',
                    'each' => 'file|fileExt:xls,xlsx,ppt,pptx,doc,docx,txt,pdf,jpg,gif,jpeg,png,bmp,mp3,mp4,zip|fileSize:314572800'
                ],
                'is_dynamic' => [
                    'array',
                    'each' => 'integer|in:0,1'
                ]
            ],
            [
                'dir_id' => 'dir_id_validate',
                'files.require' => 'file_upload_error',
                'files.array' => 'files_validate',

            ]
        );

        $files = [];
        foreach ($this->param['files'] as $key => $file) {
            $files[] = Arr::only(
                WebApi::httpMultipart(
                    '/WebAPI/uploadfile',
                    [
                        'filedata' => $file,
                        'isopen' => $this->param['active'],
                        // 'filename' => $file->getOriginalName(),
                        // 'conversion' => 1,
                        'catalogid' => $this->param['dir_id'] ?? 0,
                        'dynamicppt' => $this->param['is_dynamic'][$key] ?? 0,
                    ]
                ),
                ['fileid', 'filename']
            );
        }

        if (!empty($course_id)) {
            $course = Db::name('course')->json(['resources'])->findOrFail($course_id);
            Db::name('course')
                ->json(['resources'])
                ->where('id', $course_id)
                ->update(['resources' => array_merge($course['resources'], array_column($files, 'fileid'))]);
        }

        return $this->success($files);
    }

    /**
     * 移动文件或者目录
     */
    public function move()
    {
        $this->validate(
            $this->param,
            [
                'from_ids' => ['require', 'array'],
                'to_dir_id' => ['require', 'integer']
            ],
            [
                'from_ids' => 'params_error',
                'to_dir_id' => 'to_dir_id_validate',
            ]
        );

        foreach ($this->param['from_ids'] as $value) {
            if ($value['type'] == self::DIR_TYPE) {
                $dirParams['dir_ids'][] = $value['id'];
                $dirParams['to_dir_id'] = $this->param['to_dir_id'];
            } else {
                $fileParams['fileidarr'][] = $value['id'];
                $fileParams['catalogId'] = $this->param['to_dir_id'];
            }
        }

        if (isset($dirParams['dir_ids'])) {
            $apiRes = WebApi::httpPost('/WebAPI/moveCatalogs', $dirParams);
        }

        if (isset($fileParams['fileidarr'])) {
            $apiRes = WebApi::httpPost('/WebAPI/movefile', $fileParams);
        }

        return $this->success($apiRes);
    }


    /**
     * 资源进行copy
     */
    public function copy()
    {
        $this->validate(
            $this->param,
            [
                'from_ids' => ['require', 'array'],
                'to_dir_id' => ['require', 'integer']
            ],
            [
                'from_ids' => 'from_ids_validate',
                'to_dir_id' => 'to_dir_id_validate',
            ]
        );

        return $this->success();
    }

    /**
     * 删除文件 或者文件夹
     *
     */
    public function deleteByType()
    {
        $rule = [
            'ids' => ['require', 'array', 'each' => [
                'id' => ['require', 'integer'],
                'type' => ['require', 'integer', 'in:1,2'],
            ]]
        ];
        $message = [
            'ids.require' => 'params_empty',
            'ids.array' => 'params_error',
            'type.require' => 'file_type_empty',
            'type.integer' => 'file_type_error',
            'type.in' => 'file_type_error',
            'id.require' => 'file_id_empty',
            'id.integer' => 'file_id_error',
        ];

        $this->validate($this->param, $rule, $message);

        foreach ($this->param['ids'] as $value) {
            if ($value['type'] == self::DIR_TYPE) {
                $dirParam['dir_ids'][] = $value['id'];
            } else {
                $fileParam['fileidarr'][] = $value['id'];
            }
        }

        if (isset($fileParam['fileidarr'])) {
            $apiRes = WebApi::httpPost('/WebAPI/deletefile', $fileParam);
        }

        if (isset($dirParam['dir_ids'])) {
            $apiRes =  WebApi::httpPost('/WebAPI/deleteCatalogs', $dirParam);
        }

        return $this->success($apiRes);
    }


    /**
     * 新增文件夹
     * @author  hongwei
     */
    public function createDir()
    {
        $this->validate($this->param, ResourceValidate::class);

        $params = [];
        $params['catalog_title'] = $this->param['catalog_title'];
        $params['pid'] = $this->param['pid'];
        $params['userid'] = $this->request->user['userid'];
        $params['username'] = $this->request->user['username'];
        $apiRes = WebApi::httpPost('/WebAPI/addcatalog', $params);
        return $this->success($apiRes);
    }


    /**
     * 文件夹列表
     */
    public function catagoryList()
    {
        $params = [
            'page' => $this->page,
            'page_size' => $this->rows,
        ];

        if (isset($this->param['dir_id'])) {
            $params['dir_id'] = $this->param['dir_id'];
        }
        $apiRes = WebApi::httpPost('/WebAPI/getCatalogList', $params);

        $data         = [];
        foreach ($apiRes['data']['data'] as $value) {
            $temp = [];
            $temp['catalogid'] = $value['catalogid'];
            $temp['catalog_title'] = $value['catalog_title'];
            $temp['type'] = self::DIR_TYPE;
            $temp['size'] = '';
            $data[] = $temp;
        }


        $total = $apiRes['data']['total'] ?? 0;
        $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
        return $this->success($res);
    }

    /**
     * 企业上传大小上限
     */
    public function filesize()
    {
        $apiRes = WebApi::httpPost('/WebAPI/getCompanyInfo', ['error' => 0]);
        return $this->success(['filesize' => $apiRes['data']['coursemaxsize'] ?? 256]);
    }

    /**
     * 执行下载
     * @param $fileid
     */
    public function donwload($fileid)
    {
        $apiRes = WebApi::httpPost('/WebAPI/fileInfo', ['fileidarr' => [$fileid]]);
        $url = $apiRes['data'][0]['download_url'] ?? '';
        return redirect($url);
    }
}
