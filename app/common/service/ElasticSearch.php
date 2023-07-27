<?php

namespace app\common\service;

use Elasticsearch\ClientBuilder;

// require 'vendor/autoload.php';


/**
 * ES操作相关方法
 * Class ElasticSearch
 * @package Home\Common
 */
class ElasticSearch
{
    ## 模型对象
    private static $_instance = null;
    ## es实例
    private static $_client = null;
    private static $_config = null;
    private static $_type = '_doc';

    private function __construct()
    {
        if (empty(self::$_client) && !empty(self::$_config)) {
            self::$_client = ClientBuilder::create()
                ->setHosts(self::$_config)
                ->setSSLVerification(false)
                ->build();

        }
    }

    /**
     * 创建ES连接实例
     * @param array $hostConfig ES配置数组
     *
     * @return bool|\Elasticsearch\Client|ElasticSearch|null  false 创建失败
     *
     * @auther chenshuang
     * @date   2020-12-08 11:49
     */
    public static function getInstance($hostConfig = [])
    {
        self::$_config = !empty($hostConfig) ? $hostConfig : config('database.connections.HW_ES.Host');
        if (empty(self::$_instance) || !(self::$_instance instanceof self)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     * 创建index\type
     * @param string $index 索引
     * @param array $customInfo 创建type自定义信息
     *
     * @return array|bool  false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 14:34
     */
    public static function createIndex($index, $customInfo = [])
    {
        if (empty($index)) {
            return false;
        }
        $params = [
            'index' => $index,
        ];
        if (!empty($customInfo)) {
            $params = array_merge($params, $customInfo);
        }
        return self::$_client->indices()->create($params);
    }

    /**
     * 查看mapping映射信息
     * @param string $index index名称
     *
     * @return array|bool  false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 14:42
     */
    public static function getMappings($index)
    {
        if (empty($index)) {
            return false;
        }
        $params = [
            'index' => $index
        ];
        return self::$_client->indices()->getMapping($params);
    }


    /**
     * 修改Mappings添加字段
     * @param string $index index名称
     * @param string $type type名称
     * @param array $customsInfo 自定义字段索性  {"body":{"my_type":{"properties":{"idcard":{"type":"integer"}}}}}
     *
     * @return array|bool false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 14:50
     */
    public function putMappings($index, $type, $customsInfo = [])
    {
        if (empty($index) || empty($customsInfo)) {
            return false;
        }
        $params = array_merge([
            'index' => $index,
            'type' => $type ?: self::$_type,
        ], $customsInfo);
        return self::$_client->indices()->putMapping($params);
    }

    /**
     * 查询
     * @param string $index index名称
     * @param string $type type名称
     * @param array $query 匹配条件
     * @param array $extCons 排序分组条件
     *
     * @return array|bool false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 15:06
     */
    public static function searchData($index, $type, $query, $extCons = [], $field = [])
    {
        if (empty($index) || empty($query)) {
            return false;
        }
        $params = [
            'index' => $index,
            'type' => $type ?: self::$_type,
            '_source' => $field,
            'track_total_hits' => true,
            //"scroll" => "1m",
            'body' => array_merge([
                'query' => $query
            ], $extCons)
        ];

        return self::$_client->search($params);
    }


    /**
     * 查询总数
     * @param string $index index名称
     * @param string $type type名称
     * @param array $query 匹配条件
     * @return array|bool
     */
    public static function searchCount($index, $type, $query)
    {
        if (empty($index) || empty($query)) {
            return false;
        }
        $params = [
            'index' => $index,
            'type' => $type ?: self::$_type,
            'body' => [
                'query' => $query
            ]
        ];

        return self::$_client->count($params);
    }

    /**
     * 通过ID获取文档信息
     * @param string $index index名称
     * @param string $type type名称
     * @param string $id 文档ID
     *
     * @return array|bool   false 失败
     *
     * @auther chenshuang
     * @date   2020-12-11 10:57
     */
    public static function getDocById($index, $type, $id)
    {
        if (empty($index) || empty($id)) {
            return false;
        }
        $params = [
            'index' => $index,
            'type' => $type ?: self::$_type,
            'id' => $id
        ];
        return self::$_client->get($params);
    }


    /**
     * 添加文档
     * @param string $index index名称
     * @param string $type type名称
     * @param array $rows 新增数据数组
     * @param array $indexParams 自定义id、routing、timestamp
     *
     * @return array|bool  false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 15:06
     */
    public static function addDoc($index, $type, $rows = [], $indexParams = [])
    {
        if (empty($index) || empty($rows)) {
            return false;
        }
        foreach ($rows as $row) {
            $params['body'][] = [
                'index' => array_merge([
                    '_index' => $index,
                    '_type' => $type ?: self::$_type,
                ], $indexParams)
            ];
            $params['body'][] = $row;
        }
        if (!empty($params['body'])) {
            return self::$_client->bulk($params);
        }
        return false;
    }


    /**
     * 批量添加并且指定_id
     * @param string $index index名称
     * @param string $type type名称
     * @param array $rows 新增数据数组
     * @param string $field 指定为_id的字段
     * @param bool $isDel 这个指定为_id的字段是否删除
     * @return array|bool
     */
    public static function insertAllDoc($index, $type, $rows = [], $field = '', $isDel = false)
    {
        if (empty($index) || empty($rows)) {
            return false;
        }
        foreach ($rows as $row) {

            $indexParams = empty($field) || !isset($row[$field]) ? [] : [
                '_id' => $row[$field]
            ];

            if ($isDel) unset($row[$field]);

            $params['body'][] = [
                'index' => array_merge([
                    '_index' => $index,
                    '_type' => $type ?: self::$_type,
                ], $indexParams)
            ];
            $params['body'][] = $row;
        }
        if (!empty($params['body'])) {
            return self::$_client->bulk($params);
        }
        return false;
    }


    /**
     * 根据ID更新数据
     * @param string $index index名称
     * @param string $type type名称
     * @param array $id _id主键
     * @param array $updateData 更新数组
     *
     * @return array|bool  false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 15:16
     */
    public static function updateDocById($index, $type, $id, $updateData = [])
    {
        if (empty($index) || empty($id) || empty($updateData)) {
            return false;
        }
        $params = array_merge([
            'index' => $index,
            'type' => $type ?: self::$_type,
            'id' => $id
        ], [
            'body' => [
                'doc' => $updateData,
            ]
        ]);
        return self::$_client->update($params);
    }


    /**
     * 根据条件更新文档
     * @param string $index index名称
     * @param string $type type名称
     * @param array $query 匹配条件
     * @param array $scriptData 更新数据信息
     *
     * @return array|bool
     *
     * @auther chenshuang
     * @date   2020-12-11 19:42
     */
    public static function updateByQuery($index, $type, $query, $data = [])
    {
        if ( empty($index) || empty($query) || empty($data) ) {
            return false;
        }

        $fields = '';
        foreach ($data as $k=>$v){
            $fields .="ctx._source.{$k} = params.{$k};";
        }
        $fields = trim($fields,';');

        $params = [
            'index' => $index,
            'type'  => $type ?: self::$_type,
            'conflicts' => 'proceed',
            'body'  => array_merge(
                [
                    'query' => $query
                ],
                [
                    'script'=>
                        [
                            'inline' => $fields,
                            'params' => $data,
                        ]
                ]
            )
        ];

        return self::$_client->updateByQuery($params);
    }


    /**
     * 根据ID删除数据
     * @param string $index index名称
     * @param string $type type名称
     * @param array $id _id主键
     *
     * @return array|bool  false 失败
     *
     * @auther chenshuang
     * @date   2020-12-08 15:15
     */
    public static function deleteDocById($index, $type, $id)
    {
        if (empty($index) || empty($id)) {
            return false;
        }
        $params = array_merge([
            'index' => $index,
            'type' => $type ?: self::$_type,
            'id' => $id,
        ]);

        return self::$_client->delete($params);
    }

    /**
     * 根据条件删除数据
     * @param string $index index名称
     * @param array $id _id主键
     *
     * @return array|bool  false 失败
     *
     * @auther zhangzilong
     * @date   2020-12-08 15:15
     */
    public static function deleteDocByQuery($index, $type, $query)
    {
        if (empty($index) || empty($query)) {
            return false;
        }

        $params = [
            'index' => $index, // 索引名
            //如果出现版本冲突，如何处理？proceed表示继续更新，abort表示停止更新
            'conflicts' => 'proceed',
            'body' => [
                'query' => $query
            ],
        ];

        return self::$_client->deleteByQuery($params);
    }

    /**
     * 条件数组转为ES检索条件
     * @param  data list
     * 传参示例：
     *
     * $esCondition = [
     *       ['roomname'  , 'match_phrase'   ,  trim($where['m.roomname'][1],'%')], //会分词
     *       ['roomstate' , '='            ,  0],
     *       ['companyid' , '='            ,  $companyid],
     *       ['roomtype'  , 'lt'                ,  $where['m.roomtype'][0][1]],
     *       ['roomtype'  , 'neq'            ,  $where['m.roomtype'][1][1]],
     *       ['endtime'   , 'gt'                ,  $where['m.endtime'][1]],
     *       ['starttime' , 'lte'            ,  $where['m.starttime'][0][1]],
     *   ];
     * @return array|bool  ES检索条件
     *
     * @auther zhanzilong
     * @date   2020-12-24 15:15
     */
    public function whereToEs($where = [])
    {
        $query = [];
        foreach ($where as $key => $val) {
            switch ($val[1]) {
                case '=':
                    $query['bool']['must'][] = [
                        'match' => [
                            $val[0] => $val[2],
                        ]
                    ];
                    break;
                case 'match_phrase':
                    $query['bool']['filter']['bool']['must'][] = [
                        'match_phrase' => [
                            $val[0] => $val[2],
                        ]
                    ];
                    break;
                case 'like':
                    $query['bool']['filter']['bool']['must'][] = [
                        'wildcard' => [
                            $val[0] => '*' . $val[2] . '*',
                        ]
                    ];
                    break;
                case 'in':
                    $query['bool']['must'][] = [
                        'terms' => [
                            $val[0] => $val[2],
                        ]
                    ];
                    break;
                case 'neq':
                    $query['bool']['filter']['bool']['must_not'][] = [
                        'match' => [
                            $val[0] => $val[2],
                        ]
                    ];
                    break;
                case 'gt':
                    $query['bool']['filter']['bool']['must'][]['range'][$val[0]] = [
                        $val[1] => $val[2]
                    ];
                    break;
                case 'lt':
                    $query['bool']['filter']['bool']['must'][]['range'][$val[0]] = [
                        $val[1] => $val[2]
                    ];
                    break;
                case 'gte':
                    $query['bool']['filter']['bool']['must'][]['range'][$val[0]] = [
                        $val[1] => $val[2]
                    ];
                    break;
                case 'lte':
                    $query['bool']['filter']['bool']['must'][]['range'][$val[0]] = [
                        $val[1] => $val[2]
                    ];
                    break;
                default:
            }
        }
        return $query;

    }
}
