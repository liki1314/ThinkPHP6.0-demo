<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\AuthRule;

class Auth extends Base
{
    public function index()
    {
        //
    }

    public function tree()
    {
        return $this->success(AuthRule::suffix(config('app.auth_rule_suffix'))->select()->visible(['id', 'name', 'fid', 'code'])->tree());
    }

    public function save()
    {
        //
    }

    public function read($id)
    {
        //
    }

    public function update($id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }
}
