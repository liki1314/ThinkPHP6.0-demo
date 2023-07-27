<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-01-07
 * Time: 10:46
 */

namespace app\clientapi\validate;


use think\Validate;

class VideoLog extends Validate
{

    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'play_duration' => ['integer'],     //播放时长，单位为秒
        'stay_duration' => ['integer'],     //缓存时长，单位为秒
        'current_times' => ['integer'],              //播放时间，单位为秒
        'duration' => ['integer'],                   //视频总时长，单位为秒
        'flow_size' => ['integer'],                  //流量大小，单位为字节
        'session_id' => ["max:200"],                 //用户自定义参数，如学员ID等
        'ip_address' => ["max:15"],                 //ip地址
        'country' => ["max:50"],                    //国家
        'province' => ["max:50"],                   //省
        'city' => ["max:50"],                       //城市
        'isp' => ["max:50"],                        //isp运营商
        'referer' => ["max:200"],                    //播放视频页面地址
        'user_agent' => ["max:300"],                 //用户设备
        'operating_system' => ["max:50"],           //操作系统
        'browser' => ["max:50"],                    //浏览器
        'is_mobile' => ["integer"],                  //是否为移动到
        'view_source' => ["max:20"]                 //用户观看渠道，取值有：vod_ios_sdk、vod_android_sdk、vod_flash、vod_pc_html5、vod_wechat_mini_program、vod_mobile_html5
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [

    ];
}
