<?php

declare(strict_types=1);

namespace app\admin\command;

use app\common\job\TencentLog;
use app\common\service\TaskManage;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;
use app\common\facade\WechatMessageTemplate;
use app\common\facade\AppTemplate;
use app\common\model\Company;
use think\facade\Log;
use think\facade\Queue;

/**
 * 上下课通知提醒任务
 */
class Notice extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('notice')
            ->setDescription('the notice command');
    }

    protected function execute(Input $input, Output $output)
    {
        try {
            $task = [];
            $task['function_name'] = 'Notice' . '/' . __FUNCTION__;
            $task['execute_time'] = '开始时间:' . date('Y-m-d H:i:s');
            $task['task_desc'] = $task['task_name'] = '上下课通知提醒任务';


            $redis = Cache::store('redis');
            $keys = $redis->getTagItems('notice');

            if (!empty($keys)) {
                $wechatConfig = config('wechat');
                $jpushConfig = config('sdk.jpush');
                foreach (array_chunk($keys, 1000) as $chunk_keys) {
                    $values = array_map(function ($value) {
                        return $value === false ? $value : unserialize($value);
                    }, $redis->mGet($chunk_keys));

                    $users = Db::name('room_user')->whereIn('room_id', array_column(array_filter($values), 'id'))->select();

                    $companyModels = [];
                    foreach ($values as $key => $value) {
                        config(['jpush' => $jpushConfig], 'sdk');
                        config($wechatConfig, 'wechat');

                        if ($value === false) {
                            $redis->sRem($redis->getTagKey('notice'), $chunk_keys[$key]);
                            continue;
                        }

                        if (!isset($companyModels[$value['company_id']])) {
                            $companyModels[$value['company_id']] = Company::getDetailById($value['company_id']);
                        }

                        if (isset($companyModels[$value['company_id']]['extra_info']['jpush'])) {
                            config(['jpush' => $companyModels[$value['company_id']]['extra_info']['jpush']], 'sdk');
                        }

                        if (isset($companyModels[$value['company_id']]['extra_info']['wechat'])) {
                            config($companyModels[$value['company_id']]['extra_info']['wechat'], 'wechat');
                        }

                        if (WechatMessageTemplate::store('course.go_to_class')->checkConfig($value['company_id'], $value) === false) {
                            continue;
                        }

                        $front_user_id = $users->where('room_id', $value['id'])->column('front_user_id');
                        array_push($front_user_id, $value['teacher_id']);
                        $result = WechatMessageTemplate::store('course.go_to_class')->makeSendData($value, $front_user_id, $value['company_id'])->send() !== false/*  ||
                        WechatTemplate::store('course.class_report')->makeSendData($value, $front_user_id, $value['companyid'])->send() !== false ||
                        WechatMessageTemplate::store('course.class_end')->makeSendData($value, $front_user_id, $value['company_id'])->send() !== false */
                        ;

                        AppTemplate::store('course.go_to_class')
                            ->makeSendData($value, $front_user_id)
                            ->send();
                        if ($result === true) {
                            $redis->sRem(Cache::getTagKey('notice'), $chunk_keys[$key]);
                        }
                    }
                }
            }

            // 指令输出
            $output->writeln('notice');
            $task['execute_status'] = TaskManage::SUCCESS_STATUS;
            $task['execute_time'] .= '结束时间:' . date('Y-m-d H:i:s');
            $task['error_msg'] = '';
        } catch (\Throwable $e) {
            $task['execute_status'] = TaskManage::FAIL_STATUS;
            $task['execute_time'] .= '结束时间:' . date('Y-m-d H:i:s');
            $task['error_msg'] = $e->getMessage() . '_行数为:' . $e->getLine();
        } finally {
            if (TaskManage::FAIL_STATUS == $task['execute_status']) {
                Queue::push(TencentLog::class, $task, 'tencent_log');
            }
        }
    }
}
