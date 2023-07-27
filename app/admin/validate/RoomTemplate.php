<?php

declare(strict_types=1);

namespace app\admin\validate;

use think\Validate;
use app\admin\model\RoomTemplate as RoomTemplateModel;
class RoomTemplate extends Validate
{
    public function field()
    {
        $this->field = [
            'layout_id' => lang('layout'),
            'theme_id' => lang('theme'),
        ];
    }
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name' => ['require', 'max' => 20,],
        'type' => ['require', 'integer', 'in:0,3,4'],
        'layout_id' => ['require', 'integer'],
        'theme_id' => ['require', 'integer'],
        // 'backgroup_type' => ['require', 'integer', 'in:0,1,2'],
        'logo' => [
            'file',
            'fileSize'=>314572800,
            'fileExt'=>'jpg,gif,jpeg,png,bmp',
        ],
        // 'big_white_board' => ['require', 'integer', 'in:0,1,2'],
        'video_ratio' => ['require'],
        'answering_machine' => ['require', 'integer', 'in:0,1'],
        'turntable' => ['require', 'integer', 'in:0,1'],
        'timer' => ['require', 'integer', 'in:0,1'],
        'first_answering_machine' => ['require', 'integer', 'in:0,1'],
        'triazolam' => ['require', 'integer', 'in:0,1'],
        'auto_open_video' => ['require', 'integer', 'in:0,1'],
        'student_close_a' => ['require', 'integer', 'in:0,1'],
        'student_close_v' => ['require', 'integer', 'in:0,1'],
        'assistantopenav' => ['require', 'integer', 'in:0,1'],
        'hidden_kicking' => ['require', 'integer', 'in:0,1'],
        'is_video' => ['require', 'integer', 'in:0,1'],
        'av_guide' => ['require', 'integer', 'in:0,1'],
        'device_check_continue' => ['require', 'integer', 'in:0,1'],
        'cut_picture' => ['require', 'integer', 'in:0,1'],
        'only_teacher_and_self' => ['require', 'integer', 'in:0,1'],
        'sign_in' => ['requireCallback:checkSign', 'integer', 'in:0,1'],
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'name.require' => 'name_empty',
        'type.require' => 'type_empty',
        'backgroup_type.require' => 'backgroup_type_empty',
        'logo.require' => 'logo_empty',
        'big_white_board.require' => 'big_white_board_empty',
        'video_ratio.require' => 'video_ratio_empty',
        'answering_machine.require' => 'answering_machine_empty',
        'turntable.require' => 'turntable_empty',
        'timer.require' => 'timer_empty',
        'first_answering_machine.require' => 'first_answering_machine_empty',
        'triazolam.require' => 'triazolam_empty',
        'auto_open_video.require' => 'auto_open_video_empty',
        'student_close_a.require' => 'student_close_a_empty',
        'student_close_v.require' => 'student_close_v_empty',
        'assistantopenav.require' => 'assistantopenav_empty',
        'hidden_kicking.require' => 'hidden_kicking_empty',
        'is_video.require' => 'is_video_empty',
        'av_guide.require' => 'av_guide_empty',
        'device_check_continue.require' => 'device_check_continue_empty',
        'cut_picture.require' => 'cut_picture_empty',
        'only_teacher_and_self.require' => 'only_teacher_and_self_empty',
        'backgroup.require'=>'backgroup_empty',
        'sign_in.require'=>'sign_in_empty'
    ];

    protected function checkSign($value,$data)
    {
        return $data['type'] == RoomTemplateModel::BIG_LIVE ? true : false;
    }
}
