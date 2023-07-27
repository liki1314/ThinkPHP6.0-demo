<?php

namespace app\common\sms;

use Overtrue\EasySms\Message;
use Overtrue\EasySms\Contracts\GatewayInterface;
use Overtrue\EasySms\Strategies\OrderStrategy;

class CodeMessage extends Message
{
    protected $code;
    protected $content;
    protected $lang;
    protected $locale;
    protected $strategy = OrderStrategy::class;    // 定义本短信的网关使用策略，覆盖全局配置中的 `default.strategy`
    protected $gateways = ['chuanglan', 'qcloud']; // 定义本短信的适用平台，覆盖全局配置中的 `default.gateways`

    /**
     * qcloud 0=>国内短信模板id 1=>国际短信模板id
     *
     * @var array
     */
    protected $template = [
        'qcloud' => [
            [
                'zh-cn' => "1177226",
                'zh-tw' => "1203708",
                'en' => "1203720",
                'en-us' => "1203720",
                'wssx' => "1177226",
            ],
            [
                'zh-cn' => "1203689",
                'zh-tw' => "1177238",
                'en' => "1177245",
                'en-us' => "1177245",
                'wssx' => "1203689",
            ],
        ]
    ];

    public function __construct($code, $locale, $lang = 'zh-cn')
    {
        $this->code = $code;
        $this->lang = $lang;
        $this->locale = $locale;

        $templates = config('sms.template.language');
        $sms_config = $templates[$lang] ?? $templates[config('lang.default_lang')];
        $smssign = ($locale == 'CN') ? $sms_config['sign'] : '';
        $this->content = $smssign . str_replace('{s6}', $code, $sms_config['business']['check']['label']);
    }

    // 定义直接使用内容发送平台的内容
    public function getContent(GatewayInterface $gateway = null)
    {
        return $this->content;
    }

    // 定义使用模板发送方式平台所需要的模板 ID
    public function getTemplate(GatewayInterface $gateway = null)
    {
        return $this->template[$gateway->getName()][$this->locale == 'CN' ? 0 : 1][$this->lang] ?? null;
    }

    // 模板参数
    public function getData(GatewayInterface $gateway = null)
    {
        return [
            'code' => (string)$this->code
        ];
    }
}
