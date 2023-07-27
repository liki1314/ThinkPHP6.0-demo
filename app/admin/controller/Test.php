<?php
declare (strict_types = 1);

namespace app\admin\controller;


use think\Request;
use think\facade\Console;
class Test extends Base
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        event('Notice',[
            'template'=>'homework.remark',
            'origin'=>['homework_id'=>25],
            'front_user_id'=>[3756,3755],
        ]);
    }

    /**
     * 保存新建的资源
     *
     * @param  \think\Request  $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        //
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }

    /**
     * 同步师生考勤
     */
    public function user()
    {
        if (!request()->user || !env('app_debug')) {
            exit('无权操作');
        }
        $day = \request()->get('day') ?: '';

        if (!$day || !strtotime($day)) {
            echo '时间有误';
            return;
        }
        Console::call('syncuser', [$day]);

        echo $day . '师生考勤操作成功';

    }

    /**
     * 同步课节考勤
     */
    public function lesson()
    {
        if (!request()->user || !env('app_debug')) {
            exit('无权操作');
        }

        $day = \request()->get('day') ?: '';
        if (!$day || !strtotime($day)) {
            echo '时间有误';
            return;
        }
        Console::call('synclesson', [$day]);

        echo $day . '课节考勤操作成功';

    }
}
