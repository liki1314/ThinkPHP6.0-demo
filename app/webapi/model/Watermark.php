<?php

declare(strict_types=1);

namespace app\webapi\model;

use app\common\service\Upload;

class Watermark extends Base
{
    /** 视频 */
    const VIDEO_TYPE = 1;
    /** 视频分类 */
    const CATEGORY_TYPE = 2;
    /** 企业 */
    const COMPANY_TYPE = 3;

    protected $deleteTime = false;

    public function setImageAttr($value, $data)
    {
        $this->set('url', Upload::putFile($value));

        if (isset($data['category_id'])) {
            $this->set('watermark_id', $data['category_id']);
            $this->set('watermark_type', self::CATEGORY_TYPE);
        } else {
            $this->set('watermark_id', request()->company['companyid']);
            $this->set('watermark_type', self::COMPANY_TYPE);
        }

    }

    public function getUrlAttr($value)
    {
        return Upload::getFileUrl($value);
    }


    public static function onBeforeWrite($model)
    {
        if ($model['watermark_type'] == self::CATEGORY_TYPE) {

            $exists = self::where([
                'watermark_type' => self::CATEGORY_TYPE,
                'watermark_id' => $model['watermark_id']])->find();

            if ($exists) {
                $model->exists(true);
                $model[$model->getPk()] = $exists[$model->getPk()];
            }
        }
    }
}
