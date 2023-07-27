<?php


namespace app\common\service;


use thans\jwt\contract\Storage;
use think\facade\Cache;

class JwtStorage implements Storage
{
    public function delete($key)
    {
        return Cache::delete($this->getCacheKey($key));
    }

    public function get($key)
    {
        return Cache::get($this->getCacheKey($key));
    }

    public function set($key, $val, $time = 0)
    {
        return Cache::set($this->getCacheKey($key), $val, $time);
    }

    /**
     * 获取实际的缓存标识
     * @access public
     * @param string $name 缓存名
     * @return string
     */
    public function getCacheKey(string $name): string
    {
        return config('jwt.cache_prefix') . $name;
    }
}
