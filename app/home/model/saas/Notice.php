<?php

declare(strict_types=1);

namespace app\home\model\saas;

use think\Request;

class Notice extends Base
{
    protected $json = ['extras'];

    protected $deleteTime = false;

    protected $globalScope = ['accountId'];

    public function scopeAccountId($query)
    {
        $this->invoke(function (Request $request) use ($query) {
            if (isset($request->user['user_account_id']) && in_array('user_account_id', $query->getTableFields())) {
                $query->where('__TABLE__.user_account_id', $request->user['user_account_id'])->where('__TABLE__.userroleid', $request->user['current_identity']);
            }
        });
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('id,title,content,create_time,extras,type,read_time')
            ->order('create_time', 'desc')
            ->append(['is_read'])
            ->hidden(['read_time']);
    }

    public function getCreateTimeAttr($value)
    {
        return $value;
    }

    public function getIsReadAttr($value, $data)
    {
        return $data['read_time'] ? 1 : 0;
    }
}
