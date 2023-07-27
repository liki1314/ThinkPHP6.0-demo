<?php

declare(strict_types=1);

namespace app\gateway\controller\v1;

use app\common\wechat\messages\BindAccountHandler;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Message;
use think\Response;
use think\facade\Cache;
use app\common\model\Company;

class WeChat extends \app\BaseController
{
    protected $wechatApp;

    protected function initialize()
    {
        parent::initialize();

        if (!empty($this->request->user['companys']) && count($this->request->user['companys']) == 1) {
            $model = Company::getDetailById($this->request->user['companys'][0]);
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
        // 扫描带参数二维码事件
        $this->wechatApp->server->push(BindAccountHandler::class, Message::EVENT);

        $this->wechatApp->server->serve()->send();
        exit;
    }

    public function qrCode()
    {
        $param = $this->request->user['user_account_id'];
        if (!empty($this->request->user['companys']) && count($this->request->user['companys']) == 1) {
            $param .= '-' . $this->request->user['companys'][0];
        }
        $result = $this->wechatApp->qrcode->temporary($param, 6 * 24 * 3600);
        $url = $this->wechatApp->qrcode->url($result['ticket']);
        $content = file_get_contents($url);

        return Response::create($content)->contentType('image/jpg');
    }
}
