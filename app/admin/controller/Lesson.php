<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\MicroCourse;
use app\admin\model\Course;
use app\admin\model\FrontUser;
use app\admin\model\Room;
use app\admin\model\RoomAccessRecord;
use app\admin\model\RoomUser;
use app\admin\model\Freetime;
use app\admin\validate\Room as RoomValidate;
use app\common\facade\Live;
use app\common\service\room\RoomStudentsMsg;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\helper\Arr;
use app\common\http\WebApi;
use think\facade\{Cache, Lang};
use app\common\http\singleton\TemplateSingleton;
use app\admin\job\Export;
use app\admin\model\AuthGroup;
use app\admin\model\Company;

class Lesson extends Base
{
    public function index($type = 0)
    {
        if ($type == 1) {
            $this->param['day'] = date('Y-m-d');
            $this->param['no_page'] = true;
        } elseif ($type == 2) {
            $this->param['no_page'] = true;
        }

        $this->param['sort'] = isset($this->param['sort']) && $this->param['sort'] == 'start_lesson_time desc' ? 'desc' : 'asc';
        $data = $this->searchList(Room::class);

        if (!$data->isEmpty()) {
            if ($type == 1) {
                $serialList = $data->column('room_id');
                $studentsList = Db::table('saas_room_user')
                    ->field('count(a.front_user_id) as students,a.room_id')
                    ->alias('a')
                    ->join(['saas_front_user' => 'b'], 'a.front_user_id=b.id')
                    ->whereIn('a.room_id', $serialList)
                    ->where('b.userroleid', FrontUser::STUDENT_TYPE)
                    ->group('a.room_id')
                    ->select()
                    ->toArray();

                $enterStudent = RoomAccessRecord::whereIn('room_id', $serialList)
                    ->alias('a')
                    ->join(['saas_front_user' => 'b'], 'a.company_user_id=b.id')
                    ->field('count(DISTINCT a.company_user_id) enter_students,a.room_id')
                    ->where('a.type', 1)
                    ->where('a.entertime', '>', 0)
                    ->where('a.outtime', 0)
                    ->group('a.room_id')
                    ->select()
                    ->toArray();

                $data->withAttr('students', function ($value, $list) use ($studentsList) {
                    $students = 1; //默认有一个教师
                    foreach ($studentsList as $v1) {
                        if ($list['room_id'] == $v1['room_id']) {
                            $students = $v1['students'] + 1;  //加上教师的人数
                            break;
                        }
                    }
                    return $students;
                });

                $data->withAttr('enter_students', function ($value, $list) use ($enterStudent) {
                    $enter_students = 0;
                    foreach ($enterStudent as $v1) {
                        if ($list['room_id'] == $v1['room_id']) {
                            $enter_students = $v1['enter_students'];
                            break;
                        }
                    }
                    return $enter_students;
                });

                //优化逻辑
                $data = $data->toArray();

                foreach ($data as $key => $item) {
                    //排课之前下课
                    if ($item['actual_end_time'] && $item['starttime'] > $item['actual_end_time']) {
                        unset($data[$key]);
                        continue;
                    }

                    //排课之后下课
                    if ($item['endtime'] < $item['actual_end_time']) {
                        unset($data[$key]);
                        continue;
                    }
                }
            }

            if ($type == 0) {
                $resource = WebApi::httpPost('WebAPI/getRoomFileCount?error=0', ['serials' => $data->column('live_serial')]);
                $data->withAttr('resource_num', function ($value, $list) use ($resource) {
                    $resource_num = 0;
                    $temp = $resource['data'] ?? [];
                    foreach ($temp as $v) {
                        if ($v['serial'] == $list['live_serial']) {
                            $resource_num = $v['count'];
                            break;
                        }
                    }

                    return $resource_num;
                });

                //分辨率
                $data->withAttr('video_ratio_text', function ($value, $list) use ($resource) {
                    $video = '';
                    $temp = $resource['data'] ?? [];
                    foreach ($temp as $v) {
                        if ($v['serial'] == $list['live_serial']) {
                            $video = $v['videotype'];
                            break;
                        }
                    }
                    return $video;
                });
            }
        }
        return $this->success($data);
    }

