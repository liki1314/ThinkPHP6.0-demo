<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\common\model\Company;
use app\common\wechat\messages\BindAccountHandler;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Message;
use think\facade\Log;
use think\Response;
use think\facade\Cache;
use app\admin\model\FrontUser;
class WeChat extends Base
{
    protected $wechatApp;

    protected function initialize()
    {
        parent::initialize();

        if (isset($this->request->user['company_id'])) {
            $model = Company::getDetailById($this->request->user['company_id']);
            if (isset($model['extra_info']['wechat'])) {
                config($model['extra_info']['wechat'], 'wechat');
            }
        }

        $config = [
            'app_id' => config('wechat.app_id'),
            'secret' => config('wechat.secret'),
            'token' => config('wechat.token'),
            'response_type' => 'array',
        ];
        $this->wechatApp = Factory::officialAccount($config);
        $this->wechatApp['cache'] = Cache::store('redis');
    }

    public function server()
    {
        Log::write($this->request->param());
        // 扫描带参数二维码事件
        $this->wechatApp->server->push(BindAccountHandler::class, Message::EVENT);

        $this->wechatApp->server->serve()->send();
        exit;
    }

    public function qrCode()
    {
        $result = $this->wechatApp->qrcode->temporary($this->request->user['user_account_id'] . '-' . $this->request->user['company_id'], 6 * 24 * 3600);
        $url = $this->wechatApp->qrcode->url($result['ticket']);
        $content = file_get_contents($url);

        return Response::create($content)->contentType('image/jpg');
    }


    public function userQrCode($userid)
    {
        $model = FrontUser::where('id',$userid)->findOrFail();
        $result = $this->wechatApp->qrcode->temporary($model['user_account_id'] . '-' . $model['company_id'], 6 * 24 * 3600);
        $url = $this->wechatApp->qrcode->url($result['ticket']);
        $content = file_get_contents($url);

        return Response::create($content)->contentType('image/jpg');
    }
}
