<?php

namespace app\common\pay;

/**
 * 支付服务接口
 */
abstract class Driver
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 驱动句柄
     * @var object
     */
    protected $handler = null;

    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->handler, $method], $args);
    }

    public function pay($order, $method)
    {
        if (method_exists($this, $method)) {
            return $this->$method($order);
        }
        return call_user_func_array([$this->handler, $method], [$order]);
    }
}
