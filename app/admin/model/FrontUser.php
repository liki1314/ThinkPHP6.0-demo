<?php

declare(strict_types=1);

namespace app\admin\model;

use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use app\common\service\Upload;
use think\Exception;
use app\gateway\model\UserAccount;
use think\helper\Arr;

class FrontUser extends Base
{
    // 设置json类型字段
    protected $json = ['extra_info'];

    /** 教师 */
    const TEACHER_TYPE = 7;
    /** 学生 */
    const STUDENT_TYPE = 8;

    const DISABLE = 2;
    const ENABLE = 0;

    /** 默认头像路径 */
    const DEFAULT_AVATAR = [
        self::TEACHER_TYPE => [
            0 => '/image/woman_teacher.png',
            1 => '/image/man_teacher.png'
        ],
        self::STUDENT_TYPE => [
            0 => '/image/woman_student.png',
            1 => '/image/man_student.png'
        ]
    ];

    const RoleVK = [
        self::TEACHER_TYPE => 0,
        self::STUDENT_TYPE => 2
    ];


    protected $map = [
        'nickname' => 'companynickname',
        'name' => 'nickname',
    ];

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        // 校验当前学生\老师是否已存在当前企业
        validate(
            [
                'user_account_id' => 'unique:' . get_class($model) . ',user_account_id^userroleid^company_id=' . $model['user']['company_id'],
                'group_id|' . lang('group_id') => ['integer', 'exist:' . StudentGroup::class]
            ],
            [
                'user_account_id' => lang('data_exist')
            ]
        )->check($model->toArray());

        if (isset($model['mobile'])) {
            $model->set('username', $model['areacode'] . $model['mobile']);
        }

