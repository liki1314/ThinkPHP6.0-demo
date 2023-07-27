<?php

declare(strict_types = 1);

namespace app\webapi\validate;
use think\Validate;
use app\webapi\model\MicroCourse as MicroCourseModel;

class MicroCourse extends Validate
{

    public function field()
    {
        $this->field = [
            'name' => lang('mic_name'),
            'pic' => lang('mic_pic'),
            'intro' => lang('mic_intro'),
            'record' =>lang('mic_record'),
            'parent_id'=>lang('mic_package_id'),
            'times'=>lang('mic_times'),
            'user_id'=>lang('mic_userid'),
        ];
    }


    protected $rule = [
        'name' => ['require', 'max' => 32],
        'pic'  =>['image','fileSize:2097152','fileExt:jpeg,jpg,png'],
        'intro'=>['max' =>120],
        'record'=>['file','fileExt:mp4,mov,avi|fileSize:314572800'],
        'parent_id' => ['integer','checkExist'],
        'times' =>['integer'],
        'user_id'=>['require','max'=>50],
    ];


    protected $message = [
        'pic.require'=>'mic_pic_empty',
        'pic.width'=>'mic_width',
        'pic.height'=>'mic_height',
        'record.require'=>'mic_record_empty',
        'record.fileExt'=>'mic_record_type',
        'parent_id.require'=>'min_package_id_empty',
        'name.require'=>'mic_name_empty',
        'user_id.require'=>'mic_userid_empty',
        'user_id.max'=>'mic_userid_error',
    ];


    protected $scene = [
        'update'=>['name','pic','intro','record','times'],
    ];


    public function scenePackage()
    {
        return $this->only(['name','pic','intro'])
            ->append('name', ['require', 'max' => 32])
            ->append('pic', ['image','fileSize:2097152','fileExt:jpeg,jpg,png'])
            ->append('intro',['max' =>120]);
    }


    protected function checkExist($value, $rule, $data=[])
    {
        if(!$value) return true;

        $MicroCourseModel = new MicroCourseModel;

        return $MicroCourseModel
            ->where('id',$value)
            ->where('type',MicroCourseModel::PACKAGE_TYPE)
            ->find() ? true : lang('mic_package_id');
    }

}