<?php

namespace app\common\live;

use app\Request;

/**
 * 直播服务接口
 */
abstract class Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }
}
