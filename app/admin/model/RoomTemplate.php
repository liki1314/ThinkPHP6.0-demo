<?php

declare(strict_types=1);

namespace app\admin\model;

use app\common\http\singleton\TemplateSingleton;
use app\common\http\singleton\ThemeSingleton;
use think\helper\Arr;
use think\file\UploadedFile;
use app\common\service\Upload;
use think\exception\ValidateException;

class RoomTemplate extends Base
{
    protected $json = ['extra_info'];

    /** 默认主题 */
    const DEFAULT_THEME = 0;
    /** 颜色主题 */
    const COLOR_THEME = 1;
    /** 自定义图片主题 */
    const IMAGE_THEME = 2;
    /** 一对一 */
    const ONE_TO_ONE = 0;
    /** 一对多 */
    const ONE_TO_MANY = 3;
    /** 大直播 */
    const BIG_LIVE = 4;

    const OPEN_STATE = 0;

    const CLOSE_STATE = 1;

    // 互动工具设置
    const TOOL_SET_FIELDS = [
        'answering_machine' => 61, // 答题器
        'turntable' => 62, // 转盘
        'timer' => 63, // 计时器
        'first_answering_machine' => 64, // 抢答器
        'triazolam' => 65, // 小白板
        'sign_in' => 66, //签到
    ];
    // 功能设置
    const FUNC_SET_FIELDS = [
        'auto_open_audio' => 152, // 上课后自动开启音频
        'auto_open_video' => 153, // 上课后自动开启视频
        'student_close_a' => 154, // 允许学生关闭自己的音频
        'student_close_v' => 155, // 允许学生关闭自己的视频
        'assistantopenav' => 36, // 上课后是否允许助教上台
        'hidden_kicking' => 135, // 隐藏踢人按钮
        'is_video' => 108, // 录制生成mp4文件
        'av_guide' => 20, // 允许跳过设备检测
        'device_check_continue' => 25, // 设备检测在音频不通过时可以继续
        'cut_picture' => 17, // 设备检测不通过，禁止进入教室
        'only_teacher_and_self' => 119, // 只显示老师和自己视频
    ];

    const NAME_MAP = [
        '一对一' => 'one_to_one',
        '一对多' => 'one_to_many',
        '大直播' => 'big_live',
        '线上大直播' => 'big_live',
        '线上一对一' => 'one_to_one',
        '线上一对多' => 'one_to_many',
        '一对一（开启录制生成mp4，收费项目）' => '1-to-1 (Generate MP4 file, extra)',
        '一对多（开启录制生成mp4，收费项目）' => '1-to-many (Generate MP4 file, extra)',
        '一对一（开启录制生成mp4收费项目）' => '1-to-1 (Generate MP4 file, extra)',
        '一对多（开启录制生成mp4收费项目）' => '1-to-many (Generate MP4 file, extra)',
    ];

    const LAYOUT_MAP = [
        0 => [
            51 => "general_layout",
            52 => "main_video_layout",
            53 => "video_layout",
        ],
        3 => [
            1 => "video_top_layout",
            2 => "video_bottom_layout",
            3 => "video_surround_layout",
            4 => "main_video_layout",
            6 => "main_speaker_video_layout",
            7 => "video_layout",
        ],

        4 => [
            3 => "general_layout",
            8 => "courseware_full_screen",
            9 => "video_full_screen",
        ]
    ];

    //模板对应分辨率(id对应webapi 升序)
    const VIDEO_TYPE_LIST = [
        5 => "80*48",
        10 => "80*60",
        9 => "160*120",
        0 => "200*150",
        8 => "320*180",
        1 => "320*240",
        7 => "640*360",
        2 => "640*480",
        6 => "960*540",
        3 => "1280*720",
        4 => "1920*1080",
    ];

    public function setNameAttr($value, $data)
    {
        $extra_info = array_keys(array_merge(self::TOOL_SET_FIELDS, self::FUNC_SET_FIELDS));

        $save = Arr::only($data, $extra_info);
        if ($data['type'] != self::BIG_LIVE && isset($save['sign_in'])) {
            unset($save['sign_in']);
        }

        $this->set('extra_info', $save);

        return $value;
    }

    public function setTypeAttr($value)
    {
        if ($value != Room::ROOMTYPE_LARGEROOM) {
            $this->set('passwordrequired', 1);
        }

        return $value;
    }

    public function searchDefaultAttr($query)
    {
        $tool_set_fields_names = array_flip(self::TOOL_SET_FIELDS);
        $query->alias('t')
            ->leftJoin(['saas_room_template_config' => 'c'], 'c.room_template_id=t.id and c.company_id=' . request()->user['company_id'])
            ->withAttr('extra_info', function ($value, $data) use ($tool_set_fields_names) {
                $extraInfo = [];
                if (!empty($value)) {
                    foreach ($value as $k => $v) {
                        if ($v && !in_array($k, $tool_set_fields_names)) {
                            $extraInfo[$k] = lang($k);
                        }
                    }
                }
                return $extraInfo;

            })
            ->field('t.company_id,IFNULL(c.state,t.state) state,id,name,type,logo,video_ratio,layout_id,theme_id,extra_info,IFNULL(c.sort,t.sort) sort,support_connection')
            ->orderRaw('IFNULL(c.sort,t.sort)')
            ->append(['official', 'show_info']);
    }