    public function save()
    {
        $this->validate($this->param, RoomValidate::class . '.single');

        $models = Db::transaction(function () {
            $models = Course::findModelExceptTeacher($this->param['course_id'], $this->param['teacher_id'])
                ->createRoom(
                    Arr::only(
                        $this->param,
                        ['roomname', 'teacher_id', 'helper', 'start_time', 'start_date', 'end_time', 'resources']
                    )
                );

            Live::createRoom($models->column('id'), $this->request->user['company_id']);

            return $models;
        });

        return $this->success($models->toArray());
    }


    public function read($id)
    {
        $model = Room::withSearch(['detail'])
            ->findOrFail($id)
            ->withAttr('files', function ($value, $data) {
                $files = WebApi::httpPost('WebAPI/getroomfile?error=0', ['serial' => $data['live_serial']]);

                return array_map(
                    function ($value) {
                        return ['filename' => $value['filename'], 'downloadpath' => $value['downloadpath'], 'fileid' => $value['fileid']];
                    },
                    $files['roomfile'] ?? []
                );
            });

        return $this->success($model);
    }

    public function update($course_id, $id)
    {
        $this->validate($this->param, RoomValidate::class . '.single');

        $model = Db::transaction(function () use ($id, $course_id) {
            $model = Room::findOrFail($id)->append(['state']);
            if ($model['state'] == Course::ING_STATE) {
                $model['resources'] = $this->param['resources'];
            } else {
                $allowFiled = ['roomname', 'teacher_id', 'helper', 'start_time', 'start_date', 'end_time', 'resources'];
                $result = $model->save(
                    Arr::only(
                        $this->param,
                        $allowFiled
                    )
                );

                if ($result === false) {
                    throw new ValidateException(lang('老师未分配空闲时间'));
                }

                $model->syncHelpers($this->param['helper'], false);
                Course::updateStateTime($course_id);
                Live::updateRoom([$model], $this->request->user['company_id']);
            }

            return $model;
        });
        return $this->success($model->toArray());
    }

    public function batchUpdate()
    {
        $this->validate(
            array_merge($this->param, $this->request->route(['attr'])),
            [
                'lesson_id' => ['require', 'array', 'each' => 'integer'],
                'teacher_id' => ['requireIf:behavior,updateTeacher', 'integer', 'exist:' . FrontUser::class . ',userroleid=' . FrontUser::TEACHER_TYPE],
                'video_ratio' => function ($value, $data) {
                    return $data['behavior'] != 'updateRatio' || array_key_exists($value, TemplateSingleton::getInstance()->getVideo());
                }
            ],
            [
                'teacher_id' => lang('Teacher parameter illegal'),
                'video_ratio' => lang('Resolution parameter invalid'),
            ]
        );

        $models = Room::where('course_id', $this->param['course_id'])->whereIn('id', $this->param['lesson_id'])->select()->whereIn('schedule', [Course::UNSTART_STATE, Room::DUE_STATE]);

        if ($models->count() !== count($this->param['lesson_id'])) {
            throw new ValidateException(lang('The selected class contains a class that cannot be modified'));
        }

        call_user_func_array([Room::class, $this->request->route('behavior')], [$models, $this->param]);

        return $this->success();
    }

