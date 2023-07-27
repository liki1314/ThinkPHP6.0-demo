<?php

declare(strict_types=1);

namespace app\webapi\controller\v1;

use thans\jwt\facade\JWTAuth;
use app\gateway\model\UserAccount;
use think\exception\ValidateException;

class User extends \app\webapi\controller\Base
{
    public function login($account, $locale = 'CN')
    {
        if ($this->request->header('content-md5') !== md5($this->request->header('key') . $this->request->getContent() . $this->request->header('time'))) {
            throw new ValidateException(lang('参数验证错误'));
        }

        $model = UserAccount::where('account', config("countrycode.abbreviation_code.$locale") . $account)->find();
        $model['user_account_id'] = $model->getKey();
        $model['userid'] = $model['live_userid']??$model->getKey();
        $token = JWTAuth::builder(['data' => $model->visible(['user_account_id', 'userid', 'avatar', 'username', 'account', 'locale'])->toJson()]);

        return $this->success(['token' => $token]);
    }
}
