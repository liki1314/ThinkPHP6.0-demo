<?php

declare(strict_types=1);

namespace app\webapi\controller;

use app\webapi\model\FrontUser;

class User extends Base
{
    public function front($userid)
    {
        $models = FrontUser::alias('a')
            ->join('user_account b', 'a.user_account_id=b.id')
            ->join('company c', 'a.company_id=c.id')
            ->where('b.live_userid', $userid)
            ->field('userroleid,b.account,b.locale,authkey,a.nickname,a.create_time,a.extra_info')
            ->json(['extra_info'])
            ->select();

        return $this->success($models);
    }
}
