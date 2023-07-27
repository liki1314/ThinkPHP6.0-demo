<?php

declare(strict_types=1);

namespace app\clientapi\controller;

use app\common\http\HwCloud;
use think\facade\Cache;

class Encrypt
{
    /**
     * 获取视频秘钥
     *
     * @param string $asset_id 视频id串
     * @return string
     */
    public function getVideoEncrypt($asset_id = '')
    {
        return response(base64_decode(Cache::remember('video_encrypt:' . $asset_id, function () use ($asset_id) {
            return HwCloud::httpGet('asset/ciphers', ['asset_id' => $asset_id])['dk'];
        })), 200, [], \app\common\service\Text::class);
    }
}
