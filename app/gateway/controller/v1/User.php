<?php

declare(strict_types=1);

namespace app\gateway\controller\v1;

use app\common\lib\sms\Rule;
use thans\jwt\facade\JWTAuth;
use think\exception\ValidateException;
use app\BaseController;
use app\common\facade\Live;
use app\common\model\Company;
use app\common\service\Upload;
use app\common\sms\CodeMessage;
use app\gateway\model\UserAccount;
use app\gateway\validate\User as ValidateUser;
use think\facade\Db;
use think\facade\Log;
use think\facade\Lang;
use think\helper\Arr;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\PhoneNumber;

class User extends BaseController
{
    use Rule;


    public function getSmsCode()
    {
        $this->validate($this->param, ValidateUser::class . '.smscode');

        $lang = $this->request->param('template', $this->request->param('lang', 'zh-cn'));
        $locale = $this->request->post('locale', 'CN');
        $phone = $this->request->post('mobile', '');

        $abbreviation_code = config('countrycode.abbreviation_code');
        $locale = isset($abbreviation_code[$locale]) ? $locale : 'CN';
        $areacode = $abbreviation_code[$locale];
        $code = $this->createVerificationCode($areacode . $phone, config('sms.business_type'), 10 * 60);

        if (empty($code)) {
            throw new ValidateException(lang('Verification code sending failed'));
        }

        $easySms = new EasySms(config('easysms'));
        $gateway = $areacode == '86' ? 'chuanglan' : 'qcloud';
        // $gateway = 'qcloud';
        $message = new CodeMessage($code, $locale, $lang);
        $easySms->send(($locale == 'CN') ? $phone : new PhoneNumber($phone, $areacode), $message, [$gateway]);

        return $this->success();
    }

    public function login()
    {
        $this->validate($this->param, ValidateUser::class . '.login');

        $model = Db::transaction(function () {
            $model = UserAccount::login(
                $this->param['locale'] ?? null,
                $this->param['mobile'],
                $this->param['mode'],
                $this->param['pwd_or_code']
            );

            if ($this->request->get('company_id') && Company::cache(true)->find($this->request->get('company_id'))) {
                $id = Db::name('front_user')
                    ->where('user_account_id', $model->getKey())
                    ->where('company_id', $this->request->get('company_id'))
                    ->where('userroleid', 8)
                    ->value('id');

                if (!$id) {
                    Db::name('front_user')
                        ->extra('IGNORE')
                        ->insert([
                            'user_account_id' => $model->getKey(),
                            'company_id' => $this->request->get('company_id'),
                            'userroleid' => 8,
                            'username' => $model['username'],
                            'nickname' => $model['username'],
                            'create_time' => time(),
                        ]);
                }

                $model['company_id'] = $this->request->get('company_id');
            }

            return $model;
        });

        $model['user_account_id'] = $model->getKey();
        $model['userid'] = $model['live_userid']??$model->getKey();
        $token = JWTAuth::builder(['data' => $model->visible(['user_account_id', 'userid', 'username', 'account', 'company_id'])->toJson()]);

        return $this->success([
            'token' => $token,
            'user_account_id' => strval($model['user_account_id'])
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::token()->get());
        return $this->success();
    }

    public function update()
    {
        $model = UserAccount::cache(true)->findOrFail($this->request->user['user_account_id']);
        $model->save(Arr::only($this->param, ['username', 'avatar']));

        return $this->success();
    }

    public function verify()
    {
        $this->validate($this->param, ValidateUser::class . '.verify');

        UserAccount::verify(
            $this->request->user['account'],
            $this->param['mode'],
            $this->param['pwd_or_code']
        );

        return $this->success();
    }

    public function modifyMobile()
    {
        $this->validate($this->param, ValidateUser::class . '.modifyMobile');

        $model = UserAccount::verify(
            $this->request->user['account'],
            $this->param['mode'],
            $this->param['pwd_or_code']
        );
        $model->modifyMobile($this->param['new_locale'], $this->param['new_mobile']);

        JWTAuth::invalidate(JWTAuth::token()->get());

        return $this->success();
    }

    public function read()
    {
        $model = UserAccount::field('locale,account,username,avatar,live_userid as userid')
            ->findOrFail($this->request->user['user_account_id'])
            ->append(['mobile']);

        return $this->success($model);
    }

    public function refreshToken()
    {
        return $this->success(['token' => JWTAuth::refresh()]);
    }

    public function countrycode()
    {
        $searchstr = str_replace(' ', '', $this->request->request('searchstr'));

        $countrycode = config('countrycode');

        $code_list = $countrycode[Lang::getLangSet()];

        if ($searchstr) {
            $search_list = [];
            foreach ($code_list as $key => $value) {
                if ((strpos($value['country'], $searchstr) !== false) || (strpos($value['code'], str_replace('+', '', $searchstr)) !== false)) {
                    $search_list[] = $value;
                }
            }
            $code_list = $search_list;
        }

        $list = [];
        foreach ($code_list as $key => $value) {
            $list[] = [
                'country' => $value['country'],
                'locale' => $value['abbreviation'],
                'code' => $value['code'],
            ];
        }

        return $this->success($list);
    }

    public function forgotPwd()
    {
        $this->validate($this->param, ValidateUser::class . '.forgotPwd');

        $model = UserAccount::where('account', config("countrycode.abbreviation_code.{$this->param['locale']}") . $this->param['mobile'])->findOrFail();
        $model->save(['pwd' => $model->getUserPwd($this->param['new_pwd'])]);

        $model['user_account_id'] = $model->getKey();
        $model['userid'] = $model['live_userid'];
        $token = JWTAuth::builder(['data' => $model->visible(['user_account_id', 'userid', 'username', 'account'])->toJson()]);

        return $this->success(['token' => $token]);
    }

    public function modifyPwd()
    {
        $this->validate($this->param, ValidateUser::class . '.updatePwd');

        $model = UserAccount::where('account', $this->request->user['account'])->findOrFail();
        $model->save(['pwd' => $model->getUserPwd($this->param['new_pwd'])]);

        return $this->success();
    }

    public function checkMobile($locale, $mobile)
    {
        $this->validate($this->param, ValidateUser::class . '.checkMobile');

        return $this->success(['exists' => !UserAccount::where('account', config('countrycode.abbreviation_code.' . $locale) . $mobile)->findOrEmpty()->isEmpty()]);
    }

    /**
     * 意见箱
     */
    public function suggestion()
    {
        $rule = [
            'files' => [
                'array',
            ],
            'content' => [
                'require',
            ],
        ];

        $message = [
            'files.array' => lang('files_error'),
            'content.require' => lang('content_error'),
        ];

        $this->validate($this->param, $rule, $message);

        $save = Arr::only($this->param, ['files', 'content', 'label', 'tag']);
        if (isset($save['files'])) {
            $save['files'] = implode(
                ',',
                array_map(function ($value) {
                    return Upload::getFileUrl($value);
                }, $save['files'])
            );
        }
        Log::channel('suggestion')->record($save);
        return $this->success();
    }
}
