<?php

namespace app\common;

use think\Manager;
use app\common\pay\Driver;
use think\helper\Arr;
use InvalidArgumentException;

/**
 * 支付服务
 */
class Pay extends Manager
{
    protected $namespace = '\\app\\common\\pay\\driver\\';

    /**
     * @param null|string $name
     * @return Driver
     */
    public function channel(string $name = null): Driver
    {
        return $this->driver($name);
    }

    /**
     * 获取缓存配置
     * @access public
     * @param null|string $name    名称
     * @param mixed       $default 默认值
     * @return mixed
     */
    public function getConfig(string $name = null, $default = null)
    {
        if (!is_null($name)) {
            return $this->app->config->get('pay.' . $name, $default);
        }

        return $this->app->config->get('pay');
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }

    protected function resolveType(string $name)
    {
        return $this->getPayConfig($name, 'type', 'wechat');
    }

    protected function resolveConfig(string $name)
    {
        return $this->getPayConfig($name);
    }

    public function getPayConfig($pay, $name = null, $default = null)
    {
        if ($config = $this->getConfig("pays.{$pay}")) {
            return Arr::get($config, $name, $default);
        }

        throw new InvalidArgumentException("Pay [$pay] not found.");
    }
}
