<?php

declare(strict_types=1);

namespace app\webapi\controller;

use app\common\service\room\RoomStudentsMsg;
use app\webapi\model\MicroCourse;
use think\exception\ValidateException;
use think\facade\{Db, Queue};
use app\webapi\job\Access;
use app\gateway\model\UserAccount;
use app\common\model\Company;
use app\common\http\CommonAPI;
use app\webapi\job\Gift;

class Room extends Base
{
    //上下课
    public function startOrEnd()
    {
        $data = [];
        $room = Db::name('room')
            ->alias('a')
            ->join('company b', 'a.company_id=b.id')
            ->where('custom_id', $this->param['thirdroomid'])
            ->field('a.id,a.company_id,a.endtime,a.actual_start_time,a.create_by,b.type,b.companystate,b.authkey')
            ->findOrFail();

        try {
            if ($this->param['classstate'] == 0) { //上课
                $data['actual_start_time'] = $this->param['time'];
                $data['actual_end_time'] = 0;
                RoomStudentsMsg::openRoom($room['id'], $this->param['time']);
            } else { //下课
                $data['actual_end_time'] = $this->param['time'];
                RoomStudentsMsg::closeRoom($room['company_id'], $room['id'], $room['endtime']);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }


        Db::startTrans();

        try {
            Db::name('room')->where('id', $room['id'])->update($data);
            $this->saveTeacherTime($this->param);

            // 微思视讯教室第一次上课激活会员试用期
            if ($this->param['classstate'] == 0 && empty($room['actual_start_time']) && $room['type'] == 6) {
                $user = UserAccount::cache(true)->findOrFail($room['create_by']);
                $extend_info = $user['extend_info'];

                if (!isset($extend_info['member_expire'])) {
                    $extend_info['member_expire'] = strtotime('+' . config('app.member_period') . ' days');
                    $extend_info['member_name'] = '试用会员卡';
                    $user->extend_info = $extend_info;
                    $user->save();

                    //更新企业状态
                    Company::update(['id' => $room['company_id'], 'companystate' => 1, 'endtime' => date('Y-m-d H:i:s', $extend_info['member_expire'])]);
                    if ($room['companystate'] != 1) {
                        CommonAPI::httpPost('CommonAPI/updateCompanyFields', [
                            'key' => $room['authkey'],
                            'companystate' => 1,
                        ]);
                    }
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        return $this->success();
    }

    //进出教室
    public function accessLog()
    {
        $rule = [
            'role' => ['require', 'in' => '0,1,2,4,27,98'],
            'userid' => ['require', 'number'],
            'thirdroomid' => ['require'],
            'timestamp' => ['integer'],
            'status' => ['require', 'in:0,1'],
        ];

        $message = [
            'userid.require' => 'live_userid_error',
            'role.require' => 'live_userroleid_error',
        ];

        $this->validate($this->param, $rule, $message);

        $data = [
            'live_userid' => $this->param['userid'],
            'thirdroomid' => $this->param['thirdroomid'],
            'live_userroleid' => $this->param['role'],
        ];
        if ($this->param['status'] == 1) {
            $data['entertime'] = $this->param['timestamp'];
        }
        if ($this->param['status'] == 0) {
            $data['outtime'] = $this->param['timestamp'];
        }
        Queue::push(Access::class, $data, 'room_record');

        return $this->success();
    }

    /*
     * 微录课录制件回调
     *
     */
    public function microRecord($custom_id)
    {
        $rule = [
            // 'mp4url' => ['require'],
            'duration' => ['integer'],
            'size' => ['integer'],
            'status' => ['require', 'in' => '0,1,2'],
        ];

        $message = [
            'mp4url.require' => 'mic_mp4url_error',
            'duration.require' => 'mic_duration_error',
            'size.require' => 'mic_size_error',
            'status.require' => 'mic_status_error',
        ];

        $this->validate($this->param, $rule, $message);

        $model = MicroCourse::where('custom_id', $custom_id)->findOrEmpty();

        if ($model->isEmpty()) {
            throw new ValidateException(lang('mic_custom_id_not_exists'));
        }

        $save = [
            'record' => $this->param['mp4url'] ?? '',
            'times' => $this->param['duration'] ?? 0,
            'size' => $this->param['size'] ?? 0,
            'status' => $this->param['status'],
        ];

        $model->save($save);
        return $this->success();
    }


    /**
     * 单独保存教师上下课时间
     * @param $params
     */
    private function saveTeacherTime($params)
    {
        $save = [];
        if ($params['classstate'] == 0) { //上课
            $save['serial'] = $params['serial'];
            $save['starttime'] = $params['time'];
            Db::table('saas_room_timeinfo')->insert($save);
        } else { //下课
            Db::table('saas_room_timeinfo')
                ->where('serial', $params['serial'])
                ->where('starttime', '<', $params['time'])
                ->where('starttime', '<>', 0)
                ->where('endtime', 0)
                ->update(['endtime' => $params['time']]);

            Queue::push(Gift::class, $params['serial'], 'gift');
        }

        return true;
    }


    /**
     * 微思录制件回调
     */
    public function recordback()
    {
        $rule = [
            'playpath' => ['require'],
            'serial' => ['require',],
            'recordid' => ['require'],
            'recordtitle' => ['require'],
            'starttime' => ['require'],
            'endtime' => ['require'],
            'thirdroomid' => ['require'],
        ];

        $this->validate($this->param, $rule);

        $saveRoom['playpath'] = $this->param['playpath'];
        $saveRoom['serial'] = $this->param['serial'];
        $saveRoom['recordtitle'] = $this->param['recordtitle'];
        $saveRoom['starttime'] = intdiv((int)$this->param['starttime'], 1000);
        $saveRoom['endtime'] = intdiv((int)$this->param['endtime'], 1000);

        Db::startTrans();
        try {
            $id =  Db::table('saas_room_record')->insertGetId($saveRoom);

            Db::name('room_access_record')
                ->alias('a')
                ->join('front_user b', 'a.company_user_id=b.id and a.type=1')
                ->join('room c', 'a.room_id=c.id')
                ->where('c.live_serial', $this->param['serial'])
                ->where('a.entertime', '<', $saveRoom['endtime'])
                ->where(function ($query) use ($saveRoom) {
                    $query->where('a.outtime', '>', $saveRoom['starttime'])->whereOr('a.outtime', 0);
                })
                ->field("b.user_account_id,$id room_record_id,b.userroleid")
                ->selectInsert(['user_account_id', 'room_record_id', 'userroleid'], 'saas_user_record');

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return  $this->success();
    }
}
