<?php

namespace app\common\live\driver;

use app\common\exception\LiveException;
use app\common\facade\Live as FacadeLive;
use app\common\http\WebApi;
use app\common\job\Live;
use app\common\live\Driver;
use app\Request;
use think\facade\Queue;
use think\facade\Db;
use app\common\model\Company;
use think\facade\Filesystem;

/**
 *
 */
class Test extends Driver
{
    const SUPER_ADMIN = 11;

    /**
     * 创建企业
     * @param int $id 企业id
     * @param int $company_id 创建者企业id
     * @param int $source 4 6
     */
    public function createCompany($id, $company_id, $source = 4)
    {
        // 创建主企业
        if (isset($id['id']) && ($id = $id['id']) || empty($company_id)) {
            $key = config('app.master_company_authkey');
        }

        $model = Company::alias('a')
            ->join('company_user b', 'b.company_id=a.id and b.sys_role=' . self::SUPER_ADMIN)
            ->join('user_account c', 'b.user_account_id=c.id')
            ->where('a.id', $id)
            ->field('a.companyname,c.account,c.id,a.parentid,c.username,a.id,b.user_account_id,c.live_userid,c.locale')
            ->find();

        $result = WebApi::httpPost(
            'WebAPI/companycreate',
            [
                'key' => $key ?? $this->getAuthKeyByCompanyId($company_id),
                'source' => $source,
                'companyname' => $model['companyname'],
                'account' => $model['account'],
                'username' => $model['username'],
                'userpassword' => $model['account'],
                'auth' => [$this->config['auth']['username'], $this->config['auth']['password']],
                'userid' => $model['live_userid'],
                'mobile' => ltrim($model['account'], config("countrycode.abbreviation_code.{$model['locale']}")),
                'countryCode' => config("countrycode.abbreviation_code.{$model['locale']}"),
            ]
        );
        //回写企业authkey、域名和用户userid
        $model->save(['authkey' => $result['key']]);

        return $result;
    }

    /**
     * 创建房间
     * @param int|array $id 课节id
     * @param int $company_id 企业id
     */
    public function createRoom($id, $company_id, $roomtype = null)
    {
        $config = [];
        $logo = null;

        $model = Db::name('room')->alias('a');
        if (isset($roomtype)) {
            $model->leftJoin('room_template c', "c.company_id=0 and c.type=$roomtype and c.delete_time=0 and JSON_EXTRACT(extra_info, '$.is_video')='0'");
        } else {
            $model->leftJoin('course b', 'a.course_id=b.id')->leftJoin('room_template c', 'b.room_template_id=c.id');
        }

        $roomParamList = $model->json(['extra_info'])
            ->whereIn('a.id', $id)
            ->field('a.id,a.roomname,a.roomtype,a.starttime,a.endtime,a.custom_id,c.extra_info,c.passwordrequired,c.layout_id,c.video_ratio,c.theme_id,c.logo,c.support_connection')
            ->select()
            ->map(function ($room) use (&$config, &$logo) {
                list($chairmanpwd, $assistantpwd, $patrolpwd, $confuserpwd) = $this->getFourPwds();
                if (empty($room['passwordrequired'])) {
                    $confuserpwd = '';
                }

                if (empty($config)) {
                    $config = [
                        'chk_answering_machine' => $room['extra_info']['answering_machine'] ?? 0,
                        'chk_turntable' => $room['extra_info']['turntable'] ?? 0,
                        'chk_timer' => $room['extra_info']['timer'] ?? 0,
                        'answering_machine' => $room['extra_info']['first_answering_machine'] ?? 0,
                        'chk_triazolam' => $room['extra_info']['triazolam'] ?? 0,
                        'AllowStudentCloseAudio' => $room['extra_info']['student_close_a'] ?? 0,
                        'AllowStudentCloseVideo' => $room['extra_info']['student_close_v'] ?? 0,
                        'chk_assistantopenav' => $room['extra_info']['assistantopenav'] ?? 0,
                        'HiddenKicking' => $room['extra_info']['hidden_kicking'] ?? 0,
                        'AVGuide' => $room['extra_info']['av_guide'] ?? 0,
                        'DeviceCheckContinue' => $room['extra_info']['device_check_continue'] ?? 0,
                        'CutPicture' => $room['extra_info']['cut_picture'] ?? 0,
                        'Signin' => $room['extra_info']['sign_in'] ?? 0,
                    ];
                }

                if (!isset($logo)) {
                    $logo = $room['logo'];
                }

                return [
                    'roomname' => $room['roomname'],
                    'roomtype' => $room['roomtype'] == 4 ? 7 : $room['roomtype'],//大直播类型改为7
                    'starttime' => $room['starttime'],
                    'endtime' => $room['endtime'],
                    'thirdroomid' => $room['custom_id'],
                    'chairmanpwd' => $chairmanpwd,
                    'assistantpwd' => $assistantpwd,
                    'patrolpwd' => $patrolpwd,
                    'passwordrequired' => $room['passwordrequired'],
                    'confuserpwd' => $confuserpwd,
                    'videotype' => $room['video_ratio'],
                    'autoopenaudio' => $room['extra_info']['auto_open_audio'] ?? 0,
                    'autoopenvideo' => $room['extra_info']['auto_open_video'] ?? 0,
                    'ismp4record' => $room['extra_info']['is_video'] ?? 0,
                    'showYourself' => $room['extra_info']['only_teacher_and_self'] ?? 0,
                    'roomlayout' => $room['layout_id'],
                    'roomthemeid' => $room['theme_id'],
                    'support_connection' => $room['support_connection'],
                ];
            })
            ->toArray();

        try {
            $base64 = $logo ? 'data:image/jpg/png/gif;base64,' . chunk_split(base64_encode(Filesystem::read($logo))) : '';
        } catch (\League\Flysystem\FileNotFoundException $e) {
            $base64 = '';
        }

        $result = WebApi::httpJson(
            'WebAPI/batchCreateRoom',
            [
                'key' => $this->getAuthKeyByCompanyId($company_id),
                'roomParamList' => $roomParamList,
                'config' => $config,
                'roomLogo' => $base64,
            ]
        );

        if (empty($result) || !is_array($result)) {
            throw new LiveException('服务异常');
        }

        Db::transaction(function () use ($result) {
            foreach ($result as $value) {
                if ($value['result'] === 0) {
                    Db::name('room')->where('custom_id', $value['data']['thirdRoomId'])->update(['live_serial' => $value['data']['serial']]);
                } else {
                    throw new LiveException(lang($this->config['error_code'][$value['result']] ?? $value['msg'] ?? '服务异常'));
                }
            }
        });

        return $result;
    }

