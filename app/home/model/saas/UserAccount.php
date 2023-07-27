<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\common\service\Upload;
use think\Model;

class UserAccount extends Model
{
    /** 启用状态 */
    const ENABLE_STATE = 1;
    /** 停用状态 */
    const DISABLE_STATE = 0;

    public function getNameAttr($value, $data)
    {
        return $data['username'] ?? '';
    }

    public function getAvatarAttr($value)
    {
        return Upload::getFileUrl($value); 
    }
}
