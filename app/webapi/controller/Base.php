<?php
declare (strict_types = 1);

namespace app\webapi\controller;

use app\BaseController;

abstract class Base extends BaseController
{
    protected function success($data = [])
    {
        return json(['result' => 0, 'data' => $data, 'msg' => lang('success')]);
    }
}
