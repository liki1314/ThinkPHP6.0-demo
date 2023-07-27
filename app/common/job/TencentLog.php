<?php
/**
 * 腾讯日志消费端
 *
 */
namespace app\common\job;

use app\common\http\CommonAPI;
use think\facade\Log;
use think\queue\Job;
use app\common\service\TaskManage;

class TencentLog
{

    public function fire(Job $job, $data)
    {
        if (TaskManage::SUCCESS_STATUS == $data['execute_status']) {
            $msg = $data['error_msg'];
            unset($data['error_msg']);
            Log::channel('tx_crontab_log')->record($data);
            $data['key'] = '';
            $data['error_msg'] = $msg ?: '_';
            CommonAPI::httpPost('CommonAPI/updateTaskManage', $data);
        } else {
            $save = [];
            $save['content'] = $data['function_name'];
            $save['type'] = 'school_type'; //网校类型
            $save['source'] = 'net_school'; //网校
            $save['msg'] = $data['error_msg'];
            Log::channel('tx_exception_log')->record($save);
            if (isset($data['sync_api'])) {
                $data['key'] = '';
                CommonAPI::httpPost('CommonAPI/updateTaskManage', $data);
            }
        }

        $job->delete();
    }


    public function failed($data)
    {

    }
}