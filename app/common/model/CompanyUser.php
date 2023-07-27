<?php

declare(strict_types=1);

namespace app\common\model;

use think\Model;
use app\gateway\model\UserAccount;

/**
 * @mixin \think\Model
 */
class CompanyUser extends Model
{
    public function user()
    {
        return $this->belongsTo(UserAccount::class)->bind(['account', 'locale', 'mobile', 'code']);
    }
}
