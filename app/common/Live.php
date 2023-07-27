<?php

namespace app\common;

use think\Manager;
use app\common\live\Driver;
use think\helper\Arr;
use InvalidArgumentException;

/**
 * 直播服务
 */
class Live extends Manager
{
    protected $namespace = '\\app\\common\\live\\driver\\';

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
            return $this->app->config->get('live.' . $name, $default);
        }

        return $this->app->config->get('live');
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
        return $this->getLiveConfig($name, 'type', 'talk');
    }

    protected function resolveConfig(string $name)
    {
        return $this->getLiveConfig($name);
    }

    public function getLiveConfig($live, $name = null, $default = null)
    {
        if ($config = $this->getConfig("lives.{$live}")) {
            return Arr::get($config, $name, $default);
        }

        throw new InvalidArgumentException("Live [$live] not found.");
    }
}
