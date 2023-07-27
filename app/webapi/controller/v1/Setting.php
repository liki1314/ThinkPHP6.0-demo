<?php
declare (strict_types = 1);
/**
 * Created by PhpStorm.
 * User: hongwei
 * Date: 2021/1/11
 * Time: 17:57
 */

namespace app\webapi\controller\v1;
use app\webapi\controller\Base;
use app\webapi\validate\Setting as SettingValidate;
use app\webapi\model\Setting as SettingModel;
use think\helper\Arr;

class Setting extends Base
{

    /**
     * 设置用户配置
     *
     */
    public function playsafe()
    {
        $this->validate($this->param, SettingValidate::class);

        $this->param['company_id'] = request()->company['companyid'];

        SettingModel::duplicate(['encrypt' => $this->param['encrypt'], 'hlslevel' => $this->param['hlslevel']])->insert(Arr::only($this->param, ['company_id', 'encrypt', 'hlslevel', 'userid']));

        return $this->success();
    }


    /**
     * 读取配置
     * @param $userid
     * @return \think\response\Json
     */
    public function read($userid)
    {
        $model = SettingModel::where('userid',$userid)->findOrFail()->visible(['hlslevel','encrypt']);

        return $this->success($model);
    }

}