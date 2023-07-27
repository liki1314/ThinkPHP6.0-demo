<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\common\http\WebApi;
use app\common\service\Upload;
use think\helper\Arr;
use think\Model;
use app\Request;

class Course extends Base
{
    /** 小班课 */
    const SMALL_TYPE = 1;

    /** 大直播 */
    const BIG_TYPE = 2;

    /** 未开始 */
    const UNSTART_STATE = 1;

    /** 进行中 */
    const ING_STATE = 2;

    /** 已结课 */
    const END_STATE = 3;

    protected $json = ['resources'];

    public static function onAfterRead(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            if (!isset($request->user['company_id'])) {
                $request->user = array_merge($request->user ?? [], ['company_id' => $model['company_id']]);
            }
        });
    }

    //课节
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }


    // 我的课程
    public function searchUserAttr($query, $value)
    {
        $query->join('room aa', 'aa.course_id=__TABLE__.id')
            ->where('cc.userroleid', $value['current_identity'])
            ->where('cc.user_account_id', $value['user_account_id'])
            ->where('aa.delete_time',0)
            ->group('__TABLE__.id');

        if ($value['current_identity'] == FrontUser::TEACHER_TYPE) {
            $query->join('front_user cc', 'aa.teacher_id=cc.id');
        } else {
            $query->join('room_user bb', 'aa.id=bb.room_id')->join('front_user cc', 'bb.front_user_id=cc.id');
        }
    }

    // 课程名称
    public function searchNameAttr($query, $value)
    {
        $query->whereLike('name', "%$value%");
    }

    // 课程类型
    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    // 课程状态
    public function searchStateAttr($query, $value)
    {
        $value = explode(',', $value);

        $query->where(function ($query) use ($value) {
            if (in_array(self::UNSTART_STATE, $value)) {
                $query->where('first_start_time', '>', time())->whereOr('first_start_time', 0);
            }

            if (in_array(self::ING_STATE, $value)) {
                $query->whereOr(function ($query) {
                    $query->whereBetweenTimeField('first_start_time', 'latest_end_time');
                });
            }

            if (in_array(self::END_STATE, $value)) {
                $query->whereOr(function ($query) {
                    $query->where('latest_end_time', '<', time())->where('latest_end_time', '>', 0);
                });
            }
        });
    }

    // 课程周期
    public function getPeriodAttr()
    {
        $rooms = $this->getAttr('rooms')
            ->sort(function ($a, $b) {
                return $a['starttime'] <=> $b['starttime'];
            });

        return $rooms->isEmpty() ?: date('Y-m-d', $rooms->first()['starttime']) . '~' . date('Y-m-d', $rooms->last()['starttime']);
    }

    // 课程进度
    public function getScheduleAttr()
    {
        $rooms = $this->getAttr('rooms');
        return $rooms->where('endtime', '<', time())->count() . '/' . $rooms->count();
    }

    // 课程状态
    public function getStateAttr($value, $data)
    {
        if ($data['first_start_time'] == 0 || $data['first_start_time'] > time()) {
            return self::UNSTART_STATE;
        } elseif ($data['first_start_time'] <= time() && $data['latest_end_time'] >= time()) {
            return self::ING_STATE;
        } elseif ($data['latest_end_time'] < time() && $data['latest_end_time'] > 0) {
            return self::END_STATE;
        } else {
            return 0;
        }
    }

    // 老师
    public function getTeachersAttr()
    {
        // 主讲
        $teacher = [];
        foreach ($this->getAttr('rooms') as $room) {
            $teacher[$room['teacher']['id']] = ['avatar' => $room['teacher']['avatar'], 'name' => $room['teacher']['nickname'], 'type' => 1];
        }
        $result = array_values($teacher);

        // 助教
        $helpers = [];
        foreach ($this->getAttr('rooms')->column('helpers') as $user) {
            foreach ($user as $value) {
                $helpers[$value['user_account_id']] = ['avatar' =>Upload::getFileUrl(FrontUser::DEFAULT_AVATAR[FrontUser::TEACHER_TYPE][1],'local'), 'name' => $value['username'], 'type' => 2];
            }
        }
        $result = array_merge($result, array_values($helpers));

        return $result;
    }


    // 助教
    public function getHelpersAttr()
    {
        $helpers = [];
        foreach ($this->getAttr('rooms')->column('helpers') as $user) {
            foreach ($user as $value) {
                $helpers[$value['id']] = ['avatar' => $value['avatar'], 'name' => $value['username'], 'type' => 2];
            }
        }
        return array_values($helpers);
    }

    // 课程类型名
    public function getTypeNameAttr($value, $data)
    {
        return $data['type'] == self::SMALL_TYPE ? lang('Interactive Class') : lang('Large Class');
    }

    // 列表
    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('__TABLE__.id,__TABLE__.type,__TABLE__.name,first_start_time,latest_end_time')
            ->with(['rooms' => function ($query) {
                $query->field('id serial,starttime,endtime,course_id,id')
                    ->withJoin(['teacher' => ['nickname', 'avatar', 'username', 'userroleid', 'sex', 'id', 'user_account_id']])
                    ->with([
                        'helpers.user' => function ($query) {
                            $query->getQuery()->field('__TABLE__.user_account_id,__TABLE__.username');
                        },
                        'student',
                    ]);
            }])
            ->order($data['sort'] ?? ['__TABLE__.create_time' => 'desc'])
            ->append(['period', 'schedule', 'teachers', 'type_name', 'state', 'next_lesson'])
            ->hidden(['rooms', 'first_start_time', 'latest_end_time']);
    }

    // 详情
    public function searchDetailAttr($query)
    {
        $query->field('id,type,name,first_start_time,latest_end_time,resources,company_id')
            ->with(['rooms' => function ($query) {
                $query->field('id serial,starttime,endtime,roomname,course_id,id,custom_id,roomtype,company_id,teacher_id,actual_start_time,actual_end_time')
                    ->order('starttime')
                    ->withJoin(
                        [
                            'company' => ['notice_config']
                        ]
                    )
                    ->with([
                        'helpers' => function ($query) {
                        },
                        'student.user',
                        'homeworks' => function ($query) {
                            $query->field('id,company_id,room_id,is_draft');
                        },
                        'teacher.user' => function ($query) {
                            $query->field(['nickname', 'avatar', 'username', 'userroleid', 'sex', 'id', 'user_account_id']);
                        },
                        'homeworks.studentHomeworks',
                    ]);

                if (request()->user['current_identity'] == FrontUser::TEACHER_TYPE) {
                    $query->join(['saas_front_user' => 'k'], 'k.id=__TABLE__.teacher_id')
                        ->where('k.user_account_id', request()->user['user_account_id']);
                } else {
                    $query->join(['saas_room_user' => 'j'], 'j.room_id=__TABLE__.id')
                        ->join(['saas_front_user' => 'k'], 'k.id=j.front_user_id')
                        ->where('k.user_account_id', request()->user['user_account_id']);
                }


            }])
            ->hidden(['first_start_time', 'latest_end_time', 'rooms'])
            ->append(['period', 'teachers', 'lessons', 'type_name', 'current_lesson', 'state'])
            ->withAttr('resources', function ($value) {
                if (!empty($value)) {
                    $result = WebApi::httpPost('WebAPI/fileInfo', ['fileidarr' => $value]);

                    return array_values(array_map(function ($item) {
                        $item['downloadpath'] = $item['download_url'] ?? null;
                        $item['size'] = human_filesize($item['size']);
                        return Arr::only($item, ['filename', 'filetype', 'size', 'uploadtime', 'preview_url', 'downloadpath']);
                    }, array_filter($result['data'], function ($item) {
                        return $item['status'] == 1;
                    })));
                }
                return [];
            });
    }

    public function getLessonsAttr($value, $data)
    {
        $rooms = $this->getAttr('rooms')->order('starttime');

        if (
            $data['first_start_time'] > time() ||
            $data['latest_end_time'] < time() && $data['latest_end_time'] > 0
        ) {
            $room = $rooms->first();
        } else {
            $room = $rooms->where('starttime', '>=', time())->first();
        }

        $this->set('current_lesson', $room);

        return $rooms->visible(['serial', 'roomname', 'custom_id', 'roomtype', 'starttime', 'endtime'])
            ->hidden(['helpers', 'student', 'teacher', 'company'])
            ->append(['user', 'before_enter', 'state', 'start_date', 'week', 'start_to_end_time', 'times', 'record_url', 'teachers', 'is_join','homeworks']);
    }

    public function getNextLessonAttr()
    {
        $rooms = $this->getAttr('rooms')->order('starttime');
        $list = $rooms->toArray();
        $current_identity = request()->user['current_identity'];
        $user_account_id = request()->user['user_account_id'];

        if ($current_identity == FrontUser::STUDENT_TYPE) {
            foreach ($list as $v) {
                if (isset($v['student']) && $v['student']) {
                    foreach ($v['student'] as $item) {
                        if ($user_account_id == $item['user_account_id'] && $current_identity == $item['userroleid'] && $v['starttime'] > time()) {
                            return ['serial' => $v['serial'], 'starttime' => $v['starttime'], 'endtime' => $v['endtime']];
                        }
                    }
                }
            }
        } else {
            foreach ($list as $v) {
                if (isset($v['teacher']) && $v['teacher']) {
                    if ($user_account_id == $v['teacher']['user_account_id'] && $current_identity == $v['teacher']['userroleid'] && $v['starttime'] > time()) {
                        return ['serial' => $v['serial'], 'starttime' => $v['starttime'], 'endtime' => $v['endtime']];
                    }
                }
            }
        }
    }

    /**
     * 我的主页
     * @param $userid
     */
    public function homepage($userid)
    {
        $data = [];
        $data['cups'] = 0;

        $data['flowers'] = 0;
        return $data;
    }
}
