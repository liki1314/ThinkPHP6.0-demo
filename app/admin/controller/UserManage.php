<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\job\StudentExport;
use app\admin\model\{
    CompanyUser,
    Room,
    StudentGroup,
    FrontUser,
    Area,
    AuthGroup,
    AuthRule,
    RoomUser,
    Department as DepartmentModel
};
use app\admin\validate\{
    FrontUser as ValidateFrontUser,
    User as UserValidate
};
use app\common\facade\Live;
use app\common\facade\Excel;
use app\gateway\model\UserAccount;
use think\exception\ValidateException;
use think\facade\Lang;
use think\facade\Queue;
use think\helper\Arr;
use think\facade\Cache;
use think\facade\Db;

class UserManage extends Base
{
    public function index()
    {
        return $this->success($this->searchList(CompanyUser::class));
    }

    public function all()
    {
        $data = CompanyUser::withSearch(['all', 'enable'], $this->param)->select();

        return $this->success($data);
    }

    public function save()
    {
        $this->validate($this->param, UserValidate::class);

        Db::transaction(function () {
            $userAccountId = (new CompanyUser())->addUser($this->param);
        });

        return $this->success();
    }

    public function read($id)
    {
        $model = CompanyUser::withJoin('user')
            ->with('role')
            ->find($id)
            ->withAttr('department_id', function ($value) {
                return DepartmentModel::select()->parent($value)->column('id');
            })
            ->hidden(['user', 'role'])
            ->append(['roles']);

        $abbreviation_code = config('countrycode')['abbreviation_code']; // 区域号码
        $model->locale = isset($abbreviation_code[$model->locale]) ? $model->locale : 'CN';
        $model->code = $abbreviation_code[$model->locale];
        $model->mobile = substr($model->account, strlen($model->code));

        return $this->success($model);
    }

    public function update($id)
    {
        $this->validate($this->param, UserValidate::class);

        $model = CompanyUser::findOrFail($id);
        event('app\admin\model\CompanyUser.BeforeDelete', $model);
        $model->editUser($this->param);

        return $this->success();
    }

    public function delete($id)
    {
        CompanyUser::findOrFail($id)->delete();

        return $this->success();
    }

    public function batchDel($id)
    {
        $userId = CompanyUser::whereIn('id', $id)
            ->column('user_account_id');

        Db::transaction(function () use ($userId, $id) {
            foreach ($userId as $value) {
                $model = FrontUser::where('user_account_id', $value)
                    ->where('userroleid', FrontUser::TEACHER_TYPE)
                    ->findOrEmpty();

                if (!$model->isEmpty()) {
                    $model->delete();
                }
            }

            CompanyUser::destroy($id);
        });

        return $this->success();
    }

    public function enable($id)
    {
        CompanyUser::findOrFail($id)->save(['state' => CompanyUser::ENABLE_STATE]);

        return $this->success();
    }

    public function batchEnable($id)
    {
        Db::transaction(function () use ($id) {
            CompanyUser::whereIn('id', $id)->select()->update(['state' => CompanyUser::ENABLE_STATE]);

            FrontUser::whereIn(
                'user_account_id',
                CompanyUser::whereIn('id', $id)->column('user_account_id')
            )
                ->where('userroleid', FrontUser::TEACHER_TYPE)
                ->save(['ucstate' => FrontUser::ENABLE]);
        });

        return $this->success();
    }

    public function disable($id)
    {
        $model = CompanyUser::findOrFail($id);
        event('app\admin\model\CompanyUser.BeforeDelete', $model);
        $model->save(['state' => CompanyUser::DISABLE_STATE]);
        return $this->success();
    }

    public function batchDisable($id)
    {
        $models = CompanyUser::whereIn('id', $id)->select();
        event('app\admin\model\CompanyUser.BeforeDelete', $models);

        Db::transaction(function () use ($models) {
            $models->update(['state' => CompanyUser::DISABLE_STATE]);
        });

        return $this->success();
    }

    public function saveStudentGroup($names)
    {
        $this->validate(
            $this->param,
            [
                'names|' . lang('group_name') => [
                    'require',
                    'array',
                    'each' => 'max:20',
                    function ($value) {
                        return StudentGroup::whereIn('typename', $value)->count() > 0 ? lang('group_name_repeat') : true;
                    }
                ]
            ]
        );

        (new StudentGroup())->invoke('addGroup', [$names]);

        return $this->success();
    }