    public function batchUpdateTime()
    {
        $this->validate($this->param, [
            'lessons' => ['require', 'array', 'each' => [
                'id' => ['require', 'integer'],
                'start_date' => ['require', 'date', 'after' => 'today'],
                'start_time' => ['require', 'dateFormat' => 'H:i'],
                'end_time' => ['require', 'dateFormat' => 'H:i'],
            ]]
        ]);
        foreach ($this->param['lessons'] as $value) {
            $data[$value['id']] = $value;
        }

        $models = Room::where('course_id', $this->param['course_id'])->whereIn('id', array_keys($data))->select()->whereIn('schedule', [Course::UNSTART_STATE, Room::DUE_STATE]);
        if ($models->count() !== count($this->param['lessons'])) {
            throw new ValidateException(lang('The selected class contains a class that cannot be modified'));
        }

        Db::transaction(function () use ($models, $data) {
            $tmp = [];
            $models->map(function ($model) use ($data, &$tmp) {
                if ($model->save(Arr::only($data[$model->getKey()], ['start_date', 'start_time', 'end_time'])) !== false) {
                    $tmp[] = $model->toArray();
                }
            });

            WebApi::httpJson(
                'WebAPI/batchRoomModify',
                [
                    'key' => Company::getDetailById($this->request->user['company_id'])['authkey'], //此接口只能通过post传参方式传递企业authkey
                    'roomParamList' => array_map(function ($model) {
                        return [
                            'starttime' => $model['starttime'],
                            'endtime' => $model['endtime'],
                            'thirdroomid' => $model['custom_id'],
                        ];
                    }, $tmp)
                ]
            );
        });

        return $this->success();
    }

    /**
     * 课节分配学生
     *
     * @param int $course_id 课程id
     * @param int $id 课节id
     * @param array $students 学生id数组
     * @return void
     */
    public function allocate($course_id, $id)
    {
        $this->validate(
            $this->param,
            [
                'students|' . lang('student') => [
                    'array',
                    function ($value) {
                        return FrontUser::whereIn('id', $value)->withSearch(['student'])->count() === count($value);
                    }
                ],
                'lessons' => ['array']
            ]
        );

        $model = Room::findOrFail($id);
        $add_students = $this->param['students'];
        $del_students = [];
        $lessons = $this->param['lessons'] ?? [];
        if (!empty($lessons)) {
            $old_students = RoomUser::where('room_id', $id)->column('front_user_id');
            $del_students = array_diff($old_students, $this->param['students']);
        }
        $model->transaction(function () use ($model, $add_students, $lessons, $del_students) {
            //先删除后新增
            $model->student()->detach();

            if (!empty($del_students)) {
                Db::name('room_user')->whereIn('room_id', $lessons)->whereIn('front_user_id', $del_students)->delete();
            }
            //添加学生
            if (!empty($add_students)) {
                $lessons[] = $model->getKey();
                $data = [];
                foreach ($lessons as $l) {
                    foreach ($add_students as $s) {
                        $data[] = [
                            'room_id' => $l,
                            'front_user_id' => $s,
                        ];
                    }
                }
                Db::name('room_user')->extra('IGNORE')->insertAll($data);
            }
        });


        return $this->success($model);
    }

    /**
     * 删除课节
     * @param $course_id 课程id
     * @param $id 课节id
     * @return \think\response\Json
     */
    public function delete($course_id, $id)
    {
        $model = Room::where('course_id', $course_id)->findOrFail($id);
        $model->transaction(function () use ($model, $course_id) {
            $model->delete();

            Course::updateStateTime($course_id);
        });


        return $this->success($id);
    }

    /**
     * 批量删除
     * @param int $course_id 课程id
     * @param array $ids 课节id数组
     * @return \think\response\Json
     */
    public function deleteSelect($course_id, $ids)
    {
        $this->validate(
            $this->param,
            [
                'course_id' => ['require', 'integer'],
                'ids' => ['require', 'array', 'each' => 'integer'],
            ]
        );

        $deletes = Room::whereIn('id', $ids)
            ->where('course_id', $course_id)
            // ->where('starttime', '>', time())
            ->select()
            ->append(['schedule'])
            ->whereIn('schedule', [Course::UNSTART_STATE, Room::DUE_STATE])
            ->column('id');

        Db::transaction(function () use ($deletes, $course_id) {
            Room::whereIn('id', $deletes)->useSoftDelete('delete_time', time())->delete();
            Course::updateStateTime($course_id);

            // 取消空闲时间已排课状态
            Freetime::whereIn('room_id', $deletes)
                ->update([
                    'status' => Freetime::FREE_STATUS,
                    'room_id' => 0
                ]);

            array_walk($deletes, function ($value) {
                Cache::delete(sprintf('notice:room_pk:%s', $value));
            });
        });


        return $this->success($deletes);
    }

