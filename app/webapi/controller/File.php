<?php

declare(strict_types=1);

namespace app\webapi\controller;

use app\home\model\saas\File as SaasFile;
use think\facade\Filesystem;

class File extends Base
{
    public function convert($fileid, $converStatus = -1)
    {
        if ($converStatus == 0) {
            $this->validate($this->param, ['download_url' => 'require', 'preview_url' => 'require']);

            $model = SaasFile::where('live_fileid', $fileid)->findOrFail();
            $path = $model['path'];
            $model->save(['path' => $this->param['download_url'], 'live_fileinfo' => ['preview_url' => $this->param['preview_url']]]);

            Filesystem::delete($path);
        }

        return $this->success();
    }
}
