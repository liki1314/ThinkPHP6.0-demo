<?php
declare (strict_types = 1);
/**
 * Created by PhpStorm.
 * User: 
 * Date: 2021/1/13
 * Time: 14:48
 */

namespace app\clientapi\model;
use app\common\service\Upload;

class Watermark extends Base
{
    protected $deleteTime = false;


    public function getUrlAttr($value)
    {
        return Upload::getFileUrl($value);
    }

}