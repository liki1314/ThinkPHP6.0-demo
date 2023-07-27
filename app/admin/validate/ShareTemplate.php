<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-05-11
 * Time: 15:07
 */

namespace app\admin\validate;

use think\Validate;
use app\admin\model\Room;
use app\admin\model\MicroCourse;
use app\admin\model\ShareTemplate as ShareTemplateModel;

class ShareTemplate extends Validate
{


    protected $rule = [
        'is_default' => ['require', 'in:0,1'],
        'type' => ['require', 'in:4,6'],
        'content' => ['require', 'array', 'checkContent']
    ];


    protected $message = [
        'is_default' => 'expression_ids_num_type',
        'type' => 'expression_ids_num_type',
        'content' => 'expression_ids_num_type'
    ];

    /**
     * content数据类型进行验证
     * @param $value
     * @return bool
     */
    public function checkContent($value)
    {

        if (request()->param("type") == Room::ROOMTYPE_LARGEROOM) {
            //大直播
            foreach (["lesson", "teacher", "time"] as $v) {
                if (!isset($value[$v]) || !self::vTextDataStructure($value[$v])) return false;
            }

            if (!isset($value['qrcode']) || !self::vCoordinateDataStructure($value['qrcode'])) return false;

        } elseif (request()->param("type") == MicroCourse::ROOMTYPE_MICRO) {
            //微录课
            foreach (["name", "intro"] as $v) {
                if (!isset($value[$v]) || !self::vTextDataStructure($value[$v])) return false;
            }

            if (!isset($value['qrcode']) || !self::vCoordinateDataStructure($value['qrcode'])) return false;
        }
        return true;
    }

    /**
     * 验证文字类型数据结构
     * @param $value
     * @return bool
     */
    public static function vTextDataStructure($value)
    {
        if (!isset($value['switch']) || !in_array($value['switch'], [ShareTemplateModel::SWITCH_CLOSE, ShareTemplateModel::SWITCH_OPEN])) return false;

        if (!isset($value['size']) || !is_numeric($value['size'])) return false;
        if (!isset($value['color']) || !is_string($value['color'])) return false;
        if (!self::vCoordinateDataStructure($value)) return false;

        return true;
    }

    /**
     * 验证坐标类型数据结构
     * @param $value
     * @return bool
     */
    public static function vCoordinateDataStructure($value)
    {
        if (!isset($value['coordinate'])) return false;
        if (!isset($value['coordinate']['x']) || !is_numeric($value['coordinate']['x'])) return false;
        if (!isset($value['coordinate']['y']) || !is_numeric($value['coordinate']['y'])) return false;

        return true;
    }


}