        $model->set('user_account_id', $model['user_account_id']);
    }

    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        $changed = $model->getChangedData();
        if (isset($changed['ucstate']) && $changed['ucstate'] == self::DISABLE) {
            self::onBeforeDelete($model);
        }

        if (isset($model['birthday']) && empty($model['birthday'])) {
            $model->set('birthday', null);
        }

        if ($model['userroleid'] == self::STUDENT_TYPE) {
            $model->set('extra_info', ['p_name' => $model['p_name'] ?: '', 'relation' => $model['relation'] ?: '']);
        }
    }

    public static function onBeforeDelete($model)
    {
        if ($model['userroleid'] == self::TEACHER_TYPE) {
            $room_id = Room::where('teacher_id', $model->getKey())->where('endtime', '>', time())->value('id');
        } elseif ($model['userroleid'] == self::STUDENT_TYPE) {
            $room_id = Room::alias('b')
                ->join('room_user a', 'a.room_id=b.id')
                ->where('front_user_id', $model->getKey())
                ->where('endtime', '>', time())
                ->value('b.id');
        }

        if (!empty($room_id)) {
            throw new ValidateException(lang('Please delete the arrangement information before disabling the operation.'));
        }
    }

    public static function onAfterWrite($model)
    {
        $company_id = request()->user['company_id'];

        //如果是添加教师信息,则加入后台账户表
        if ($model['userroleid'] == self::TEACHER_TYPE) {

            $roleId = Db::name('company_user')
                ->where('user_account_id', $model['user_account_id'])
                ->where('company_id', $company_id)
                ->value('sys_role');

            //超管不做处理
            if (AuthGroup::SUPER_ADMIN == $roleId) {
                return;
            }

            Db::table('saas_user_account')
                ->where('id', $model['user_account_id'])
                ->update(['username' => $model['name']]);

            $changeData = $model->getChangedData();
            $companyUser = Db::name('company_user')
                ->where('user_account_id', $model['user_account_id'])
                ->where('company_id', $company_id)
                ->find();

            if ($companyUser) {
                $update = [];

                if (isset($changeData['name']) && $changeData['name']) {
                    $update['username'] = $changeData['name'];
                }

                if ($update) {
                    $update['create_time'] = time();
                    Db::name('company_user')->where('id', $companyUser['id'])->update($update);
                }

                $role = Db::name('company_user_role')
                    ->where('company_user_id', $companyUser['id'])
                    ->where('auth_group_id', FrontUser::TEACHER_TYPE)
                    ->find();

                if (!$role) {
                    Db::name('company_user_role')->insert(['company_user_id' => $companyUser['id'], 'auth_group_id' => self::TEACHER_TYPE]);
                }
            } else {
                $companyuser_data = [
                    'user_account_id' => $model['user_account_id'],
                    'company_id' => $company_id,
                    'create_time' => time(),
                    'username' => $model['name'],
                ];
                $companyuser_id = Db::name('company_user')->insertGetId($companyuser_data);
                if (!$companyuser_id) {
                    throw new ValidateException(lang('create_company_user_fail'));
                }

                if (!Db::name('company_user_role')->insert(['company_user_id' => $companyuser_id, 'auth_group_id' => self::TEACHER_TYPE])) {
                    throw new ValidateException(lang('create_company_user_role'));
                }
            }
        }
    }

    public function user()
    {
        return $this->belongsTo(UserAccount::class)->bind(['mobile' => 'account', 'locale', 'code', 'userkey']);
    }

    // 学生分组
    public function groups()
    {
        return $this->belongsToMany(StudentGroup::class, 'frontuser_group', 'group_id');
    }

    // 角色id搜索器
    public function searchUserroleidAttr($query, $value)
    {
        $query->where('__TABLE__.userroleid', $value);
    }

    public function searchStudentAttr($query)
    {
        $query->where('__TABLE__.userroleid', self::STUDENT_TYPE);
    }

    public function searchTeacherAttr($query)
    {
        $query->where('__TABLE__.userroleid', self::TEACHER_TYPE);
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field("id,user_account_id as userid,userroleid,companynickname as nickname,avatar,create_time as createtime,sex,nickname as name,ucstate,birthday")
            ->withJoin('user')
            ->order('createtime desc')
            ->append(['http_avatar', 'domain_account', 'qrcode'])
            ->hidden(['user', 'userkey']);

        if ($data['userroleid'] == self::TEACHER_TYPE) {
            $query->field('email');
        }
        if ($data['userroleid'] == self::STUDENT_TYPE) {
            $query->field('extra_info,birthday');
        }
        //手机号查询
        if (!empty($data['mobile'])) {
            $query->whereLike('user.account', "%" . $data['mobile']);
        }
        //姓名查询
        if (!empty($data['name'])) {
            $query->whereLike('__TABLE__.nickname', '%' . $data['name'] . '%');
        }
        //分组id查询
        if (!empty($data['groupid'])) {
            $query->whereIn('__TABLE__.id', function ($query) use ($data) {
                $query->name('frontuser_group')->where('group_id', $data['groupid'])->field('front_user_id');
            });
        }
        //账号状态查询
        if (isset($data['ucstate'])) {
            if (in_array($data['ucstate'], ['0', '2'])) {
                $query->where('__TABLE__.ucstate', $data['ucstate']);
            }
        }

        if (!empty($data['no_page']) || isset($data['export'])) {
            $this->isPage = false;
        }
    }

    public function getDomainAccountAttr()
    {
        $data = $this->getAttr('user')['extend_info'];
        if (!is_array($data) && !empty($data)) {
            $data = json_decode($data, true);
        }
        return $data['domain_account'] ?? null;
    }

    // 课节id搜索器
    public function searchRoomIdAttr($query, $value)
    {
        $query->whereIn('__TABLE__.' . $this->getPk(), RoomUser::where('room_id', $value)->column('front_user_id'));
    }

    // 课程id搜索器
    public function searchCourseIdAttr($query, $value)
    {
        $studentId = Course::find($value) ? Course::find($value)->getData('students') : [0];
        $id = $studentId ?: [0];
        $query->whereIn('__TABLE__.' . $this->getPk(), $id);
    }

    public function getCompanynicknameAttr($value, $data)
    {
        return $value ?: ($data['nickname'] ?? null);
    }

    public function setAvatarFileAttr($value)
    {
        if ($value instanceof \think\File) {
            $this->set('avatar', Upload::putFile($value));
        } else {
            $this->set('avatar', '');
        }
    }

    public function getCreateTimeAttr($value)
    {
        return date('Y-m-d', $value);
    }

    public function getHttpAvatarAttr($value, $data)
    {
        return isset(self::DEFAULT_AVATAR[$data['userroleid']]) ? Upload::getFileUrl($data['avatar'] ?: self::DEFAULT_AVATAR[$data['userroleid']][$data['sex']], $data['avatar'] ? '' : 'local') : '';
    }

    public function getAvatarAttr($value, $data)
    {
        return isset(self::DEFAULT_AVATAR[$data['userroleid']]) ? Upload::getFileUrl($value ?: self::DEFAULT_AVATAR[$data['userroleid']][$data['sex']], $value ? '' : 'local') : '';
    }

    public function getCodeAttr($value, $data)
    {
        return Config::get('countrycode')['abbreviation_code'][$data['locale']] ?? '86';
    }

    public function getMobileAttr($value)
    {
        return $value ? substr((string)$value, strlen($this->getAttr('code'))) : '';
    }

    public function searchDetailAttr($query)
    {
        $query->field('id,user_account_id userid,userroleid,email,avatar,sex,ucstate,birthday,extra_info,nickname as name,companynickname as nickname')
            ->withJoin('user')
            ->append(['http_avatar', 'code'])
            ->hidden(['user']);
    }

    /**
     * 添加老师或者学生的账号
     * @param $data
     */
    public function addUserAccount($data): int
    {
        $this->startTrans();
        try {
            $data = UserAccount::saveUser($data);

            // $model = self::create($data);
            $model = self::onlyTrashed()
                ->where('user_account_id', $data['user_account_id'])
                ->where('company_id', request()->user['company_id'])
                ->where('userroleid', $data['userroleid'])
                ->findOrEmpty();

            if ($model->isEmpty()) {
                $model->save($data);
            } else {
                $model->restore();
                $model->save($data);
                if (isset($data['pwd']) && $data['pwd']) {
                    (new UserAccount)->updatePwd($model['user_account_id'], $data['pwd']);
                }
            }

            if (!empty($data['group_id'])) {
                $model->groups()->save($data['group_id']);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }

        return intval($data['user_account_id']);
    }

    public function importUser($tmpData): array
    {
        $user_account_id = [];

        Db::startTrans();
        foreach ($tmpData as $k => $v) {
            try {
                $user_account_id[] = $this->addUserAccount(array_filter($v));
            } catch (\Exception $e) {
                $failData[] = lang('import_error', [$k + 1, $tmpData[$k]['name'], $e->getMessage()]);
            }
        }
        if (!empty($failData)) {
            Db::rollback();
            throw new ValidateException(implode("\n", $failData));
        }
        Db::commit();

        return $user_account_id;
    }

    public static function batchCreateUser(array $data, $company_id, $mode = 1)
    {
        $user_account_id = [];

        self::startTrans();
        try {
            if ($mode == 2) {
                $error = [];
                foreach ($data as $key => $value) {
                    try {
                        $id = UserAccount::where('extend_info->domain_account', $value['domain_account'])->value('id');
                        $d = Arr::only($value, [
                            'userroleid',
                            'name',
                            'nickname',
                            'email',
                            'sex',
                            'birthday'
                        ]);

                        $d['p_name'] = $value['p_name'] ?? null;
                        $d['relation'] = $value['relation'] ?? null;

                        if (!empty($id)) {
                            $user = self::where('user_account_id', $id)
                                ->where('company_id', $company_id)
                                ->where('userroleid', $value['userroleid'])
                                ->findOrEmpty();

                            $model = self::onlyTrashed()
                                ->where('user_account_id', $id)
                                ->where('company_id', $company_id)
                                ->where('userroleid', $value['userroleid'])
                                ->findOrEmpty();

                            if (!$user->isEmpty()) {
                                throw new Exception(lang('账号已注册'));
                            }

                            if ($model->isEmpty()) {
                                $d['user_account_id'] = $id;
                                self::create($d);
                            } else {
                                $model->restore();
                            }
                        } else {
                            $userAccountModel = UserAccount::create([
                                'username' => $value['name'],
                                'pwd' => $value['pwd'],
                                'extend_info' => ['domain_account' => $value['domain_account']]
                            ]);

                            $id = $userAccountModel->getKey();
                            $d['user_account_id'] = $id;
                            self::create($d);
                        }

                        $user_account_id[] = $id;
                    } catch (\Exception $e) {
                        $error[] = lang('import_error', [$key + 1, $value['name'], $e->getMessage()]);
                    }
                }

                if (!empty($error)) {
                    throw new ValidateException(implode("\n", $error));
                }

                self::commit();
            }
        } catch (\Exception $e) {
            self::rollback();
            throw $e;
        }

        return $user_account_id;
    }

    public function getQrcodeAttr($value, $data)
    {
        return empty($data['userkey']) ? 0 : 1;
    }

    public function searchStudentexportAttr($query, $value, $data)
    {
        $query->alias('a')
            ->join(['saas_user_account' => 'b'], 'a.user_account_id=b.id')
            ->field([
                'a.nickname name',
                'a.companynickname nickname',
                'a.sex',
                'a.birthday',
                'b.account',
                "JSON_UNQUOTE(b.extend_info->'$.domain_account')" => 'user_domain',
                "JSON_UNQUOTE(a.extra_info->'$.p_name')" => 'p_name',
                "JSON_UNQUOTE(a.extra_info->'$.relation')" => 'relation',
                'b.userkey qrcode',
                'user_account_id'
            ])
            ->when(!empty($data['userroleid']), function ($query) use ($data) {
                $query->where('a.userroleid', $data['userroleid']);
            })
            ->when(!empty($data['mobile']), function ($query) use ($data) {
                $query->whereLike('b.account', "%" . $data['mobile']);
            })
            ->when(!empty($data['name']), function ($query) use ($data) {
                $query->whereLike('a.nickname', '%' . $data['name'] . '%');
            })->when(!empty($data['groupid']), function ($query) use ($data) {
                $query->whereIn('a.id', function ($query) use ($data) {
                    $query->name('frontuser_group')->where('group_id', $data['groupid'])->field('front_user_id');
                });
            })->when(isset($data['ucstate']), function ($query) use ($data) {
                if (in_array($data['ucstate'], ['0', '2'])) {
                    $query->where('a.ucstate', $data['ucstate']);
                }
            })->when(!empty($data['student_id']), function ($query) use ($data) {
                $query->whereIn('a.id', $data['student_id']);
            })
            ->append(['link']);
    }
}
