<?php

namespace app\common\job;

use think\queue\Job;

class Live
{
    public function fire(Job $job, $data)
    {
        call_user_func_array($data['func'], $data['params']);
        $job->delete();

        // $job->release($delay); //$delay为延迟时间
    }

    public function failed($data)
    {
    }
}