    public function studentGroupList()
    {
        $data = $this->searchList(StudentGroup::class);
        return $this->success($data);
    }

    public function allStudentGroup()
    {
        $models = StudentGroup::withSearch(['enable'])->field('id typeid,typename')->select();

        return $this->success($models);
    }

    public function updateStudentGroup($id, $name)
    {
        $this->validate(
            $this->param,
            ['name|' . lang('group_name') => 'require|max:20|unique:' . StudentGroup::class . ',typename']
        );

        StudentGroup::where('id', $id)->update(['typename' => $name]);

        return $this->success();
    }

    public function deleteStudentGroup($id)
    {
        $this->validate($this->param, ['id' => 'require|array']);

        StudentGroup::whereIn('id', $id)->delete();

        return $this->success();
    }

    public function changeStudentGroupState($id, $state)
    {
        $this->validate($this->param, ['id' => 'require|array']);

        StudentGroup::whereIn('id', $id)->update(['state' => $state]);

        return $this->success();
    }

    /**
     * 批量报班
     *
     * @param array $course_id 课程id数组
     * @param array $student_id 学生id数组
     * @return void
     */
    public function attendClass($course_id, $student_id)
    {
        $this->validate(
            $this->param,
            [
                'course_id|' . lang('course') => [
                    'require',
                    'array',
                    // 'length' => 1,
                ],
                'student_id|' . lang('student') => [
                    'require',
                    'array',
                    function ($value) {
                        return FrontUser::withSearch(['student'])
                            ->whereIn('id', $value)
                            ->count() === count($value);
                    }
                ]
            ]
        );

        RoomUser::attendClass($course_id, $student_id);

        return $this->success();
    }

    /**
     * 学生批量分组
     *
     * @param array $student_id 学生id数组
     * @param array $group_id 分组id数组
     * @return void
     */
    public function batchDivideStudent($student_id, $group_id)
    {
        $this->validate(
            $this->param,
            [
                'student_id|' . lang('student') => [
                    'require',
                    'array',
                    function ($value) {
                        return FrontUser::withSearch(['student'])
                            ->whereIn('id', $value)
                            ->count() === count($value);
                    }
                ],
                'group_id|' . lang('group_id') => [
                    'require',
                    'array',
                    function ($value) {
                        return StudentGroup::whereIn('id', $value)->count() === count($value);
                    }
                ]
            ]
        );

        Db::transaction(function () use ($student_id, $group_id) {
            FrontUser::whereIn('id', $student_id)
                ->withSearch(['student'])
                ->select()
                ->map(function ($model) use ($group_id) {
                    $model->groups()->sync($group_id);
                });
        });

        return $this->success();
    }

    /**
     * 分组批量分配学生
     *
     * @param array $group_id 分组id数组
     * @param array $student_id 学生id数组
     * @return void
     */
    public function batchDivideGroup($group_id, $student_id)
    {
        $this->validate(
            $this->param,
            [
                'student_id|' . lang('student') => [
                    'array',
                    function ($value) {
                        return FrontUser::withSearch(['student'])
                            ->whereIn('id', $value)
                            ->count() === count($value);
                    }
                ],
                'group_id|' . lang('group_id') => [
                    'require',
                    'array',
                ]
            ]
        );

        $models = StudentGroup::whereIn('id', $group_id)->select();
        if ($models->count() !== count($group_id)) {
            throw new ValidateException(lang('group error'));
        }

        Db::transaction(function () use ($student_id, $models) {
            $models->map(function ($model) use ($student_id) {
                $model->users()->sync($student_id);
            });
        });

        return $this->success();
    }

    public function userList()
    {
        /** @var \app\common\Collection $data */
        $data = $this->searchList(FrontUser::class);
        return $this->success($data);
    }

    public function readUser($id)
    {
        $model = FrontUser::withSearch(['detail'])->findOrFail($id);
        return $this->success($model);
    }

    public function saveUser($userroleid)
    {
        $this->validate($this->param, ValidateFrontUser::class . '.' . ($userroleid == FrontUser::STUDENT_TYPE ? 'student' : 'teacher'));

        Db::transaction(function () {
            $userAccountId = (new FrontUser())->addUserAccount($this->param);
        });

        return $this->success();
    }

