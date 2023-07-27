<?php

namespace app\common\job;

use think\queue\Job;
use think\facade\Log;

class AliyunLog
{
    public function fire(Job $job, $data)
    {
        Log::aliyun($data);
        $job->delete();

        // $job->release($delay); //$delay为延迟时间
    }

    public function failed($data)
    {
    }
}
