<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\common\service\Upload;
use think\facade\Config;
use think\Model;
use Carbon\Carbon;

/**
 * @mixin \think\Model
 */
class FrontUser extends Base
{
    // 设置json类型字段
    protected $json = ['extra_info'];
    /** 教师 */
    const TEACHER_TYPE = 7;
    /** 学生 */
    const STUDENT_TYPE = 8;

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

    const DISABLE = 2;
    const ENABLE = 0;

    /** 进入教室身份与userroleid映射关系 */
    const IDENTITY_MAP = [
        self::STUDENT_TYPE => 2,
        self::TEACHER_TYPE => 0,
    ];

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_account_id', 'id')->bind(['live_userid', 'mobile' => 'account', 'locale', 'code']);
    }

    public function getAvatarAttr($value, $data)
    {
        return isset(self::DEFAULT_AVATAR[$data['userroleid']]) ? Upload::getFileUrl($value ?: self::DEFAULT_AVATAR[$data['userroleid']][$data['sex']], $value ? '' : 'local') : '';
    }


    public function searchUserroleidAttr($query, $value)
    {
        $query->where('__TABLE__.userroleid', $value);
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field("id,user_account_id as userid,userroleid,companynickname,avatar,create_time as createtime,sex,nickname,ucstate,birthday")
            ->withJoin('user')
            ->order('createtime desc')
            ->append(['http_avatar', 'domain_account'])
            ->hidden(['user']);

        if (isset($data['userroleid']) && $data['userroleid'] == self::TEACHER_TYPE) {
            $query->field('email');
        }
        if (isset($data['userroleid']) && $data['userroleid'] == self::STUDENT_TYPE) {
            $query->field('extra_info,birthday');
        }
        //手机号查询
        if (!empty($data['mobile'])) {
            $query->whereLike('user.account', "%" . $data['mobile']);
        }

        $query->where('__TABLE__.company_id', request()->user['company_id']);

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
    }

    public function searchTeacherAttr($query)
    {
        $query->where('__TABLE__.userroleid', self::TEACHER_TYPE);
    }

    public function getNameAttr($value, $data)
    {
        return isset($data['username']) ? $data['username'] : ($data['nickname'] ?? '');
    }

    // 课节id搜索器
    public function searchRoomIdAttr($query, $value)
    {
        $query->whereIn('__TABLE__.' . $this->getPk(), RoomUser::where('room_id', $value)->column('front_user_id'));
    }

    // 课节id搜索器
    public function searchLessonIdAttr($query, $value)
    {
        $query->whereIn('__TABLE__.' . $this->getPk(), RoomUser::where('room_id', $value)->column('front_user_id'));
    }

    public function searchSerialAttr($query, $value)
    {
        $query->whereIn('__TABLE__.' . $this->getPk(), RoomUser::where('room_id', $value)->column('front_user_id'));
    }

    public function getIsRemarkAttr($value)
    {
        return isset($value['serial']) ? 1 : 0;
    }

    public function searchStudentAttr($query)
    {
        $query->where('__TABLE__.userroleid', self::STUDENT_TYPE);
    }

    public function searchIdentityAttr($query, $value, $user)
    {
        $query->where('user_account_id', $user['user_account_id'])
            ->whereIn('userroleid', [self::STUDENT_TYPE, self::TEACHER_TYPE])
            ->group('userroleid');
    }


    public function getAgeAttr($value, $data)
    {
        return Carbon::create($data['birthday'])->age;
    }

    public function getCodeAttr($value, $data)
    {
        return Config::get('countrycode')['abbreviation_code'][$data['locale']] ?? '86';
    }

    public function getMobileAttr($value)
    {
        return $value ? substr((string)$value, strlen($this->getAttr('code'))) : '';
    }

    public function getHttpAvatarAttr($value, $data)
    {
        return isset(self::DEFAULT_AVATAR[$data['userroleid']]) ? Upload::getFileUrl($data['avatar'] ?: self::DEFAULT_AVATAR[$data['userroleid']][$data['sex']], $data['avatar'] ? '' : 'local') : '';
    }

    public function getDomainAccountAttr()
    {
        $data = $this->getAttr('user')['extend_info'];
        if (!is_array($data) && !empty($data)) {
            $data = json_decode($data, true);
        }
        return $data['domain_account'] ?? null;
    }
}