    /**
     * 监课报告详情
     * @param $course_id
     * @param $id
     * @param $userid
     */
    public function monitorInfo($course_id, $id, $userid)
    {
    }

    /**
     * 批量新增课节
     * @return \think\response\Json
     */
    public function batch()
    {
        $this->validate($this->param, RoomValidate::class . '.batch');

        if (empty($this->param['time'])) {
            $time = array_combine(array_column($this->param['week']['time'], 'week_id'), $this->param['week']['time']);
            $this->param['time'] = array_map(
                function ($item) use ($time) {
                    return [
                        'start_date' => $item['start_date'],
                        'start_time' => $time[$item['week_id']]['start_time'],
                        'end_time' => $time[$item['week_id']]['end_time']
                    ];
                },
                Room::getTimeByWeek(
                    $this->param['week']['start_date'],
                    $this->param['week']['num'] < count($this->param['week']['time']) ? count($this->param['week']['time']) : $this->param['week']['num'],
                    array_keys($time)
                )
            );
        }

        $models = Db::transaction(function () {
            $data = [];
            foreach ($this->param['time'] as $key => $value) {
                // $value['roomname'] = $this->param['roomname'] . ($key + 1);
                $data[] = Arr::only(
                    array_merge($this->param, $value),
                    ['roomname', 'teacher_id', 'helper', 'start_time', 'start_date', 'end_time']
                );
            }

            $models = Course::findModelExceptTeacher($this->param['course_id'], $this->param['teacher_id'])->createRoom($data);
            if (!empty($this->param['resources'])) {
                $resources = array_combine(array_column($this->param['resources'], 'number'), $this->param['resources']);
                $models->each(function ($item, $key) use ($resources) {
                    $item['resources'] = array_map(function ($item) {
                        return ['id' => $item];
                    }, $resources[$key + ($this->param['start_num'] ?? 1 ?: 1)]['ids'] ?? []);
                    return $item;
                });
            }

            Live::createRoom($models->column('id'), $this->request->user['company_id']);

            return $models;
        });

        return $this->success($models->toArray());
    }

    /**
     * 课前准备
     * @param $course_id
     * @param $id
     * @return \think\response\Json
     */
    public function beforePrepare($course_id, $id)
    {
        $model = Room::findOrFail($id);

        $result = WebApi::httpPost(
            'WebAPI/getroom',
            [
                'serial' => $model['live_serial']
            ]
        );
        $results = WebApi::httpGet([
            sprintf('WebAPI/getroom?error=0&serial=%s&username=%s&usertype=%s&pid=%s', $model['live_serial'], urlencode($this->request->user['username']), AuthGroup::HELPER_ROLE, $this->request->user['userid']),
            sprintf('WebAPI/getroom?error=0&serial=%s&username=%s&usertype=%s&pid=%s', $model['live_serial'], urlencode($this->request->user['username']), AuthGroup::COURSE_ROLE, $this->request->user['userid']),
        ]);
        $result = $results[0];

        $files = WebApi::httpPost(
            'WebAPI/getroomfile?error=0',
            [
                'serial' => $model['live_serial']
            ]
        );

        return $this->success([
            'live_addr' => (function () use ($result) {
                foreach ($result['enterurl'] as $value) {
                    if ($value['usertype'] == 0) {
                        return $value['url'];
                    }
                }
            })(),
            'student_addr' => (function () use ($result) {
                foreach ($result['enterurl'] as $value) {
                    if ($value['usertype'] == 2) {
                        return $value['url'];
                    }
                }
            })(),
            'room_pwd' => [
                'teacher' => $result['chairmanpwd'],
                'helper' => $result['assistantpwd'],
                'course' => $result['patrolpwd'],
                'student' => $result['confuserpwd']
            ],
            'room_resource' => array_map(
                function ($item) {
                    return ['filename' => $item['filename'], 'downloadpath' => $item['downloadpath']];
                },
                $files['roomfile'] ?? []
            ),
            'helper_enter_url' => $this->request->user['super_user'] || in_array(AuthGroup::HELPER_ROLE, $this->request->user['roles'] ?? []) ? $results[0]['entryurl'] ?? null : null,
            'tour_enter_url' => $this->request->user['super_user'] || in_array(AuthGroup::COURSE_ROLE, $this->request->user['roles'] ?? []) ? $results[1]['entryurl'] ?? null : null,
        ]);
    }

