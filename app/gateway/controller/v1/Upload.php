<?php

declare(strict_types=1);

namespace app\gateway\controller\v1;

use app\gateway\model\File;
use think\facade\Db;

class Upload extends \app\BaseController
{
    public function save()
    {
        $this->validate(
            $this->param,
            ['files' => ['require', 'array', 'each' => 'file']],
            ['files' => '上传文件错误']
        );

        $models = Db::transaction(function () {
            /** @var \think\model\Collection $models */
            $models = (new File())->saveAll(array_map(function ($item) {
                return ['file' => $item];
            }, $this->request->file('files')));

            return $models;
        });

        return $this->success($models->append(['url']));
    }
}
