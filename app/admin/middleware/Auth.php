<?php

declare(strict_types=1);

namespace app\admin\middleware;

use app\admin\model\AuthGroup;
use think\exception\ValidateException;
use app\admin\model\CompanyUser;
use app\admin\model\Company;
use app\common\Code;

class Auth
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function handle($request, \Closure $next)
    {
        if (empty($request->user['company_id'])) {
            throw new ValidateException(lang('not_entry_company'));
        }

        if (!($user = CompanyUser::getCompanyUserInfo($request->user['user_account_id'], $request->user['company_id']))) {
            abort(302, lang('账号异常'));
        }

        $model = Company::cache(true, 12 * 3600)->find($request->user['company_id']);

        if ($model['type'] == 6) {
            abort(403, '拒绝访问');
        }

        if (
            $model['companystate'] == Company::TRIAL_STATE
            && $model['endtime'] < date('Y-m-d H:i:s')
            || in_array($model['companystate'], [4, 5])
        ) {
            throw new ValidateException(['result' => Code::DUE]);
        }

        if ($request->has('_code', 'route')) {
            if (
                $request->user['sys_role'] != AuthGroup::SUPER_ADMIN &&
                !in_array($request->route('_code'), CompanyUser::getCompanyUserAuth($request->user['user_account_id'], $request->user['company_id']))
            ) {
                throw new ValidateException(lang('not_permit'));
            }
        }

        $request->user = array_merge($request->user, $user, ['company_model' => $model]);
        return $next($request);
    }
}
