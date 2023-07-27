<?php

declare(strict_types=1);

/**
 * webapi 消费进入教室时长队列
 */

namespace app\webapi\job;

use app\common\service\room\RoomStudentsMsg;
use think\queue\Job;
use think\facade\Db;
use app\webapi\model\FrontUser;

class Access
{

    public function fire(Job $job, $params)
    {
        if (!$user_account_id = Db::table('saas_user_account')->where('live_userid', $params['live_userid'])->value('id')) {
            if (!$user_account_id = Db::table('saas_user_account')->where('id', $params['live_userid'])->value('id')) {
                $job->delete();
                return false;
            }
        }

        $roomInfo = Db::table('saas_room')
            ->alias('a')
            ->field('a.id,a.company_id,b.type')
            ->join(['saas_company' => 'b'], 'a.company_id=b.id')
            ->where('custom_id', $params['thirdroomid'])
            ->find();

        if (!$roomInfo) {
            $job->delete();
            return false;
        }

        $save = [];
        $save['entertime'] = $params['entertime'] ?? 0;
        $save['outtime'] = $params['outtime'] ?? 0;
        $save['user_account_id'] = $user_account_id;
        $save['company_user_id'] = 0;

        $type = 2; //后台账户
        //师生
        if (in_array($params['live_userroleid'], ['0', '2', '98'])) {
            $type = 1; //前台账户
            $userroleid = $params['live_userroleid'] == 0 ? FrontUser::TEACHER_TYPE : FrontUser::STUDENT_TYPE;

            $company_user = Db::table('saas_front_user')
                ->field(['id', 'company_id'])
                ->where('user_account_id', $user_account_id)
                ->where('userroleid', $userroleid)
                ->when($roomInfo['type'] != 6, function ($query) use ($roomInfo) {
                    $query->where('company_id', $roomInfo['company_id']);
                })
                ->find();

            if (empty($company_user)) {
                $job->delete();
                return false;
            }
            $save['company_user_id'] = $company_user['id'];
            RoomStudentsMsg::setStudentsStatus(
                $roomInfo['id'],
                $company_user['id'],
                !empty($save['entertime']) ? sprintf("1-%s", $save['entertime']) : sprintf("2-%s", $save['outtime'])
            );
        }

        $save['type'] = $type;
        $save['room_id'] = $roomInfo['id'];

        //进入教室
        if ($save['entertime']) {
            Db::table('saas_room_access_record')->insert($save);
        } else {
            Db::table('saas_room_access_record')
                ->where('user_account_id', $save['user_account_id'])
                ->where('company_user_id', $save['company_user_id'])
                ->where('room_id', $save['room_id'])
                ->where('entertime', '<=', $save['outtime'])
                ->where('outtime', 0)
                ->order('entertime')
                ->limit(1)
                ->update(['outtime' => $save['outtime']]);
        }

        $job->delete();
        return true;
    }
}
