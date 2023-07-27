<?php

namespace app;

use app\common\exception\LiveException;
use thans\jwt\exception\JWTException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        // 参数验证错误
        if ($e instanceof ValidateException) {

            if (is_array($e->getError())) {
                return json($e->getError());
            }

            return json(['result' => $e->getCode() ?: -1, 'msg' => $e->getMessage()]);
        }

        // token失效
        if ($e instanceof JWTException) {
            return response($e->getMessage(), 302, [], \app\common\service\Text::class);
        }

        if ($e instanceof ModelNotFoundException) {
            return json(['result' => -1, 'msg' => lang('data Not Found')]);
        }

        if ($e instanceof LiveException) {
            return json(['result' => -1, 'msg' => $e->getMessage()]);
        }

        if ($e instanceof HttpException) {
            return response(lang('System exception'), $e->getStatusCode(), [], \app\common\service\Text::class);
        }

        if (!env('APP_DEBUG')) {
            return json(['result' => $e->getCode() ?: -1, 'msg' => lang('System exception')]);
        }

        return parent::render($request, $e);
    }
}
