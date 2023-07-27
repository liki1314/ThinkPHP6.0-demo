<?php

namespace app\admin\model;

use app\common\facade\Live;
use app\common\model\CompanyUser;
use app\common\service\Upload;
use thans\jwt\facade\JWTAuth;
use think\exception\ValidateException;
use think\facade\Db;
use think\model\Relation;
use app\gateway\model\UserAccount;
use app\common\http\CommonAPI;
use app\common\model\Company as ModelCompany;
use Exception;

class Company extends ModelCompany
{
    /** 试用 */
    const TRIAL_STATE = 0;
    /** 正式 */
    const NORMAL_STATE = 1;
    /** 冻结 **/
    const FREEZE_STATE = 4;
    /** 子企业类型：子企业 */
    const CHILD_TYPE = 1;
    /** 子企业类型：代理商 */
    const AGENT_TYPE = 2;
    /** 根企业id */
    const ROOT_COMPANY = 1;

    const CACHE_TIME = 12 * 3600;

    public static function onBeforeInsert($model)
    {
        //子企业初始化正式，主企业初始化试用
        $model->set('companystate', isset($model['iscreatechild']) && $model['iscreatechild'] == 1 ? 1 : 0);
        //主企业预授权额度为0
        if (!(isset($model['iscreatechild']) && $model['iscreatechild'] == 1)) {
            $model->set('credit_limit', 0);
        }
        $time = time();
        $model->set('starttime', date('Y-m-d H:i:s', $time));
        $model->set('endtime', date('Y-m-d H:i:s', $time + (15 * 86400)));
        $model->set('createtime', date('Y-m-d H:i:s', $time));
        $model->set('update_time', $time);
        $model->set('createuserid', request()->user['user_account_id'] ?? '');
        $model->set('parentid', $model['iscreatechild'] == 1 ? request()->user['company_id'] : 1);
        $model->set('companyname', $model['companyfullname']);
    }

    public function users()
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function setIcoAttr($value)
    {
        return empty($value) ? '' : Upload::putFile($value);
    }

    public function setBusinessLicenseAttr($value)
    {
        return empty($value) ? '' : Upload::putFile($value);
    }

    public function setCompanytypeAttr($value)
    {
        $this->set('type', $value);

        return $value;
    }

    public function setIscreatechildAttr($value)
    {
        if (!empty($value)) {
            $this->set('companystate', self::NORMAL_STATE);
        }

        return $value;
    }

    // 有效企业
    public function searchValidAttr($query)
    {
        $query->whereIn('companystate', ['0', '1', '2', '3']);
    }

    // 冻结企业
    public function searchFreezeAttr($query)
    {
        $query->whereIn('companystate', ['4', '5']);
    }

    // 切换企业列表
    public function searchChildAttr($query, $value, $data)
    {
        if ($data['sys_role'] == AuthGroup::SUPER_ADMIN) {
            $query->where('id', $data['user_company_id'])->whereOr('parentid', $data['user_company_id']);
        } else {
            $query->where('id', $data['company_id']);
        }
    }

    /**
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDefaultAttr($query)
    {
        $query->with(['users' => function (Relation $query) {
            $query->withJoin(['user'])->where('sys_role', AuthGroup::SUPER_ADMIN)->withLimit(1);
        }])->where(function ($query) {
            $query->where('id', request()->user['company_id'])->whereOr('parentid', request()->user['company_id']);
        })->field([
            'id',
            'id as companyid',
            'companyfullname',
            'credit_limit',
            'balance',
            'parentid',
            'companystate',
            'createuserid',
            'ico',
            'endtime',
            'type as companytype',
            'locale',
            'mobile'
        ])
            ->append(['self_create', 'mobile', 'code'])
            ->hidden(['createuserid', 'users']);
    }

    public function searchDetailAttr($query)
    {
        $query->field('*,type as companytype,id as companyid')->with(['users' => function (Relation $query) {
            $query->withJoin(['user'])->where('sys_role', AuthGroup::SUPER_ADMIN)->withLimit(1);
        }])
            ->append(['mobile', 'code'])
            ->hidden(['users']);
    }

    /**
     * @param $query
     * @param $value
     */
    public function searchNoPageAttr($query, $value)
    {
        $this->isPage = false;
    }

    /**
     * 搜索参数  企业名称  模糊搜索
     * @param $query
     * @param $value
     */
    public function searchCompanyfullnameAttr($query, $value)
    {
        $query->whereLike('companyfullname', '%' . $value . '%');
    }

    /**
     * 企业状态搜索
     * @param $query
     * @param $value
     */
    public function searchStateAttr($query, $value)
    {
        if ($value == '1') {
            $query->whereIn('companystate', ['0', '1', '2', '3']);
        }
        if ($value == '0') {
            $query->whereIn('companystate', ['4', '5']);
        }
    }

    public function getStartTimeAttr($value)
    {
        return date('Y-m-d', strtotime($value));
    }

    public function getEndtimeAttr($value)
    {
        return date('Y-m-d', strtotime($value));
    }

    public function getIcoAttr($value)
    {
        return Upload::getFileUrl($value);
    }