    public function url($id)
    {
        $model = Room::findOrFail($id);
        $results = WebApi::httpGet([
            sprintf('WebAPI/getroom?error=0&serial=%s&username=%s&usertype=%s&pid=%s', $model['live_serial'], urlencode($this->request->user['username']), AuthGroup::HELPER_ROLE, $this->request->user['userid']),
            sprintf('WebAPI/getroom?error=0&serial=%s&username=%s&usertype=%s&pid=%s', $model['live_serial'], urlencode($this->request->user['username']), AuthGroup::COURSE_ROLE, $this->request->user['userid']),
        ]);

        return $this->success([
            'helper_enter_url' => $this->request->user['super_user'] || in_array(AuthGroup::HELPER_ROLE, $this->request->user['roles'] ?? []) ? $results[0]['entryurl'] ?? null : null,
            'tour_enter_url' => $this->request->user['super_user'] || in_array(AuthGroup::COURSE_ROLE, $this->request->user['roles'] ?? []) ? $results[1]['entryurl'] ?? null : null,
        ]);
    }

    /**
     * 调课
     * @param $course_id 课程id
     * @param $id 课节id
     * @return \think\response\Json
     */
    public function change($course_id, $id)
    {
        $this->validate(
            $this->param,
            [
                'id' => ['exist' => Room::class],
                'target_room_id' => ['require', 'integer', 'different' => 'id', 'exist' => Room::class],
                'students' => ['require', 'array', 'each' => 'integer']
            ],
            [
                'from_ids' => 'params_error',
                'to_dir_id' => 'params_error',
            ]
        );

        RoomUser::extra('IGNORE')
            ->where('room_id', $id)
            ->whereIn('front_user_id', $this->param['students'])
            ->update(['room_id' => $this->param['target_room_id']]);

        return $this->success([['id' => $id], ['id' => $this->param['target_room_id']]]);
    }

    /**
     * 教室链接重定向
     * @param $room_id
     * @param $username
     * @param $type
     * @return \think\response\Redirect
     */
    public function enterRoom($room_id, $type = 0, $username = '')
    {
        $this->param['roomtype'] = $this->param['roomtype'] ?? 0;
        if ($this->param['roomtype'] == MicroCourse::ROOMTYPE_MICRO) {
            $custom_id = MicroCourse::where('id', $room_id)->where('type', MicroCourse::COURSE_TYPE)->value('custom_id');
        } else {
            $custom_id = Room::where('live_serial', '<>', '')->findOrFail($room_id)['custom_id'];
        }

        $params = [
            'thirdroomid' => $custom_id,
            'username' => $username,
            'pid' => $this->request->user['userid'] ?: $this->request->user['user_account_id'],
            'usertype' => $type
        ];

        $apiRes = WebApi::httpPost('/WebAPI/getroom', $params);
        $url = $apiRes['entryurl'] ?? '';
        return redirect($url);
    }

    /**
     * @return \think\response\Json
     */
    public function issue()
    {
        $userId = $this->request->user['super_user'] == 1 ? false : $this->request->user['id'];
        return $this->success(RoomStudentsMsg::getCompanyMsg($this->request->user['user_company_id'], $userId));
    }

