<?php

namespace app\home\job;

use app\common\http\WebApi;
use app\home\model\saas\File;
use think\queue\Job;
use think\facade\Filesystem;

class FileDelete
{
    public function fire(Job $job, $data)
    {
        if (!empty($data['files'])) {
            $deleted = File::whereIn('id', $data['files'])
                ->where('delete_time', '>', 0)
                ->select();

            //删除本地文件
            $deleted->where('live_fileid', '=', 0)->each(function ($item) {
                Filesystem::delete($item['path']);
            });

            //删除网盘私有文件
            if (isset($data['company_id']) && ($fileidarr = $deleted->where('live_fileid', '>', 0)->column('live_fileid'))) {
                request()->user = ['company_id' => $data['company_id']];
                WebApi::httpPost('WebAPI/deletefile', ['fileidarr' => $fileidarr]);
            }
        }

        $job->delete();
    }

    public function failed($data)
    {
    }
}
