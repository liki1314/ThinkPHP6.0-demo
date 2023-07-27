<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-11
 * Time: 14:59
 */

namespace app\admin\model;

use app\common\service\Upload;

class ShareTemplate extends Base
{
    protected $json = ['content'];

    /**
     * 是否是默认模板
     */
    const DEFAULT_YES = 1;
    const DEFAULT_NO = 0;

    /**
     * 是否打开显示
     */
    const SWITCH_OPEN = 1;
    const SWITCH_CLOSE = 0;


    /**
     * @param $value
     * @param $data
     * @return string
     */
    public function getCoverAttr($value, $data)
    {
        return Upload::getFileUrl($data['pic']);
    }

    /**
     * 默认搜索器
     * @param $query
     */
    public function searchDefaultAttr($query)
    {
        $query->field([
            'id',
            'pic',
            'is_default',
            'content'
        ])->append(['cover']);
    }

    /**
     * 标题模糊搜索器
     * @param $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

}
