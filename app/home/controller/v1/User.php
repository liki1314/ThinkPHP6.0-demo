<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use app\home\model\saas\FrontUser;
use thans\jwt\facade\JWTAuth;
use app\home\model\saas\{Company, Course, Notice};
use think\facade\Db;
use think\helper\Arr;

class User extends \app\BaseController
{
    /**
     * 切换登录身份
     *
     * @return void
     */
    public function changeLoginIdentity($identity = 8)
    {
        $users = FrontUser::where('user_account_id', $this->request->user['user_account_id'])
            ->whereIn('userroleid', [FrontUser::STUDENT_TYPE, FrontUser::TEACHER_TYPE])
            ->when(isset($this->request->user['company_id']), function ($query) {
                $query->where('company_id', $this->request->user['company_id']);
            })
            ->select();

        $result = [
            'identitys' => array_values(array_unique($users->column('userroleid')))
        ];

        if (in_array($identity, $result['identitys'])) {
            $result['current_identity'] = $identity;
        } elseif (!empty($result['identitys'])) {
            $result['current_identity'] = $result['identitys'][0];
        } else {
            $result['current_identity'] = null;
        }


        $user = $this->request->user;
        $user['current_identity'] = $result['current_identity'];
        $user['companys'] = array_values(array_unique($users->column('company_id')));
        JWTAuth::invalidate(JWTAuth::token()->get());
        $result['token'] = JWTAuth::builder(['data' => json_encode($user)]);

        return $this->success($result);
    }


    /**
     * 我的主页
     */
    public function homepage()
    {
        $data = (new Course)->homepage($this->request->user['user_account_id']);
        $data['identitys'] = FrontUser::where('user_account_id', $this->request->user['user_account_id'])
            ->whereIn('userroleid', [FrontUser::STUDENT_TYPE, FrontUser::TEACHER_TYPE])
            ->when(isset($this->request->user['company_id']), function ($query) {
                $query->where('company_id', $this->request->user['company_id']);
            })
            ->group('userroleid')
            ->column('userroleid');

        $data['courses'] = Course::withSearch('user', $this->param)->count();
        $data['config'] = [
            'remove_account' => config('app.config.remove_account'),
            'phone' => config('app.config.phone'),
        ];

        return $this->success($data);
    }

    /**
     * 通知列表
     *
     */
    public function notice()
    {
        return $this->success($this->searchList(Notice::class));
    }

    /**
     * 更新通知状态
     *
     */
    public function updateNotice()
    {
        $rule = [
            'id' => ['array', 'require', 'each' => ['integer']],
        ];

        $message = [
            'id' => lang('notice_id_error'),
        ];

        $this->validate($this->param, $rule, $message);

        Notice::whereIn('id', $this->param['id'])->update(['read_time' => time()]);

        return $this->success();
    }

    /**
     * 获取未读数量
     * @return \think\response\Json
     */
    public function countNotice()
    {
        $count = Notice::where('read_time', 0)->count();

        $num = Db::table('saas_homework_record')
            ->alias('a')
            ->join(['saas_homework' => 'c'], 'a.homework_id=c.id')
            ->join(['saas_front_user' => 'b'], 'a.student_id=b.id')
            ->where('b.userroleid', $this->request->user['current_identity'])
            ->where('b.user_account_id', $this->request->user['user_account_id'])
            ->where('a.read_time', 0)
            ->where('c.delete_time', 0)
            ->where('c.is_draft', 0)
            ->where(function ($query) {
                $query->where('c.issue_status', 1)->whereOr('c.day', '<=', date('Y-m-d'));
            })
            ->count();

        return $this->success(['count' => $count, 'homework_unreads' => $num]);
    }

    public function companyList()
    {
        $models = Company::alias('a')
            ->whereExists(function ($query) {
                $query->name('front_user')
                    ->alias('b')
                    ->whereColumn('a.id', 'b.company_id')
                    ->where('b.user_account_id', $this->request->user['user_account_id'])
                    ->where('b.userroleid', $this->request->user['current_identity']);
            })
            // ->where('notice_config->scheduling->freetime_switch', 1)
            // ->whereRaw('json_extract(notice_config,"$.scheduling.freetime_switch")=1')
            ->field('id,companyname,notice_config config')
            ->json(['config'])
            ->select()
            ->withAttr('config', function ($value) {
                // return Arr::only(array_replace_recursive(config('app.company_default_config'), (array)$value), ['scheduling']);
                return array_merge(Arr::only(config('app.company_default_config'), ['scheduling', 'navigation', 'time_format']), Arr::only((array)$value, ['scheduling', 'navigation', 'time_format']));
            });

        return $this->success($models);
    }
}
