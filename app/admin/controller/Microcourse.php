<?php

declare(strict_types=1);

namespace app\admin\controller;


use app\admin\validate\MicroCourse as MicroCourseValidate;
use app\admin\model\MicroCourse as MicroCourseModel;
use app\common\http\WebApi;
use think\exception\ValidateException;
use think\helper\Arr;
use app\admin\model\RoomTemplate;
use think\facade\Db;

class Microcourse extends Base
{
    /**
     * 微课列表
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

        Db::transaction(function () use ($type) {
            $model = MicroCourseModel::create(Arr::only($this->param, ['name', 'pic', 'intro', 'type', 'parent_id']));

            if ($type == MicroCourseModel::COURSE_TYPE) {
                $createParams = [
                    'roomname' => $model['name'],
                    'custom_id' => $model['custom_id'],
                    'room_template_id' => RoomTemplate::where('type', 0)->where('company_id', 0)->value('id'),
                ];
                //预约房间
                $model->createRoom($createParams);
                //得到进入教室地址
                $result['room_live'] = $model->getMicEnter($model['custom_id']);
                $result['id'] = $model['id'];
            }
        });

        return $this->success($result);
    }

    /**
     * 详情
     * @param $id
     */
    public function read($id, $type)
    {
        $model = MicroCourseModel::where('type', $type)
            ->findOrfail($id)
            ->visible(['id', 'name', 'intro']);
        return $this->success($model);
    }


    /**
     * 更新微课(包)
     * @param $thirdroomid
     */
    public function update($thirdroomid, $type = 1)
    {
        $this->validate($this->param, MicroCourseValidate::class . '.package');
        if ($type == 2) {
            $model = MicroCourseModel::where('type', $type)->findOrfail($thirdroomid);
        } else {
            if (is_numeric($thirdroomid)) {
                $model = MicroCourseModel::where('type', $type)->findOrfail($thirdroomid);
            } else {
                $temp = explode('_', $thirdroomid);
                $thirdroomid = $temp[1] ?? $temp[0];
                $model = MicroCourseModel::where('type', $type)->where('custom_id', $thirdroomid)->findOrfail();
            }
        }

        $model->save(Arr::only($this->param, ['name', 'pic', 'intro']));
        return $this->success($model);
    }

    /**
     * 删除微课(包)
     */
    public function delete($type = 1)
    {
        $rule = [
            'id' => [
                'require',
                'array',
                'each' => 'integer'
            ]
        ];
        $message = [
            'id.require' => 'mic_id_empty',
            'id.array' => 'mic_id_error',
        ];

        $this->validate($this->param, $rule, $message);

        $custom_id_arr = [];
        if ($type == MicroCourseModel::PACKAGE_TYPE) {
            $info = MicroCourseModel::where('type', MicroCourseModel::COURSE_TYPE)
                ->whereIn('parent_id', $this->param['id'])
                ->find();
            if ($info) {
                throw new ValidateException(lang('mic_package_exists_room'));
            }
        } else {
            $custom_id_arr = MicroCourseModel::where('type', $type)
                ->whereIn('id', $this->param['id'])
                ->column('custom_id');
        }

        Db::transaction(function () use ($custom_id_arr) {
            MicroCourseModel::useSoftDelete('delete_time', time())->whereIn('id', $this->param['id'])->delete();
            if (!empty($custom_id_arr)) {
                WebApi::httpPost('WebAPI/muchroomdelete', ['thirdroomid_list' => $custom_id_arr]);
            }
        });


        return $this->success();
    }

    /**
     * 包列表
     */
    public function packageList()
    {
        $data = MicroCourseModel::withSearch(['onlypackage'], $this->param)->select();
        return $this->success($data);
    }

    /**
     * 移动 or 复制 包 到包下
     * @param $type
     */
    public function modify($type)
    {
        $rule = [
            'id' => [
                'require',
                'integer',
                function ($id) {
                    return MicroCourseModel::find($id) ? true : lang('mic_room_not_exists');
                }
            ],

            'source_package_id' => [
                'require',
                'integer',
                function ($id) {
                    if (!$id) return true;
                    return MicroCourseModel::find($id) ? true : lang('mic_source_not_exists');
                }
            ],
            'target_package_id' => [
                'require',
                'integer',
                function ($id) {
                    if (!$id) return true;
                    return MicroCourseModel::find($id) ? true : lang('mic_tatget_not_exists');
                }
            ],
        ];

        $message = [
            'id.require' => 'mic_id_empty',
            'id.integer' => 'mic_package_id_type_error',
        ];

        $this->validate($this->param, $rule, $message);

        (new MicroCourseModel)->modify($this->param, $type);

        return $this->success();
    }
}
