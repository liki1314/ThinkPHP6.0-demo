<?php

namespace app\home\job;

use app\home\model\saas\File;
use think\queue\Job;
use app\common\http\WebApi;
use think\facade\Db;
use think\facade\Filesystem;
use app\common\service\Upload;

class FileConvert
{
    public function fire(Job $job, $data)
    {
        request()->user = ['company_id' => $data['company_id']];

        Db::transaction(function () use ($data) {
            File::lock(true)
                ->whereIn('id', $data['files'])
                ->where('live_fileid', 0)
                ->where('delete_time', 0)
                ->whereIn('type', ['xls', 'xlsx', 'ppt', 'pptx', 'doc', 'docx', 'txt', 'pdf', 'jpg', 'gif', 'jpeg', 'png', 'bmp', 'mp3', 'mp4', 'zip', 'wav', 'mov'])
                ->select()
                ->each(function ($item) {
                    $stream = @fopen(Upload::getFileUrl($item['path']), 'rb');
                    if ($stream === false) {
                        return;
                    }

                    try {
                        $file = WebApi::httpMultipart(
                            'WebAPI/uploadfile',
                            [
                                'filedata' => $stream, //Filesystem::readStream($item['path']),
                                'isopen' => 0,
                            ]
                        );
                    } catch (\League\Flysystem\FileNotFoundException $e) {
                        return;
                    }

                    $item->save(['live_fileid' => $file['fileid']]);
                });
        });

        $job->delete();
    }

    public function failed($data)
    {
    }
}
