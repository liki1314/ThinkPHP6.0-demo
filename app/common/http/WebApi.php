<?php

namespace app\common\http;

use app\common\exception\LiveException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use app\common\model\Company;
use app\Request;
use Psr\Http\Message\ResponseInterface;
use think\facade\Lang;

class WebApi extends Base
{
    protected $name = 'app.host.webapi';

    protected function initialize()
    {
        $this->middlewares['auth'] = Middleware::mapRequest(
            function (RequestInterface $request) {
                $query = invoke(function (Request $req) use (&$request) {
                    parse_str($request->getUri()->getQuery(), $query);
                    if (isset($req->user['company_id'])) {
                        $query['key'] = Company::cache(true)->find($req->user['company_id'])['authkey'];
                        $request->withHeader('Tk-authkey', $query['key']);
                    }

                    return $query;
                });

                return $request->withHeader('Accept-Language', Lang::getLangSet())->withUri($request->getUri()->withQuery(http_build_query($query)));
            }
        );

        $this->middlewares['result'] = function (callable $handler) {
            return function (
                RequestInterface $request,
                array $options
            ) use ($handler) {
                $promise = $handler($request, $options);
                return $promise->then(
                    function (ResponseInterface $response) use ($request) {
                        parse_str($request->getUri()->getQuery(), $query);

                        if (!(isset($query['error']) || defined('static::NO_ERROR'))) {
                            $stream = $response->getBody();
                            $stream->rewind();
                            $result = json_decode($stream->getContents(), true);

                            if (isset($result['result']) && $result['result'] != 0) {
                                throw new LiveException(lang(config('live.lives.talk.error_code')[$result['result']] ?? $result['msg'] ?? '服务异常'), $result['result']);
                            }
                        }

                        return $response;
                    }
                );
            };
        };
    }
}
