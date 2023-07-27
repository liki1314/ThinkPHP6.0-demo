<?php

namespace app\common\queue\failed;

use think\facade\Log;
use think\queue\failed\None;
use Carbon\Carbon;

class Aliyun extends None
{
    public function log($connection, $queue, $payload, $exception)
    {
        $fail_time = Carbon::now()->toDateTimeString();
        Log::channel('failed_jobs')->record(compact('connection', 'queue', 'payload', 'exception', 'fail_time'));
    }
}
