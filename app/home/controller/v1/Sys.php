<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use app\common\http\WebApi;


class Sys extends \app\BaseController
{
    /**
     *翻译
     */
    public function translate()
    {
        $rule = [
            'content' => ['require', 'max:5000'],
        ];

        $this->validate($this->param, $rule);

        $params = [
            'sourceText' => $this->param['content'],
            'targetLanguage' => preg_match('/[^\x00-\x80]/', $this->param['content']) ? 'en' : 'zh',
            'key' => config('app.master_company_authkey')
        ];

        $apiRes = WebApi::httpPost('/WebAPI/translate', $params);
        return $this->success(['content' => $apiRes['data'] ?? '']);
    }
}
