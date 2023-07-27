<?php

declare(strict_types=1);

namespace app\gateway\model;

use think\Model;
use app\common\service\Upload as ServiceUpload;
use app\Request;
use think\file\UploadedFile;

/**
 * @mixin \think\Model
 */
class File extends Model
{
    public static function onBeforeInsert(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            $model->set('create_by', $request->user['user_account_id']);
            $model->set('company_id', $request->user['company_id'] ?? 0);
        });
    }

    public function setFileAttr(UploadedFile $file)
    {
        $this->set('path', ServiceUpload::putFile($file));
        $this->set('name', $file->getOriginalName());
        $this->set('size', $file->getSize());
        $this->set('type', $file->extension());
    }

    public function getUrlAttr($value, $data)
    {
        return ServiceUpload::getFileUrl($data['path']);
    }
}