    public function updateUser($userroleid, $id)
    {
        $this->validate($this->param, ValidateFrontUser::class . '.' . ($userroleid == FrontUser::STUDENT_TYPE ? 'UpdateStudent' : 'UpdateTeacher'));

        $model = FrontUser::findOrFail($id);
        $model->save(Arr::only(
            $this->param,
            ['nickname', 'name', 'sex', 'birthday', 'province', 'city', 'area', 'avatarFile', 'email', 'address', 'p_name', 'relation']
        ));

        return $this->success();
    }

    public function deleteFrontUser($userroleid, $id)
    {
        Db::transaction(function () use ($userroleid, $id) {
            $models = FrontUser::where('userroleid', $userroleid)
                ->whereIn('id', $id)
                ->select();
            $models->delete();

            //同步删除该账号在企业端的老师角色
            Db::name('company_user_role')
                ->using('saas_company_user_role')
                ->join('company_user b', '__TABLE__.company_user_id=b.id')
                ->whereIn('b.user_account_id', $models->where('userroleid', '=', FrontUser::TEACHER_TYPE)->column('user_account_id'))
                ->where('auth_group_id', AuthGroup::TEACHER_ROLE)
                ->delete();
        });

        return $this->success();
    }

    public function disableFrontUser($userroleid, $id)
    {
        $id = (array)$id;

        Db::transaction(function () use ($userroleid, $id) {
            $models = FrontUser::where('userroleid', $userroleid)
                ->whereIn('id', $id)
                ->select();

            $models->update(['ucstate' => FrontUser::DISABLE]);
        });

        return $this->success();
    }

    public function enableFrontUser($userroleid, $id)
    {
        $id = (array)$id;

        Db::transaction(function () use ($userroleid, $id) {
            FrontUser::where('userroleid', $userroleid)
                ->whereIn('id', $id)
                ->update(['ucstate' => FrontUser::ENABLE]);
        });

        return $this->success();
    }

    /**
     * 导入账号
     *
     * @return void
     */
    public function importUser()
    {
        $rule = [
            'excelFile' => ['require', 'fileSize:2096576', 'fileExt:xls,xlsx'],
            'group_id' => ['integer'],
        ];
        $message = [
            'excelFile.require' => 'excelFile_require',
            'excelFile.fileSize' => 'upload_file_error',
            'excelFile.fileExt' => 'upload_file_error',
            'group_id.integer' => 'group_id_error',

        ];
        $this->validate($this->param, $rule, $message);

        $importData = Excel::import($this->request->file('excelFile')->getRealPath());

        $accountType = $importData[1][2] ?? '';
        if ($this->request->route('is_custom') == 1) {
            if ($accountType != '帐号') {
                throw new ValidateException(lang('import_tpl_type_error'));
            }
        } else {
            if ($accountType != '区号') {
                throw new ValidateException(lang('import_tpl_type_error'));
            }
        }

        $data = array_map(function ($item) {
            $item = array_map('trim', $item);
            $d = [
                'name' => $item[0],
                'sex' => $item[1] == '男' ? '1' : ($item[1] == '女' ? '0' : null),
                'company_id' => $this->request->user['company_id'],
                'userroleid' => $this->request->route('userroleid'),
            ];

            if ($this->request->route('is_custom') == 1) { //自定义账号属性
                $d['domain_account'] = (string)$item[2];
                $d['pwd'] = $item[3];
            } else { //手机账号属性
                $d['locale'] = array_search($item[2], config('countrycode.abbreviation_code'));
                $d['mobile'] = $item[3];
                $d['pwd'] = str_pad(substr((string)$d['mobile'], -8), 8, '0', STR_PAD_LEFT);
            }

            if ($this->request->route('userroleid') == FrontUser::STUDENT_TYPE) {
                $d['nickname'] = $item[4];
                $d['p_name'] = $item[6] ?? null;
                $d['birthday'] = is_numeric($item[5]) ? date('Y-m-d', ($item[5] - 25569) * 24 * 3600) : $item[5];
            } else {
                $d['email'] = $item[5];
                $d['pwd']   = $item[4] ?? null;
                $d['birthday'] = is_numeric($item[6]) ? date('Y-m-d', ($item[6] - 25569) * 24 * 3600) : $item[6];
            }

            return $d;
        }, array_filter(
            $importData,
            function ($v, $k) {
                return $k > 1 && (!empty($v[0]) || !empty($v[1]) || !empty($v[2]) || !empty($v[3]));
            },
            ARRAY_FILTER_USE_BOTH
        ));

        if (empty($data)) {
            throw new ValidateException(lang('数据不能为空'));
        }

        $error = [];
        foreach ($data as $key => $value) {
            try {
                $value['is_custom'] = $this->request->route('is_custom');
                $this->validate(
                    $value,
                    ValidateFrontUser::class . '.' . ($this->request->route('userroleid') == FrontUser::STUDENT_TYPE ? 'student' : 'teacher')
                );
            } catch (ValidateException $e) {
                $error[] = lang('import_error', [$key + 1, $value['name'], $e->getMessage()]);
            }
        }
        if (!empty($error)) {
            throw new ValidateException(implode("\n", $error));
        }

        Db::transaction(function () use ($data) {
            if ($this->request->route('is_custom') == 1) {
                $userAccountId = FrontUser::batchCreateUser($data, $this->request->user['company_id'], 2);
            } else {
                $userAccountId = (new FrontUser())->importUser($data);
            }
        });


        return $this->success();
    }

