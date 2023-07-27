<?php

namespace app\common\log;

use think\contract\LogHandlerInterface;
use think\Exception;

class Aliyun implements LogHandlerInterface
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'time_format'  => 'c',
        'json'         => false,
        'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'format'       => '[%s][%s] %s',
        'topic'        => '',
    ];

    private $client;

    // 实例化并传入参数
    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $this->client = new \Aliyun_Log_Client($this->config['endpoint'], $this->config['access_key_id'], $this->config['access_key_secret']);
    }

    public function save(array $log): bool
    {
        $request = new \Aliyun_Log_Models_PutLogsRequest($this->config['project'], $this->config['logstore'], $this->config['topic']);
        try {
            foreach ($log as $contents) {
                foreach ($contents as $content) {
                    $logItme = new \Aliyun_Log_Models_LogItem();
                    $logItme->setContents($content);
                    $logItmes[] = $logItme;
                }
            }

            $request->setLogItems($logItmes);
            $this->client->putLogs($request);

            return true;
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * 查询日志
     *
     * @param integer $from 查询开始时间
     * @param integer $to 查询结束时间
     * @param string $query 查询分析语法
     * @param integer $line 查询条数
     * @param integer $offset 查询开始行
     * @param boolean $reverse 是否按日志时间戳逆序返回日志，精确到分钟级别
     * @return array
     */
    private function getLog($from, $to = 0, $query = '', $line = 100, $offset = 0, $reverse = false)
    {
        $request = new \Aliyun_Log_Models_GetLogsRequest(
            $this->config['project'],
            $this->config['logstore'],
            $from,
            $to ?: time(),
            $this->config['topic'],
            $query,
            $line,
            $offset,
            $reverse
        );

        $result = [];
        $response = $this->client->getLogs($request);
        foreach ($response->getLogs() as $log) {
            $result[] = $log->getContents();
        }

        return $result;
    }

    public function find($query, $from = 0, $to = 0)
    {
        return $this->getLog($from, $to, $query, 1)[0] ?? null;
    }
}
