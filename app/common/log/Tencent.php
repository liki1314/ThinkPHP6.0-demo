<?php


namespace app\common\log;


use Cls\Log;
use Cls\LogGroup;
use Cls\LogGroupList;
use TencentCloud\Cls\V20201016\ClsClient;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cls\V20201016\Models\SearchLogRequest;
use think\contract\LogHandlerInterface;

class Tencent implements LogHandlerInterface
{

    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'time_format' => 'c',
        'json' => false,
        'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'format' => '[%s][%s] %s',
        'topic' => '',
    ];

    private $client;


    public function __construct($config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
        $cred = new Credential($this->config['secret_id'], $this->config['secret_key']);
        $this->client = new ClsClient($cred, "ap-guangzhou");
    }


    /**
     * 保存数据
     * @param array $data
     * @return bool
     */
    public function save(array $data): bool
    {

        $logGroupList = new LogGroupList();
        $logGroup = new LogGroup();
        $log = new Log();
        $contents = [];

        foreach ($data as $paramData) {
            foreach ($paramData as $val) {
                array_walk($val, function ($value, $key) use (&$contents) {
                    $content = new Log\Content();
                    $content->setKey($key);
                    $content->setValue($value);

                    $contents[] = $content;
                });
            }
        }

        $log->setContents($contents);
        $log->setTime(time());
        $logGroup->setLogs([$log]);
        $logGroupList->setLogGroupList([$logGroup]);
        $pb_str = $logGroupList->serializeToString();
        try {
            $resp = $this->client->call_octet_stream("UploadLog", array(
                "X-CLS-TopicId" => $this->config['topic_id'],
            ), $pb_str);
            $resp->toJsonString();
            return true;
        } catch (TencentCloudSDKException $e) {
            var_dump($e->getMessage());
            return false;
        }
        return true;

    }

    /**
     * 查询
     * @param $from
     * @param int $to
     * @param string $query
     */
    public function select($from, $to = 0, $query = '')
    {
        try {

            $req = new SearchLogRequest();
            $params = array(
                "TopicId" => $this->config['topic_id'],
                "From" => $from,
                "To" => $to,
                "Query" => $query
            );
            $req->fromJsonString(json_encode($params));
            $resp = $this->client->SearchLog($req);
            $res = json_decode($resp->toJsonString(), true);
            $data = [];
            foreach ($res['AnalysisResults'] as $value) {
                $d = [];
                foreach ($value['Data'] as $v) {
                    $d[$v['Key']] = $v['Value'] == 'null' ? '' : $v['Value'];
                }
                $data[] = $d;
            }
            return $data;
        } catch (TencentCloudSDKException $e) {
            return [];
        }
    }

    /**
     * @param $from
     * @param int $to
     * @param string $query
     * @return int
     */
    public function count($from, $to = 0, $query = '')
    {
        try {

            $req = new SearchLogRequest();
            $params = array(
                "TopicId" => $this->config['topic_id'],
                "From" => $from,
                "To" => $to,
                "Query" => $query
            );
            $req->fromJsonString(json_encode($params));
            $resp = $this->client->SearchLog($req);
            $data = json_decode($resp->toJsonString(), true);
            return intval($data['AnalysisResults'][0]['Data'][0]['Value']);
        } catch (TencentCloudSDKException $e) {
            return [];
        }
    }
}
