<?php

declare(strict_types=1);

namespace app\wssx\model;

use app\common\facade\Live;
use app\common\model\Company;
use think\Collection;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class UserAccount extends Base
{
    protected $json = ['extend_info'];

    protected $deleteTime = false;

    public static function homepage($user): Room
    {
        return Db::transaction(function () use ($user) {
            //初始化企业
            $company = Company::lock(true)
                ->where('createuserid', $user['user_account_id'])
                ->where('type', 6)
                ->findOrEmpty();
            if ($company->isEmpty()) {
                $company->save([
                    'companyname' => $user['account'] . '的机构',
                    'companyfullname' => $user['account'] . '的机构',
                    'createuserid' => $user['user_account_id'],
                    'type' => 6,
                    'endtime' => '2999-01-01 00:00:00',
                ]);
                Db::name('company_user')
                    ->insert([
                        'user_account_id' => $user['user_account_id'],
                        'username' => $user['username'],
                        'company_id' => $company->getKey(),
                        'create_time' => time(),
                        'sys_role' => 11
                    ]);
            }

            //初始化学生老师身份
            Db::name('front_user')
                ->duplicate(['delete_time' => 0, 'ucstate' => 0])
                ->insertAll([
                    ['company_id' => $company->getKey(), 'user_account_id' => $user['user_account_id'], 'userroleid' => 7, 'create_time' => time(), 'username' => $user['username'], 'nickname' => $user['user_account_id']],
                    ['company_id' => $company->getKey(), 'user_account_id' => $user['user_account_id'], 'userroleid' => 8, 'create_time' => time(), 'username' => $user['username'], 'nickname' => $user['user_account_id']],
                ]);

            //初始化房间
            $room = Room::lock(true)
                ->where('create_by', $user['user_account_id'])
                ->where('course_id', 0)
                ->where('company_id', $company->getKey())
                ->findOrEmpty();
            if ($room->isEmpty()) {
                $room->save(['company_id' => $company->getKey()]);
            }

            if (empty($company['authkey'])) {
                Live::createCompany($company->getKey(), 0, 6);
            }

            if (empty($room['live_serial'])) {
                $collection = new Collection(Live::createRoom($room->getKey(), $company->getKey(), 3));
                $room['live_serial'] = $collection->where('data.thirdRoomId', $room['custom_id'])->first()['data']['serial'] ?? null;
            }

            return $room;
        });
    }
}