    public function searchTypeAttr($query, $value)
    {
        if ($value == 1) {
            $query->whereIn('type', [0, 3]);
        } elseif ($value == 2) {
            $query->where('type', 4);
        }
    }

    public function setLogoAttr(UploadedFile $file)
    {
        $uploadFile = Upload::putFile($file);

        $this->set('logo', $uploadFile);
    }


    public function getLogoAttr($value)
    {
        return Upload::getFileUrl($value);
    }


    public function scopeCompanyId($query)
    {
        if (isset(request()->user['company_id'])) {
            $query->whereIn('__TABLE__.company_id', ['0', request()->user['company_id']]);
        }
    }

    public static function onBeforeDelete($value)
    {
        if ($value['company_id'] == 0) {
            throw new ValidateException(lang('public_tpl_only_read'));
        }
    }


    public static function onBeforeWrite($model)
    {
        if ($model['company_id'] == 0 && $model['state'] == 0) {

            $model->exists(false);

            unset($model[$model->getPk()]);

            $model['company_id'] = request()->user['company_id'];
        }
    }

    public static function onBeforeUpdate($model)
    {

    }

    public static function getRoom()
    {
        return [
            0 => lang('one_vs_one'),
            3 => lang('one_vs_more'),
            4 => lang('big_course')
        ];
    }


    public function getOfficialAttr($value, $data)
    {
        return $data['company_id'] == 0 ? 1 : 0;
    }

    /**
     * @param $value
     * @param $data
     * @return array
     */
    public function getShowInfoAttr($value, $data)
    {
        $theme = ThemeSingleton::getInstance()->getData();
        $room = self::getRoom();
        $video = TemplateSingleton::getInstance()->getVideo();
        $layout_name = TemplateSingleton::getInstance()->getLayoutName();
        $theme_icontype = isset($theme[$data['theme_id']]['icontype']) ? $theme[$data['theme_id']]['icontype'] : 0;

        return [
            'type_name' => $room[$data['type']] ?? '',
            'videotype_name' => $video[$data['video_ratio']] ?? '',
            'layout_name' => isset(self::LAYOUT_MAP[$data['type']][$data['layout_id']]) ? lang(self::LAYOUT_MAP[$data['type']][$data['layout_id']]) : '',
            'theme_name' => isset($theme[$data['theme_id']]['name']) ? $theme[$data['theme_id']]['name'] : lang('Black'),
            'theme_type' => isset($theme[$data['theme_id']]['type']) ? $theme[$data['theme_id']]['type'] : 0,
            'theme_imgurl' => isset($theme[$data['theme_id']]['imgurl']) ? $theme[$data['theme_id']]['imgurl'] : '',
            'theme_color' => isset($theme[$data['theme_id']]['color']) ? $theme[$data['theme_id']]['color'] : '#242530',
            'layouticon_img' => sprintf("%s/image/icon/layouticon_%s_%s_%s.png", config('app.host.local'), $data['type'], $data['layout_id'], $theme_icontype),
        ];
    }

    /**
     * @param $value
     * @param $data
     * @return array
     */
    public function getConfigitemsToolAttr($value, $data)
    {
        $type = [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM, Room::ROOMTYPE_LARGEROOM];

        $configitems_tool = [];

        foreach (self::TOOL_SET_FIELDS as $k => $v) {

            $configitems_tool[$k] = [
                'name' => lang($k),
                'value' => $data['extra_info'][$k] ?? 0,
                'type' => in_array($v, [66]) ? [Room::ROOMTYPE_LARGEROOM] : (!in_array($v, [61, 63]) ? [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM] : $type)
            ];

        }

        return $configitems_tool;
    }

    /**
     * @param $value
     * @param $data
     * @return array
     */
    public function getConfigitemsFuncAttr($value, $data)
    {
        $type = [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM, Room::ROOMTYPE_LARGEROOM];

        $configitems_func = [];

        foreach (RoomTemplate::FUNC_SET_FIELDS as $k => $v) {

            $configitems_func[$k] = [
                'name' => lang($k),
                'value' => $data['extra_info'][$k] ?? 0,
                'type' => in_array($v, [152, 153, 135, 108, 119]) ? [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM] : $type
            ];
        }

        return $configitems_func;
    }

    public function getNameAttr($value)
    {
        return isset(self::NAME_MAP[$value]) ? lang(self::NAME_MAP[$value]) : $value;
    }

}
