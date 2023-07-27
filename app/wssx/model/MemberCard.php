<?php

declare(strict_types=1);

namespace app\wssx\model;

use app\common\service\Upload;
use think\Model;

class MemberCard extends Model
{

    const CARD_OPEN_STATUS = 1; //开启

    const CARD_CLOSE_STATUS = 0; //关闭

    public function searchCardAttr($query, $value)
    {
        $query->field('apple_product_id,id,name,pic,price,period,discount,apple_price')
            ->where('enable',self::CARD_OPEN_STATUS)
            ->order('sort');
    }

    public function getPicAttr($value)
    {
        return $value ? Upload::getFileUrl($value) : '';
    }


}