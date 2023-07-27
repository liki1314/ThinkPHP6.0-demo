<?php

declare(strict_types=1);

namespace app\common\service;

use think\Cookie;
use think\Response;

/**
 * Text Response
 */
class Text extends Response
{
    /**
     * 输出type
     * @var string
     */
    protected $contentType = 'text/plain';

    public function __construct(Cookie $cookie, $data = '', int $code = 200)
    {
        $this->init($data, $code);
        $this->cookie = $cookie;
    }
}
