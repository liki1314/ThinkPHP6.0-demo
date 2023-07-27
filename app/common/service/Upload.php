<?php

namespace app\common\service;

use think\facade\Filesystem;
use think\File;

class Upload
{
    /**
     * 文件上传
     * @param File $file
     * @param string $asName 指定文件名保存
     * @param string|array $disk 上传磁盘|平台，支持上传到多个磁盘|平台
     * @param string $upload_path 上传父目录
     * @param null|string|\Closure $rule 文件命名规则
     * @return string|array
     */
    public static function putFile(File $file, $asName = '', $disk = [], $upload_path = '', $rule = null)
    {
        if (empty($disk)) {
            $disk = config('filesystem.multi_default');
        }

        if (!is_array($disk)) {
            $disk = [$disk];
        }

        if (empty($upload_path)) {
            $upload_path = config('filesystem.upload_path');
        }

        foreach ($disk as $val) {
            if (!empty($asName)) {
                $savename[$val] = Filesystem::disk($val)->putFileAs($upload_path, $file, date('Ymd') . DIRECTORY_SEPARATOR . $asName . '.' . $file->extension());
            } else {
                $savename[$val] = Filesystem::disk($val)->putFile($upload_path, $file, $rule);
            }
        }

        return count($savename) === 1 ? array_values($savename)[0] : $savename;
    }

    /**
     * 获取文件访问地址
     * @param string $fileName 文件名
     * @param string $disk 磁盘名
     */
    public static function getFileUrl($fileName, $disk = null)
    {
        if (empty($fileName)) {
            return '';
        }

        if (strpos($fileName, 'http') === 0) {
            return $fileName;
        }

        return Filesystem::disk($disk)->getUrl(trim($fileName, '/'));
    }
}
