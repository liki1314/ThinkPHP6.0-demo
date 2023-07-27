<?php

namespace app\common\app_terminal\messages\template;

use JPush\Client;
use JPush\Exceptions\APIRequestException;
use think\App;
use think\facade\Db;
use think\facade\Log;

abstract class Driver
{
    /**
     * 消息接受者
     *
     * @var array
     */
    public $users;

    /**
     * 通知配置信息
     *
     * @var array
     */
    protected $config = [];

    private $client;

    /** 上课提醒 */
    const GO_TO_CLASS_TYPE = 3;
    /** 作业发布 */
    const ASSIGN_TYPE = 1;
    /** 作业点评 */
    const REMARK_TYPE = 2;
    /** 作业提醒 */
    const REMIND_TYPE = 1;
    // 作业提交
    const SUBMIT_TYPE = 1;


    public function __construct()
    {

        //$logFile = env('APP_DEBUG') ? sprintf('%slog/%s/%s_jpush.log', App::getInstance()->getRuntimePath(), date('Ym'), date('d')) : null;
        // $logFile = sprintf('%slog/%s/%s_jpush.log', App::getInstance()->getRuntimePath(), date('Ym'), date('d'));

        // $this->config = [
        //     'app_key' => config('sdk.jpush.app_key'),
        //     'app_secret' => config('sdk.jpush.secret'),
        //     'log_file' => $logFile
        // ];

        // $this->client = new  Client($this->config['app_key'], $this->config['app_secret'], $this->config['log_file']);
    }

    /**
     * 发送消息
     */
    public function send()
    {
        if (!empty($this->users)) {
            $this->client = new  Client(config('sdk.jpush.app_key'), config('sdk.jpush.secret'), sprintf('%slog/%s/%s_jpush.log', App::getInstance()->getRuntimePath(), date('Ym'), date('d')));
            foreach ($this->users as $value) {
                $data = $this->getDataByUser($value);
                $insertData[] = [
                    'user_account_id' => $value['user_account_id'],
                    'userroleid' => $value['userroleid'],
                    'title' => $data['title'],
                    'content' => $data['remark'],
                    'create_time' => time(),
                    'type' => $this->getType(),
                    'extras' => $data['extras']
                ];
                try {
                    $this->client->push()
                        ->options([
                            'apns_production' => !env('APP_DEBUG')
                        ])
                        ->setPlatform('all')
                        ->addAlias($data['alias'])
                        ->iosNotification([
                            'title' => $data['title'],
                            'body' => $data['remark']
                        ], [
                            'sound' => 'sound',
                            'badge' => '+1',
                            'extras' => $data['extras']
                        ])
                        ->androidNotification($data['remark'], array(
                            'title' => $data['title'],
                            'extras' => $data['extras'],
                            'uri_activity' => 'com.talkcloud.networkshcool.baselibrary.ui.activities.OpenClickActivity',
                        ))->send();
                } catch (APIRequestException $e) {
                    Log::error(sprintf("code:%s;msg:%s", $e->getCode(), $e->getMessage()));
                }
            }
            Db::name('notice')->json(['extras'])->insertAll($insertData);
        }
    }

    /**
     * 处理消息数据
     * @param $user
     * @return mixed
     */
    abstract protected function getDataByUser($user);

    public function makeSendData($origin, $front_user_id)
    {
        return $this;
    }

    public function getType()
    {
        return 0;
    }
}
