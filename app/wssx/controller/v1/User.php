<?php

declare(strict_types=1);

namespace app\wssx\controller\v1;

use app\wssx\controller\Base;
use app\wssx\model\UserAccount;

class User extends Base
{
    public function homepage()
    {
        $model = UserAccount::homepage($this->request->user);

        $user = UserAccount::cache(true)->findOrFail($this->request->user['user_account_id']);

        return $this->success([
            'roomname' => $model['roomname'],
            'serial' => $model['live_serial'],
            'member' => [
                'expire' => isset($user['extend_info']['member_expire']) ? date('Y-m-d H:i:s', $user['extend_info']['member_expire']) : null,
                'name' => $user['extend_info']['member_name'] ?? null,
            ],
            'service_tel' => config('app.service_tel'),
        ]);
    }
}
