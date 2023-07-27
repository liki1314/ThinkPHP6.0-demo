<?php

declare(strict_types=1);

namespace app\webapi\middleware;

use think\exception\ValidateException;
use app\common\model\Company;
use think\facade\Cache;

class CheckSign
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     * @throws Exception
     */
    public function handle($request, \Closure $next)
    {
        if (!$request->header('key')) {
            throw new ValidateException(lang('header_key_require'));
        }

        $model = Cache::remember('company:authkey:' . $request->header('key'), function () use ($request) {
            return Company::where('authkey', $request->header('key'))->find();
        });

        if (
            empty($model)
            || $model['companystate'] == 0 && $model['endtime'] < date('Y-m-d')
            || in_array($model['companystate'], [4, 5])
        ) {
            throw new ValidateException(lang('企业不存在或不可用'));
        }

        $request->company = $model;

        return $next($request);
    }
}
