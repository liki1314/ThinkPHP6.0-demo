<?php

declare(strict_types=1);

namespace app\common\http;

use GuzzleHttp\{Client, HandlerStack, Middleware, Promise};
use GuzzleHttp\Handler\CurlHandler;
use app\common\http\log\MessageFormatter;
use GuzzleHttp\ClientInterface;
use think\file\UploadedFile;

abstract class Base
{
    /**
     * 请求客户端对象数组
     * @var Client[]
     */
    protected static $clients = [];

    /**
     * 当前请求客户端对象
     * @var Client
     */
    protected $client;

    protected $handlerStack;

    protected $name;

    protected $middlewares = [];

    public function __construct()
    {
        $this->middlewares = [
            // 'log' => Middleware::log(app('log'), new MessageFormatter()),
        ];

        $this->initialize();
    }

    public function getHttpClient(): ClientInterface
    {
        if (!($this->client instanceof ClientInterface)) {
            if (isset(self::$clients[$this->name])) {
                $this->client = self::$clients[$this->name];
            } else {
                $this->client = self::$clients[$this->name] = new Client([
                    'handler' => $this->getHandlerStack(),
                    'base_uri' => $this->name ? config($this->name) : '',
                    'connect_timeout' => config('app.httpclient.timeout'),
                ]);
            }
        }

        return $this->client;
    }

    public function getHandlerStack(): HandlerStack
    {
        if ($this->handlerStack) {
            return $this->handlerStack;
        }

        $config = config('app.httpclient.handler');
        switch ($config) {
                /* case 'curl':
                $handler = new CurlHandler();
                break; */
            default:
                $handler = new CurlHandler();
                break;
        }

        $this->handlerStack = HandlerStack::create($handler);

        foreach ($this->middlewares as $name => $middleware) {
            $this->handlerStack->push($middleware, $name);
        }

        $this->handlerStack->push(
            Middleware::log(
                app('log'),
                new MessageFormatter('{method} {uri} {req_body} {res_body} {code}'),
                'rpc'
            ),
            'log'
        );

        return $this->handlerStack;
    }

    protected function initialize()
    {
        # code...
    }

    public static function httpGet($url, $query = [])
    {
        $options = [];
        if (is_array($url)) {
            foreach ($url as $key => $value) {
                if (isset($query[$key])) {
                    $options[$key] = ['query' => $query[$key]];
                }
            }
        } elseif (!empty($query)) {
            $options = ['query' => $query];
        }
        return (new static())->request($url, 'GET', $options ?: $query);
    }

    public static function httpPost(string $url, array $data = [], $method = 'POST')
    {
        $d = [];
        if (isset($data['auth'])) {
            $d['auth'] = $data['auth'];
            unset($data['auth']);
        }
        $d['form_params'] = $data;
        return (new static())->request($url, $method, $d);
    }

    public static function httpJson(string $url, array $data = [], array $query = [], $method = 'POST')
    {
        return (new static())->request($url, $method, ['query' => $query, 'json' => $data]);
    }

    public static function httpMultipart(string $url, array $data = [])
    {
        $options = [];

        foreach ($data as $key => $value) {
            $tmp = ['name' => $key];
            if ($value instanceof UploadedFile) {
                $tmp['contents'] = fopen($value->getRealPath(), 'r');
                $tmp['filename'] = $value->getOriginalName();
            } else {
                $tmp['contents'] = $value;
            }
            array_push($options, $tmp);
        }

        return (new static())->request($url, 'POST', ['multipart' => $options]);
    }

    public function request($url, string $method = 'GET', array $options = [], $returnRaw = false)
    {
        if (is_array($url)) {
            foreach ($url as $key => $value) {
                $promises[$key] = $this->getHttpClient()->requestAsync($method, $value, $options[$key] ?? $options);
            }
            $response = Promise\unwrap($promises);
        } else {
            $response = $this->getHttpClient()->request($method, $url, $options);
        }

        if ($returnRaw) {
            return $response;
        }

        if (!is_array($response)) {
            $response = [$response];
        }

        $rs = [];
        foreach ($response as $key => $value) {
            $stream = $value->getBody();
            $stream->rewind();
            $rs[$key] = json_decode($stream->getContents(), true) ?? $stream->getContents();
        }

        return count($rs) > 1 ? $rs : array_shift($rs);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->getHttpClient(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        return call_user_func_array([(new static())->getHttpClient(), $method], $args);
    }
}