    public function getArea()
    {
        $cache = Cache::remember('cache:area', function () {
            $area = new Area();
            return $area->select()->tree(0, 'id', 'reid');
        }, 0);
        return $this->success($cache);
    }

    public function auth()
    {
        $tree = AuthRule::suffix(config('app.auth_rule_suffix'))->order('sort', 'desc')
            ->select($this->request->user['sys_role'] == AuthGroup::SUPER_ADMIN ? null : array_keys(CompanyUser::getCompanyUserAuth($this->request->user['user_account_id'], $this->request->user['company_id'])))
            ->tree();
        return $this->success($tree);
    }

    public function updatePwd($id)
    {
        $this->validate($this->param, [
            'password' => ['require', 'chsDash', 'length:8,20']
        ], [
            'new_pwd.require' => lang('new_pwd_empty')
        ]);


        $userId = FrontUser::where('id', $id)->value("user_account_id");

        $userAccountModel = new UserAccount();

        $userAccountModel->updatePwd($userId, $this->param['password']);

        return $this->success();
    }

    /**
     * 前台老师列表
     */
    public function teacherAll()
    {
        $data = FrontUser::withSearch(['teacher'], $this->param)
            ->select()
            ->visible(['id', 'name'])
            ->each(function (&$value) {
                $value['name'] = $value['nickname'];
                return $value;
            });
        return $this->success($data);
    }

    /**
     * 根据课节ID 批量分配报班
     * @return \think\response\Json
     */
    public function attendLesson()
    {
        $this->validate(
            $this->param,
            [
                'lesson_id|' . lang('lesson') => [
                    'require',
                    'array',
                    function ($value) {
                        return Room::whereIn('id', $value)
                            ->where('starttime', '>', time())
                            ->count() === count($value) ? true : lang('lesson_id_error');
                    }
                ],
                'student_id|' . lang('student') => [
                    'require',
                    'array',
                    function ($value) {
                        return FrontUser::withSearch(['student'])
                            ->whereIn('id', $value)
                            ->count() === count($value);
                    }
                ]
            ]
        );

        RoomUser::attendLesson($this->param['lesson_id'], $this->param['student_id']);
        return $this->success();
    }


    /**
     * 根据师生ID取消绑定通知
     * @param $userid
     */
    public function cancelBind($userid)
    {
        $model = FrontUser::where('id', $userid)->findOrFail();

        $userModel = UserAccount::where('id', $model['user_account_id'])->findOrFail();

        if ($userModel['userkey']) {
            $userModel->save(['userkey' => '']);
        }

        return $this->success();
    }

    /**
     * 学生信息导出
     */
    public function userListExport()
    {
        $rule = [
            'student_id' => ['array'],
        ];

        $this->validate($this->param, $rule);

        $save = [];
        $save['name'] = 'user_list_student';
        $save['type'] = 'student_export';
        $save['company_id'] = $this->request->user['company_id'];
        $save['create_time'] = time();
        $save['create_by'] = $this->request->user['user_account_id'];

        $fileId = Db::table('saas_file_export')->insertGetId($save);

        $queParams = array_merge($this->request->param(), [
            'userroleid' => $this->request->route('userroleid'),
            'company_id' => $this->request->user['company_id'],
            'create_by' => $this->request->user['user_account_id'],
            'lang' => Lang::getLangSet(),
            'fileId' => $fileId,
            'fileName' => MD5(microtime(true) . $this->request->user['user_account_id']),
            'student_id' => $this->param['student_id'] ?? [],
        ]);

        Queue::push(StudentExport::class, $queParams, 'student_export');

        return $this->success();
    }
}
