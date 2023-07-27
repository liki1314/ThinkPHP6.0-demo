<?php

declare(strict_types=1);

namespace app\home\model\saas;

use think\model\concern\SoftDelete;
use think\Request;

class Base extends \app\BaseModel
{
    use SoftDelete;
    protected $defaultSoftDelete = 0;
    protected $globalScope = ['companyId'];

    public function scopeCompanyId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (!empty($request->user['company_id']) && in_array('company_id', $query->getTableFields())) {
                $query->where('__TABLE__.company_id', $request->user['company_id']);
            }
        });
    }

    public function scopeUser($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (in_array('user_account_id', $query->getTableFields())) {
                $query->where('__TABLE__.user_account_id', $request->user['user_account_id']);
            }
        });
    }

}