    /**
     * @return \think\response\Json
     */
    public function delIssue()
    {
        $this->validate(
            $this->param,
            [
                "id" => ['require'],
                "name" => ['require'],
                "mobile" => ['require'],
                "room_name" => ['require'],
                "start_time" => ['require'],
                "end_time" => ['require'],
                "on_time" => ['require'],
                "msg_time" => ['require'],
                "msg_type" => ['require'],
                "students_time" => ['require'],
                "auxiliary" => ['array'],
            ],
            [
                "id" => 'params_error',
                "name" => 'params_error',
                "mobile" => 'params_error',
                "room_name" => 'params_error',
                "start_time" => 'params_error',
                "end_time" => 'params_error',
                "on_time" => 'params_error',
                "msg_time" => 'params_error',
                "msg_type" => 'params_error',
                "students_time" => 'params_error',
                "auxiliary" => 'params_error'
            ]
        );

        $data['id'] = strval($this->param['id']);
        $data['name'] = strval($this->param['name']);
        $data['mobile'] = strval($this->param['mobile']);
        $data['room_id'] = strval($this->param['room_id']);
        $data['room_name'] = strval($this->param['room_name']);
        $data['start_time'] = strval($this->param['start_time']);
        $data['end_time'] = strval($this->param['end_time']);
        $data['on_time'] = strval($this->param['on_time']);
        $data['msg_time'] = strval($this->param['msg_time']);
        $data['msg_type'] = strval($this->param['msg_type']);
        $data['students_time'] = strval($this->param['students_time']);
        $data['auxiliary'] = $this->param['auxiliary'];

        RoomStudentsMsg::closeRoomStudentsTypeMsg(
            $this->request->user['user_company_id'],
            $data
        );

        return $this->success();
    }


    /**
     * @return \think\response\Json
     */
    public function delIssueAll()
    {
        RoomStudentsMsg::closeAll($this->request->user['user_company_id'], false);
        return $this->success();
    }

    /**
     * 课节课件明细
     * @param $room_id
     * @return \think\response\Json
     */
    public function files($room_id)
    {
        $model = Room::findOrFail($room_id);
        $apiRes = WebApi::httpPost('WebAPI/getroomfile?error=0', ['serial' => $model['live_serial']]);
        $temp = array_filter($apiRes['roomfile'] ?? [], function ($value) {
            return isset($value['fileid']);
        });

        $data = [];
        foreach ($temp as $v) {
            $data[] = [
                'filename' => $v['filename'],
                'preview_url' => $v['path_arr'][0] ?? '',
                'status' => $v['status'],
                'download_url' => $v['download_url'],
                'filetype' => $v['filetype']
            ];
        }

        return $this->success($data);
    }

    public function delay($course_id)
    {
        $this->validate(
            $this->param,
            [
                'lessons|' . lang('顺延课节') => [
                    'require',
                    'array',
                    'each' => [
                        'id' => 'require',
                        'start_date' => ['require', 'dateFormat:Y-m-d', 'after:today'],
                        'start_time' => ['require', 'dateFormat' => 'H:i'],
                        'end_time' => ['require', 'dateFormat' => 'H:i', '>:start_time'],
                    ],
                    function ($value) use ($course_id) {
                        return Room::where('course_id', $course_id)
                            ->whereIn('id', array_column($value, 'id'))
                            ->where('starttime', '>', time())
                            ->count() == count($value);
                    }
                ]
            ]
        );

        $lessons = [];
        foreach ($this->param['lessons'] as $lesson) {
            $lessons[$lesson['id']] = Arr::only($lesson, ['start_date', 'start_time', 'end_time']);
        }

        Db::transaction(function () use ($lessons, $course_id) {
            $models = Room::whereIn('id', array_keys($lessons))
                ->select()
                ->order('starttime', 'desc')
                ->map(function ($model) use ($lessons) {
                    $model->save($lessons[$model->getKey()]);

                    return $model;
                });

            Course::updateStateTime($course_id);
            Live::updateRoom($models->toArray(), $this->request->user['company_id']);
        });

        return $this->success();
    }

