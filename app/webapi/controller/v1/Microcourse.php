<?php

declare(strict_types=1);

namespace app\webapi\controller\v1;

use app\webapi\validate\MicroCourse as MicroCourseValidate;
use app\webapi\controller\Base;
use app\webapi\model\MicroCourse as MicroCourseModel;
use think\helper\Arr;
use think\facade\Db;


class Microcourse extends Base
{

    /**
     * 微课(包)列表
     *
     */
    public function index()
    {
        $rule = [
            'package_id' => ['integer'],
            'name' => ['chsDash'],
        ];

        $message = [
            'package_id.integer' => 'mic_package_id_type_error',
        ];

        $this->validate($this->param, $rule, $message);

        $data = $this->searchList(MicroCourseModel::class)->toArray();
        $data = (new MicroCourseModel)->countPackageSize($data);
        return $this->success($data);
    }

    /**
     * 创建微课(包)
     */
    public function save($type = 1)
    {
        $result = [];
        $this->param['parent_id'] = $this->param['package_id'] ?? 0;

        $this->validate($this->param, MicroCourseValidate::class . ($type != MicroCourseModel::COURSE_TYPE ? '.package' : ''));
        $model = MicroCourseModel::create(Arr::only($this->param, ['name', 'pic', 'intro', 'type', 'parent_id', 'user_id']));

        if ($type == MicroCourseModel::COURSE_TYPE) {
            $createParams = [
                'roomname' => $model['name'],
                'custom_id' => $model['custom_id'],
                'room_template_id' => Db::name('room_template')->where('type', 0)->where('company_id', 0)->value('id'),
            ];
            //预约房间
            $model->createRoom($createParams);
            //得到进入教室地址
            $result['room_live'] = $model->getMicEnter($model['custom_id']);
            $result['id'] = $model['id'];
        }

        return $this->success($result);
    }
}