    /**
     * 修改房间
     * @param mixed $models 课节model
     * @param int $company_id 企业id
     */
    public function updateRoom($models, $company_id)
    {
        $result = WebApi::httpJson(
            'WebAPI/batchRoomModify',
            [
                'roomParamList' => array_map(function ($model) {
                    return [
                        'roomname' => $model['roomname'],
                        'starttime' => $model['starttime'],
                        'endtime' => $model['endtime'],
                        'thirdroomid' => $model['custom_id'],
                    ];
                }, (array) $models),
                'key' => $this->getAuthKeyByCompanyId($company_id)
            ]
        );

        if (!is_array($result)) {
            throw new LiveException('服务异常');
        }

        foreach ($result as $key => $value) {
            if ($value['result'] !== 0) {
                throw new LiveException(lang($this->config['error_code'][$value['result']] ?? '服务异常'));
            }
        }
    }

    /**
     * 删除房间
     * @param int|array $id 课节id
     * @param int $company_id 企业id
     */
    public function deleteRoom($id, $company_id)
    {
        Db::name('room')
            ->whereIn('id', $id)
            ->field('custom_id')
            ->select()
            ->each(function ($model) use ($company_id) {
                WebApi::httpPost(
                    'WebAPI/roomdelete',
                    [
                        'key' => $this->getAuthKeyByCompanyId($company_id),
                        'thirdroomid' => $model['custom_id']
                    ]
                );
            });
    }

    /**
     * 房间关联课件
     * @param array $models 课节
     * @param int $company_id 企业id
     */
    public function roomBindFile($models, $company_id)
    {
        if (!isset($models[0])) {
            $models = [$models];
        }

        $authkey = $this->getAuthKeyByCompanyId($company_id);

        foreach ($models as $key => $model) {
            if (!empty($model['resources'])) {
                WebApi::httpPost(
                    'WebAPI/roombindfile',
                    [
                        'key' => $authkey,
                        'thirdroomid' => $model['custom_id'],
                        'fileidarr' => array_column(array_filter(
                            $model['resources'],
                            function ($value) {
                                return !isset($value['type']) || $value['type'] == 2;
                            }
                        ), 'id'),
                        'catalogidarr' => array_column(array_filter(
                            $model['resources'],
                            function ($value) {
                                return isset($value['type']) && $value['type'] == 1;
                            }
                        ), 'id'),
                    ]
                );
            }
        }
    }

    /**
     * 取消房间关联资源
     * @param array $model 课节
     * @param int $company_id 企业id
     */
    public function roomUnbindFile($model, $company_id, $request)
    {
        $result = WebApi::httpPost(
            'WebAPI/getroomfile?error=0',
            ['thirdroomid' => $model['custom_id'], 'key' => $this->getAuthKeyByCompanyId($company_id)]
        );

        if (isset($result['roomfile'])) {
            $unbindFiles = array_diff(
                array_column($result['roomfile'], 'fileid'),
                array_map(
                    function ($item) {
                        return $item['id'];
                    },
                    array_filter(
                        $request['resources'] ?? [],
                        function ($value) {
                            return !isset($value['type']) || $value['type'] == 2;
                        }
                    )
                )
            );

            if (!empty($unbindFiles)) {
                WebApi::httpPost('WebAPI/roomdeletefile', ['thirdroomid' => $model['custom_id'], 'key' => $this->getAuthKeyByCompanyId($company_id), 'fileidarr' => $unbindFiles]);
            }
        }
    }

    // 生成随机不重复4组密码
    private function getFourPwds()
    {
        $pwds = [];
        for ($i = 0; $i < 4; $i++) {
            $pwds[] = mt_rand(1000, 9999);
        }

        $result = array_unique($pwds);
        if (count($result) != 4) {
            $this->getFourPwds();
        } else {
            return $result;
        }
    }

    protected function getAuthKeyByCompanyId($company_id)
    {
        return Company::cache(true)->find($company_id)['authkey'];
    }

    public function send($server, $id, Request $request)
    {
        Queue::push(
            Live::class,
            [
                'func' => [FacadeLive::class, $server],
                'params' => [
                    'id' => $id,
                    'company_id' => $request->user['company_id'] ?? null,
                    'request' => $request->post()
                ]
            ],
            config('queue.queue.live')
        );
    }
}