    public function getDelayTime($course_id, $type, $days = 0, $lessons = 0)
    {
        /** @var \think\model\Collection */
        $rooms = Room::where('course_id', $course_id)
            ->where('starttime', '>', time())
            ->when($lessons, function ($query) use ($lessons) {
                $query->whereIn('id', $lessons);
            })
            ->field("id,roomname name,starttime,endtime,teacher_id,FROM_UNIXTIME(starttime,'%w') week,actual_start_time,actual_end_time")
            ->order('starttime', 'asc')
            ->selectOrFail()
            ->where('state', '=', Course::UNSTART_STATE);

        $weeks = array_unique($rooms->column('week'));
        sort($weeks);
        $num = count($weeks);
        $rooms->each(function ($item, $key) use ($type, $days, $weeks, $num, $rooms) {
            if ($type == 1) { //依次顺延
                $days = $weeks[(array_search($item['week'], $weeks) + 1) % $num] - $item['week'];
                if ($days <= 0) {
                    $days += 7;
                }
                $item['starttime'] = strtotime("+$days days", $item['starttime']);
                $item['endtime'] = strtotime("+$days days", $item['endtime']);
            } else { //按天顺延
                $item['starttime'] += $days * 24 * 3600;
                $item['endtime'] += $days * 24 * 3600;
            }

            //判断顺延之后的时间段该老师是否已排课
            if ($type == 1 || $days > 0) {
                $item['status'] = !empty($this->request->user['company_model']['notice_config']['repeat_lesson']['switch']) && Room::where('teacher_id', $item['teacher_id'])
                    ->where('starttime', '<', $item['endtime'])
                    ->where('endtime', '>', $item['starttime'])
                    ->whereNotIn('id', $rooms->column('id'))
                    ->value('id') ? 1 : 0;

                // 空闲排课开
                if (!empty($this->request->user['company_model']['notice_config']['scheduling']['freetime_switch'])) {
                    $item['status'] = Freetime::alias('a')
                        ->join('front_user b', 'a.create_by=b.user_account_id')
                        ->where('start_time', '<=', $item['starttime'])
                        ->where('end_time', '>=', $item['endtime'])
                        ->where('b.id', $item['teacher_id'])
                        ->where(function ($query) use ($rooms) {
                            $query->where('a.status', Freetime::FREE_STATUS)->whereOr('room_id', 'in', $rooms->column('id'));
                        })
                        ->value('a.id') ? $item['status'] : 1;
                }
            }

            $item['start_time'] = date('H:i', $item['starttime']);
            $item['end_time'] = date('H:i', $item['endtime']);

            return $item;
        })
            ->append(['start_date'])
            ->hidden(['week', 'starttime', 'endtime']);

        return $this->success($rooms->values());
    }

    public function delayStauts()
    {
        $this->validate(
            $this->param,
            [
                'current_lesson' => [
                    'require',
                    'array',
                    'each' => [
                        'id' => 'require',
                        'start_date' => ['require', 'dateFormat:Y-m-d', 'after:today'],
                        'start_time' => ['require', 'dateFormat' => 'H:i'],
                        'end_time' => ['require', 'dateFormat' => 'H:i', '>:start_time'],
                    ]
                ],
                'lessons' => ['require', 'array', 'each' => 'integer'],
            ]
        );

        $model = Room::findOrFail($this->param['current_lesson']['id']);
        $endtime = $this->param['current_lesson']['start_date'] . ' ' . $this->param['current_lesson']['end_time'];
        $starttime = $this->param['current_lesson']['start_date'] . ' ' . $this->param['current_lesson']['start_time'];
        $status = Room::where('teacher_id', $model['teacher_id'])
            ->whereTime('starttime', '<', $endtime)
            ->whereTime('endtime', '>', $starttime)
            ->whereNotIn('id', $this->param['lessons'])
            ->value('id') ? 1 : 0;

        // 空闲排课开
        if (!empty($this->request->user['company_model']['notice_config']['scheduling']['freetime_switch'])) {
            $status = Freetime::alias('a')
                ->join('front_user b', 'a.create_by=b.user_account_id')
                ->where('start_time', strtotime($starttime))
                ->where('end_time', strtotime($endtime))
                ->where('b.id', $model['teacher_id'])
                ->where('a.status', Freetime::FREE_STATUS)
                ->value('a.id') ? $status : 1;
        }
        return $this->success(['status' => $status]);
    }


