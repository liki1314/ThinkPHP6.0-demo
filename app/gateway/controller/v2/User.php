<?php

declare(strict_types=1);

namespace app\gateway\controller\v2;

use app\common\http\Wool;
use app\common\lib\sms\Rule;
use think\exception\ValidateException;
use app\BaseController;
use app\gateway\validate\User as ValidateUser;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\PhoneNumber;
use app\common\sms\CodeMessage;

class User extends BaseController
{
    use Rule;

    public function getSmsCode()
    {
        $this->validate($this->param, ValidateUser::class . '.smscode');

        if (!isset($this->param['captcha']) || !isset($this->param['captcha']['randstr']) || !isset($this->param['captcha']['ticket'])) {
            throw new ValidateException(lang('Password or verification code error'));
        }
        $wool = new Wool();
        $ret = $wool->randstr($this->param['captcha']['randstr'])->ticket($this->param['captcha']['ticket'])->ip($this->request->ip())->yzm();
        if (!$ret) {
            throw new ValidateException($wool->getError());
        }
        $lang = $this->request->param('lang', 'zh-cn');
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
}
