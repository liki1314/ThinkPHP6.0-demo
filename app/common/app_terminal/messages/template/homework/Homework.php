<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-06-29
 * Time: 10:09
 */

namespace app\common\app_terminal\messages\template\homework;


use app\admin\model\FrontUser;
use app\common\app_terminal\messages\template\Driver;
use app\admin\model\Homework as HomeworkModel;

abstract class Homework extends Driver
{

    /**
     * 原始消息数据
     *
     * @var array
     */
    protected $origin;


    /**
     * 创造发送消息需要的数据
     *
     * @param mixed $origin
     * @param int|array $front_user_id 前台账号id
     * @return $this
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function makeSendData($origin, $front_user_id)
    {
        $this->setOrigin($origin);
        $this->users = FrontUser::whereIn('id', $front_user_id)->field(['id', 'user_account_id', 'nickname', 'userroleid'])->select();
        return $this;
    }

    /**
     * @param $origin
     */
    protected function setOrigin($origin)
    {
        $this->origin = $origin;
        $this->origin['title'] = HomeworkModel::where('id', $origin['homework_id'])->value('title');
        $this->origin['app_name'] = $this->name;
        $this->origin['homework_id'] = $origin['homework_id'];
    }
}