    public function getCoursewareByRule($lesson_num, $dir_id, $rule_type)
    {
        $files = WebApi::httpPost('WebAPI/fileList', ['catalogId' => $dir_id, 'getAll' => 1]);

        $result = [];
        if (!empty($files['data']['list'])) {
            switch ($rule_type) {
                case '1':
                    $pattern = '/^(0[1-9]|[1-9][0-9])_.+/';
                    break;
                case '2':
                    $pattern = '/^([1-9]|[1-9][0-9])_.+/';
                    break;
                case '3':
                    $pattern = '/.+_(0[1-9]|[1-9][0-9])\..+/';
                    break;
                case '4':
                    $pattern = '/.+_([1-9]|[1-9][0-9])\..+/';
                    break;
                default:
                    # code...
                    break;
            }

            $startNum = $this->param['start_num'] ?? 1 ?: 1;

            foreach ($files['data']['list'] as $file) {
                if ($file['data_type'] == 1) {
                    continue;
                }

                preg_match($pattern, $file['filename'], $matches);

                if (!empty($matches) && ($number = intval($matches[1])) >= $startNum && ($number - $startNum) < $lesson_num) {
                    $result[$number]['ids'][] = $file['fileid'];
                    $result[$number]['number'] = $number;
                }
            }
        }

        return json(['result' => 0, 'data' => array_values($result), 'msg' => $lesson_num - count($result) > 0 ? lang('%d lesson%s have not matched any file.', [$lesson_num - count($result), $lesson_num - count($result) > 1 ? 's' : '']) : '']);
    }

    /**
     * 批量删除课节课件
     * @param $course_id
     */
    public function deleteLessonResource($course_id)
    {
        $serial = [];
        $rule = [
            'lesson_id' => ['require', 'array', function ($value) use ($course_id, &$serial) {
                $data = Room::where('course_id', $course_id)
                    ->whereIn('id', $value)
                    ->select();
                $serial = $data->column('live_serial');
                $needDelete = $data->where('state', Course::ING_STATE);
                return (!$needDelete->isEmpty() || count($data->toArray()) != count($value)) ? lang('lesson_ing') : true;
            }],
        ];

        $message = [
            'lesson_id' => 'lesson_id_error',
        ];

        $this->validate($this->param, $rule, $message);

        //删除私有课件
        $apiRes = WebApi::httpPost('WebAPI/deleteRoomsPrivateFiles', ['serials' => $serial]);

        return $this->success($apiRes);
    }

    /**
     * 导出学生排课信息
     */
    public function export()
    {
        $rule = [
            'start_date|' . lang('start_date') => ['require', 'date'],
            'end_date|' . lang('end_date') => [
                'require',
                'date',
                function ($value, $data) {
                    if (strtotime($value) - strtotime($data['start_date']) > 24 * 3600 * 31) {
                        return false;
                    }
                    return true;
                }
            ],
            'has_del|' . lang('has_del') => ['in:0,1']
        ];

        $this->validate($this->param, $rule);

        $save = [];
        $save['name'] = 'export_lesson';
        $save['type'] = 'schedule';
        $save['company_id'] = $this->request->user['company_id'];
        $save['create_time'] = time();
        $save['create_by'] = $this->request->user['user_account_id'];

        $fileId = Db::table('saas_file_export')->insertGetId($save);

        $queParams = array_merge($this->request->param(), [
            'key' => $this->request->user['company_model']['authkey'],
            'company_id' => $this->request->user['company_id'],
            'create_by' => $this->request->user['user_account_id'],
            'lang' => Lang::getLangSet(),
            'fileId' => $fileId,
            'fileName' => MD5(microtime(true) . $this->request->user['user_account_id']),
        ]);

        Queue::push(Export::class, $queParams, 'file_export');

        return $this->success();
    }

    public function getAllMonths()
    {
        $course_id = $this->request->param("course_id");
        $months = Room::group("FROM_UNIXTIME(starttime,'%Y-%m')")
            ->where(function ($q) use ($course_id) {
                if (!empty($course_id)) {
                    $q->where("course_id", $course_id);
                }
            })
            ->order('starttime', 'asc')
            ->column("FROM_UNIXTIME(starttime,'%Y-%m') m");
        return $this->success(array_column($months, 'm'));
    }
}
