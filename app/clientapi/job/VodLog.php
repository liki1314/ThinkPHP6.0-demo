<?php

namespace app\clientapi\job;

use think\queue\Job;
use think\facade\Log;

class VodLog
{
    public function fire(Job $job, $data)
    {
        Log::channel('vod')->record($data);
        $job->delete();
    }

    public function failed($data)
    {
    }
}
