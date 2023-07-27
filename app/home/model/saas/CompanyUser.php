<?php
declare(strict_types=1);

namespace app\home\model\saas;

class CompanyUser extends Base
{

    /** 启用状态 */
    const ENABLE_STATE = 1;
    /** 停用状态 */
    const DISABLE_STATE = 0;

    public function user()
    {
        return $this->belongsTo(UserAccount::class)
            ->bind(['account', 'locale','avatar']);
    }


}
