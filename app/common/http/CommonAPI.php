<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-02
 * Time: 18:11
 */

namespace app\common\http;

use app\common\exception\LiveException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Exception;
use Psr\Http\Message\ResponseInterface;
use think\facade\Lang;

class CommonAPI extends Base
{
    protected $name = 'app.host.commonapi';

    protected function initialize()
    {
        $this->middlewares['auth'] = Middleware::mapRequest(
            function (RequestInterface $request) {
                //if (isset(request()->user)) {
                parse_str($request->getBody()->getContents(), $data);
                $responseBody = stripslashes(json_encode($data, JSON_UNESCAPED_UNICODE));
                $sign = base64_encode(hash_hmac('sha256', $responseBody, $data['key'], true));
                parse_str($request->getUri()->getQuery(), $query);

                return $request->withHeader('Accept-Language', Lang::getLangSet())
                    ->withHeader('SIGNATURE', $sign)
                    ->withHeader('Tk-authkey', $data['key'])
                    ->withUri($request->getUri()->withQuery(http_build_query($query)));
                // }



                return $request;
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

                        if (!(isset($query['error']) || defined('self::NO_ERROR'))) {
                            $stream = $response->getBody();
                            $stream->rewind();
                            $result = json_decode($stream->getContents(), true);

                            if (!isset($result['result'])) {
                                throw new LiveException('服务异常');
                            }

                            if ($result['result'] != 0) {
                                throw new LiveException(lang(config('live.lives.talk.error_code')[$result['result']] ?? $result['msg'] ?? '服务异常'), $result['result']);
                            }
                        }

                        return $response;
                    }
                );
            };
        };
    }

    /**
     * 获取计费标准
     * @param $authKey
     * @return mixed
     */
    public function getChargeStandard($authKey)
    {
        $res = self::httpPost("/CommonAPI/getChargeStandard", [
            'key' => $authKey,
            'source' => 4
        ]);

        return $res['data'];
    }

    /**
     * 子企业充值
     * @param $authKey
     * @param $amount
     * @param $userid
     */
    public function recharge($authKey, $amount, $userid)
    {
        self::httpPost("/CommonAPI/childComoanyRecharge", [
            'key' => $authKey,
            'rechargeamount' => $amount,
            'operationuserid' => $userid,
            'remarks' => '用户通过主账号向子账号充值',
        ]);
    }
}