    public function getSelfCreateAttr($value, $data)
    {
        if ($data['createuserid'] == request()->user['user_account_id']) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getCodeAttr($value, $data)
    {
        return config("countrycode.abbreviation_code.{$data['locale']}") ?: 86;
    }

    /* public function getLocaleAttr()
    {
        return $this->getAttr('users')->first()['locale'] ?? '';
    }

    public function getMobileAttr()
    {
        return $this->getAttr('users')->first()['mobile'] ?? '';
    } */

    /**
     * 通知配置
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getNoticeConfigByCompanyId($companyId = 0)
    {
        $key = sprintf("think:%s.%s|%s", self::getConfig('database'), self::getTable(), $companyId ?: request()->user['company_id']);
        $list = array_replace_recursive(config('app.notice'), self::cache($key)->find($companyId ?: request()->user['company_id'])['notice_config'] ?? []);
        if (isset($list['teacher_enter_in_advance'])) {
            $list['teacher_enter_in_advance'] = intval($list['teacher_enter_in_advance']);
        }
        if (isset($list['teacher_enter_in_advance'])) {
            $list['student_enter_in_advance'] = intval($list['student_enter_in_advance']);
        }
        return $list;
    }


    /**
     * @param $companyId
     * @param $mainCompanyId
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getCompanyBalance($companyId, $mainCompanyId)
    {
        if ($companyId == $mainCompanyId) {
            $condition = ['id|parentid' => $companyId];
        } else {
            $condition = ['parentid' => $mainCompanyId, 'id' => $companyId];
        }
        //如果是主企业，子企业余额也返回
        $companies = self::field(['id', 'companyfullname', 'balance'])->where($condition)->select();
        $ret = ['balance' => 0.00, 'upgradecompany_amount' => config('app.upgradecompany_amount')];
        //只能查询企业自身或者子企业余额
        if (empty($companies)) {
            return $ret;
        }
        $childCompanies = [];
        foreach ($companies as $company) {
            if ($company['id'] == $companyId) {
                $ret['balance'] = $company['balance'];
            } else {
                //子企业返回企业ID、企业名、余额
                $childCompanies[] = [
                    'company_id' => $company['id'],
                    'companyname' => $company['companyfullname'],
                    'balance' => $company['balance']
                ];
            }
        }
        if ($childCompanies) {
            $ret['child_companies'] = $childCompanies;
        }
        return $ret;
    }


    //创建企业
    public function register($data = [])
    {
        $abbreviation_code = config('countrycode')['abbreviation_code']; // 区域号码
        $time = time(); // 当前时间
        $locale = isset($abbreviation_code[$data['locale']]) ? $data['locale'] : 'CN';
        $mobile = isset($data['mobile']) ? $abbreviation_code[$locale] . $data['mobile'] : '';
        Db::startTrans();
        try {
            // 主企业超管创建子企业时先创建子企业的管理员账号
            $saveRes = UserAccount::saveUser(['locale' => $locale, 'mobile' => $data['mobile'], 'username' => $mobile]);

            if (!isset($saveRes['user_account_id'])) {
                throw new ValidateException(lang('create_user_account_fail'));
            }

            $userid = $saveRes['user_account_id'];

            if (Db::table('saas_company')->where('createuserid', $userid)->find()) {
                throw new ValidateException(lang('createuserid_unique'));
            }

            // 创建企业
            $data['createuserid'] = $userid;
            $model = self::create($data);
            $companyid = $model->getKey();

            // 增加企业账号表信息saas_company_user
            $companyuser_data = [
                'user_account_id' => $userid,
                'company_id' => $companyid,
                'create_time' => $time,
                'username' => $data['linkname'],
                'sys_role' => AuthGroup::SUPER_ADMIN,
            ];

            if (!Db::table('saas_company_user')->extra('IGNORE')->insert($companyuser_data)) {
                throw new ValidateException(lang('create_company_user_fail'));
            }

            $data['company_id'] = $companyid;
            //远程创建企业
            Live::createCompany($companyid, null);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        //远程同步分销信息
        $this->syncRelation($data);

        $result = [
            'linkname' => $data['linkname'],
            'mobile' => $data['mobile'] ?? '',
            'email' => $data['email'] ?? '',
            'companyfullname' => $data['companyfullname'],
            '_notin_company' => '1',
            'createtime' => date('Y-m-d H:i:s', $time),
            'update_time' => date('Y-m-d H:i:s', $time),
            'createuserid' => $userid,
            'companyid' => $companyid,
            'companystate' => $model['companystate'],
        ];

        // 验证是否有权限登录当前企业
        $companyUser = \app\admin\model\CompanyUser::getCompanyUserInfo($userid, $companyid);
        // 生成新token
        $result['token'] = JWTAuth::builder(['data' => json_encode($companyUser)]);
        return $result;
    }


    /**
     * 如果存在分销关系则写入记录
     * @param $data
     */
    private function syncRelation($data)
    {
        $save = [];

        if (!empty($data['referral_code'])) {
            $rel = Db::name('referral_relation')
                ->where('referral_code', $data['referral_code'])
                ->find();

            if ($rel && $rel['referral_userid']) {
                $save['marketid'] = $rel['referral_userid'];
            }
        } elseif (!empty($data['referral_userid'])) {
            $save['marketid'] = $data['referral_userid'];
        }

        if (!$save) {
            return true;
        }

        try {
            CommonAPI::httpPost('/CommonAPI/addCompanyLinkMarket', [
                'key' => $this->where('id', $data['company_id'])->value('authkey'),
                'marketid' => $save['marketid'],
            ]);
        } catch (Exception $e) {
        }

        return true;
    }
}
