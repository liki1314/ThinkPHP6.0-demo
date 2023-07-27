<?php

declare(strict_types=1);

namespace app\gateway\model;

use app\common\service\Upload;
use Exception;
use think\exception\ValidateException;
use think\File;
use think\Model;
use think\helper\Arr;

/**
 * @mixin \think\Model
 */
class UserAccount extends Model
{
    /** 手机+短信登录 */
    const SMS_LOGIN = 1;
    /** 手机+密码登录 */
    const PWD_LOGIN = 2;
    /** 账号+密码登录 */
    const ACCOUNT_LOGIN = 3;

    /** 启用状态 */
    const ENABLE_STATE = 1;
    /** 停用状态 */
    const DISABLE_STATE = 0;


    protected $deleteTime = false;

    protected $json = ['extend_info'];

    protected $jsonAssoc = true;

    public static function onBeforeInsert($model)
    {
        $model->set('state', $model['state'] ?? self::ENABLE_STATE);
        $model->set('salt', get_rand_str());

        if (isset($model->getData()['pwd'])) {
            $model->set('pwd', $model->getUserPwd($model->getData()['pwd']));
        }

        $model->set('username', $model['username'] ?? $model['account']);
    }

    /**
     * 生成用户密文密码
     * @param $pwd  明文密码
     * @return string
     */
    final public function getUserPwd($pwd)
    {
        return md5(md5(strval($pwd)) . $this->getAttr('salt'));
    }

    public function setMobileAttr($value, $data)
    {
        $this->set('account', config("countrycode.abbreviation_code.{$data['locale']}") . $value);

        return $value;
    }

    public function setAvatarFileAttr(File $value)
    {
        $this->set('avatar', Upload::putFile($value));
    }

    public function setNicknameAttr($value)
    {
        $this->set('username', $value);
    }

    /**
     * 登录
     *
     * @param string $locale 国际区号
     * @param string $mobile 手机号或账号
     * @param int $mode 登录方式 1手机+短信 2手机+密码 3账号+密码
     * @param $pwd
     * @return UserAccount
     * @throws Exception
     */
    public static function login($locale, $mobile, $mode, $pwd): UserAccount
    {
        self::startTrans();
        try {
            if ($mode == self::ACCOUNT_LOGIN) {
                $model = self::where('extend_info->domain_account', $mobile)->lock(true)->findOrEmpty();
            } else {
                $model = self::where('account', config("countrycode.abbreviation_code.$locale") . $mobile)->lock(true)->findOrEmpty();
            }

            if ($mode != self::SMS_LOGIN) {
                // 密码登录账号不存在
                if ($model->isEmpty()) {
                    throw new ValidateException(lang('Account does not exist'));
                }
                // 密码错误
                if ($model->getUserPwd($pwd) != $model['pwd']) {
                    throw new ValidateException(lang('password error'));
                }
            }

            // 短信登录账号不存在直接注册
            if ($mode == self::SMS_LOGIN && $model->isEmpty()) {

                $model->locale = $locale;
                $model->mobile = $mobile;
                $model->save();
            }

            self::commit();
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }

        return $model;
    }

    /**
     * 账号校验
     *
     * @param string $account 账号
     * @param int $mode 登录方式 1短信 2密码
     * @return UserAccount
     */
    public static function verify($account, $mode, $pwd): UserAccount
    {
        $model = self::where('account', $account)->findOrFail();

        if ($mode == self::PWD_LOGIN) {
            // 密码错误
            if ($model->getUserPwd($pwd) != $model['pwd']) {
                throw new ValidateException(lang('password error'));
            }
        }

        return $model;
    }

    /**
     * 修改手机号
     *
     * @param string $locale 国际区号
     * @param string $mobile 手机号
     * @return void
     */
    public function modifyMobile($locale, $mobile)
    {
        $account = config("countrycode.abbreviation_code.$locale") . $mobile;

        $this->transaction(function () use ($account, $mobile) {
            $this->save(['account' => $account]);
        });
    }

    public function getAvatarAttr($value)
    {
        return !empty($value) ? Upload::getFileUrl($value) : Upload::getFileUrl('/image/man_teacher.png','local');
    }

    public function getCodeAttr($value, $data)
    {
        return config('countrycode.abbreviation_code.' . $data['locale']) ?: '86';
    }

    public function getMobileAttr($value, $data)
    {
        return $data['account'] ? substr((string)$data['account'], strlen($this->getAttr('code'))) : '';
    }

    /**
     * 保存用户信息
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function saveUser($data = [])
    {
        $account = config('countrycode.abbreviation_code.' . ($data['locale'] ?? 'CN')) . $data['mobile'];
        $user = self::where('account', $account)->findOrEmpty();

        self::startTrans();
        try {
            $data['account'] = $account;
            if ($user->isEmpty()) {
                $user = self::create(Arr::only($data, ['username', 'account', 'locale', 'pwd']));
            }
            $data['user_account_id'] = $user->getKey();
            self::commit();
        } catch (Exception $e) {
            self::rollback();
            throw $e;
        }

        return $data;
    }

    /**
     * 修改密码
     * @param $userid
     * @param $pwd
     * @return bool
     */
    public function updatePwd($userid, $pwd)
    {
        $model = self::findOrFail($userid);
        $save = [
            'pwd' => $model->getUserPwd($pwd),
        ];
        return $model->save($save);
    }
}
