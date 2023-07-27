<?php

namespace app\admin\model;


use app\common\service\Upload;
use thans\jwt\facade\JWTAuth;
use think\facade\Route;
use app\BaseModel;

class FileExport extends BaseModel
{
    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('a.id,a.name as type,a.type name,a.size,a.delete_time,a.path,a.create_time export_time,b.username op_name')
            ->alias('a')
            ->join('user_account b', 'a.create_by=b.id')
            ->where('a.create_by', $data['user']['user_account_id'])
            ->append(['download_path', 'status', 'export_time'])
            ->hidden(['delete_time', 'id', 'path'])
            ->order('id', 'desc');
    }

    public function getDownloadPathAttr()
    {
        return $this->getAttr('path');
    }

    public function getSizeAttr($value)
    {
        return $value ? human_filesize($value) : '0';
    }

    public function getTypeAttr($value)
    {
        return lang($value);
    }

    public function getNameAttr($value)
    {
        return lang($value);
    }

    public function getStatusAttr($value, $data)
    {
        return $data['path'] ? 1 : 0;
    }

    public function getPathAttr($value)
    {
        return $value ? Upload::getFileUrl($value) : '';
    }

    public function getExportTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }
}
