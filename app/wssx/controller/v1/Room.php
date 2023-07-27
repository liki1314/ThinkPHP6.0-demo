<?php

declare(strict_types=1);

namespace app\wssx\controller\v1;

use app\common\http\CommonAPI;
use app\common\http\WebApi;
use app\common\model\Company;
use app\wssx\controller\Base;
use app\wssx\model\Room as RoomModel;
use app\wssx\model\{FrontUser, RoomUser, UserAccount, UserRecord};
use think\exception\ValidateException;
use think\facade\Route;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use think\facade\Cache;
use think\paginator\driver\Bootstrap;
use think\Response;
use think\facade\Db;

class Room extends Base
{


    /**
     * 修改房间名称
     * @return \think\response\Json
     */
    public function update()
    {
        $rule = [
            'roomname' => ['require', 'max:30']
        ];

        $message = [
            'roomname.require' => 'roomname_empty',
        ];

        $this->validate($this->param, $rule, $message);

        Db::startTrans();
        try {
            $model = RoomModel::where('live_serial', $this->param['serial'])
                ->findOrFail();
            $model->save(['roomname' => $this->param['roomname']]);
            //此接口只能通过post传参方式传递企业authkey
            $key = Company::cache(true)->find($model['company_id'])['authkey'];
            WebApi::httpPost('WebAPI/roommodify', ['key' => $key, 'serial' => $this->param['serial'], 'roomname' => $this->param['roomname']]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->success();
    }

    /**
     * 进入房间
     */
    public function read($serial)
    {
        $roomModel = RoomModel::where('live_serial', $serial)->findOrFail();

        $apiParams = [
            'serial' => $serial,
            'username' => $this->request->user['username'],
            'usertype' => FrontUser::IDENTITY_MAP[$this->param['identity']],
            'pid' => $this->request->user['userid'] ?: $this->request->user['user_account_id'],
        ];

        $uri  = 'WebAPI/getroom';

        try {
            $room = WebApi::httpPost($uri, $apiParams);
        } catch (\Throwable $e) {
            if ($e->getCode() == '4001' && $roomModel['create_by'] != $this->request->user['user_account_id']) {
                $msg = '房间被冻结，无法参与直播～';
                throw new ValidateException($msg);
            }
            throw $e;
        }


        $current = [];

        foreach ($room['enterurl'] as $t) {
            if ($t['usertype'] == FrontUser::IDENTITY_MAP[$this->param['identity']]) {
                $current = $t;
                break;
            }
        }

        $res = [
            'pwd' => $this->param['identity'] == FrontUser::STUDENT_TYPE ? $room['confuserpwd'] : $room['chairmanpwd'],
            'serial' => $this->param['serial'],
            'userid' => UserAccount::where('id', $this->request->user['user_account_id'])->value('live_userid'),
            'enter_url' => $current['url'],
        ];

        if ($this->param['identity'] == FrontUser::STUDENT_TYPE) {
            $room_id = Db::table('saas_room')->where('live_serial', $this->param['serial'])->value('id');
            Db::table('saas_room_user')
                ->duplicate(['room_id' => $room_id, 'front_user_id' => $this->request->user['user_account_id']])
                ->insert(['room_id' => $room_id, 'front_user_id' => $this->request->user['user_account_id']]);
        }

        return $this->success($res);
    }

    /**
     * 房间录制件
     */
    public function record()
    {
        $model = RoomModel::where('live_serial', $this->param['serial'])
            ->where('create_by', $this->request->user['user_account_id'])
            ->findOrEmpty();

        if ($model->isEmpty()) {
            return $this->success($this->searchList(UserRecord::class));
        } else {
            $apiParams = [
                'thirdroomids' => [$model['custom_id']],
                'page' => $this->page,
                'recordtype' => [0], //只要常规录制件
            ];

            $apiRes = WebApi::httpPost('/WebAPI/batchGetRecord?error=0', $apiParams);

            $total = $apiRes['total'] ?? 0;
            $data = [];
            $temp = $apiRes['recordlist'] ?? [];

            foreach ($temp as $v) {
                $endtime = $v['starttime'] + ($v['duration'] ? $v['duration'] / 1000 : 0);
                $date = date('H:i', (int)$v['starttime']) . '~' . date('H:i', (int)$endtime);
                $data[] = [
                    'serial' => $v['serial'],
                    'title' => date('Y-m-d', (int)$v['starttime']) . ' ' . $date,
                    'url' => $v['https_playpath'],
                    'recordtitle' => $v['recordtitle'],
                    'starttime' => date('Y-m-d H:i:s', (int)$v['starttime']),
                    'endtime' => date('Y-m-d H:i:s', (int)$endtime),
                    'sort' => (int)$v['starttime']
                ];
            }

            //排序
            $sortList = array_column($data, 'sort');
            array_multisort($sortList, SORT_DESC, $data);
            $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
            return $this->success($res);
        }

    }

    /**
     * 分享(只有教师才能分享)
     */
    public function share()
    {
        $model = RoomModel::where('live_serial', $this->param['serial'])->findOrFail();
        $serial = $this->param['serial'];
        $res = [
            'username' => $this->request->user['username'],
            'roomname' => $model['roomname'],
            'serial' => $this->param['serial'],
            'url' => (string)Route::buildUrl("enterRoom/$serial", [/* 'token' => JWTAuth::token()->get(),  */'clienttype' => $this->param['clienttype'] ?? 0])
                ->domain(true)->suffix('')
        ];

        return $this->success($res);
    }

    /**
     * 执行进入教室
     * @param $serial
     */
    public function enterRoom($serial)
    {
        if ($this->request->param('qrcode') == 1 && Cache::get('qrcode:' . $serial) === null) {
            // throw new Exception('二维码已失效');
            return redirect((string)Route::buildUrl('/static/wssx/error.html')->domain(config('app.host.webapi'))->suffix(''));
        }

        RoomModel::where('live_serial', $serial)->findOrFail();

        try {
            $checkRes = WebApi::httpPost('/WebAPI/getonlineuser', ['serial' => $serial]);
            $onLine = $checkRes['onlineuser'] ?? [];
        } catch (\Throwable $e) {
            $onLine = [];
        }

        $hasTeacher = false;
        $total = 0;
        foreach ($onLine as $v) {
            $total++;
            if ($v['userrole'] == FrontUser::IDENTITY_MAP[FrontUser::TEACHER_TYPE]) {
                $hasTeacher = true;
            }
        }

        if ($hasTeacher && $total >= 7) {
            throw new ValidateException('数据格式有误');
        }

        if (!$hasTeacher && $total >= 6) {
            throw new ValidateException(lang('too_many_people_for_room'));
        }

        $apiRes = WebApi::httpPost(
            'WebAPI/getEnterRoomUrl',
            [
                'serial' => $serial,
                'username' => $serial,
                'usertype' => FrontUser::IDENTITY_MAP[FrontUser::STUDENT_TYPE],
                'ts' => time(),
            ]
        );

        return redirect((string)Route::buildUrl('/static/wssx/index.html', ['url' => $apiRes['enterRoomUrl']])->domain(config('app.host.webapi'))->suffix(''));
    }

    public function qrcode($serial)
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data((string)Route::buildUrl("enterRoom/$serial")->domain(true)->suffix(''))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->logoPath($this->request->user['avatar'] ?: './image/man_teacher.png')
            ->logoResizeToWidth(50)
            ->labelText('')
            ->labelFont(new NotoSans(20))
            ->labelAlignment(new LabelAlignmentCenter())
            ->build();

        return Response::create($result->getString())->contentType('image/jpg');
    }

    public function qrcodeContent($serial)
    {
        // RoomModel::where('live_serial', $serial)->findOrFail();
        Cache::set('qrcode:' . $serial, 1, config('app.qrcode_period'));
        $period = time() + config('app.qrcode_period');
        return $this->success([
            'url' => (string)Route::buildUrl("enterRoom/$serial", ['qrcode' => 1, 't' => $period])->domain(true)->suffix(''),
            'expire' => date('Y-m-d H:i', $period),
        ]);
    }

    /**
     * 我的参与的
     *
     */
    public function index()
    {
        return $this->success($this->searchList(RoomUser::class));
    }

    /**
     * 兑换课件
     */
    public function convert()
    {
        $key = '';
        $account_id = $company_id = 0;
        $rule = [
            'mobile|' . lang('mobile') => ['require', 'mobile', function ($value) use (&$key, &$account_id, &$company_id) {
                $userModel = UserAccount::cache(true)->where('account', '86' . $value)
                    ->findOrEmpty();
                if ($userModel->isEmpty()) {
                    return lang('account_not_exists');
                }
                $company = Company::cache(true)
                    ->where('createuserid', $userModel['id'])
                    ->where('type', 6)
                    ->findOrEmpty();

                if ($company->isEmpty()) {
                    return lang('account_not_exists');
                }

                $key = $company['authkey'];
                $company_id = $company['id'];
                $account_id = $userModel['id'];
                return true;

            }],
            'code|' . lang('code') => ['require', 'length:6', function ($value) {
                $info = Db::table('saas_coupon')->whereFieldRaw('binary code', '=', $value)->find();
                if (!$info) {
                    return lang('code_not_exists');
                }

                if ($info['account']) {
                    return lang('code_used');
                }
                return true;
            }],
        ];

        $message = [
            'mobile.require' => 'mobile_empty',
            'code.require' => 'code_empty',
        ];

        $this->validate($this->param, $rule, $message);

        $serial = Db::table('saas_room')
            ->where('create_by', $account_id)
            ->where('course_id', 0)
            ->where('company_id', $company_id)
            ->value('live_serial');

        $apiRes = WebApi::httpPost(
            'WebAPI/getEnterRoomUrl',
            [
                'key' => $key,
                'serial' => $serial,
                'username' => $serial,
                'usertype' => FrontUser::IDENTITY_MAP[FrontUser::TEACHER_TYPE],
                'ts' => time(),
            ]
        );

        Db::transaction(function () use ($key) {
            Db::table('saas_coupon')
                ->where('code', $this->param['code'])
                ->where('account', '')
                ->update(['update_time' => time(), 'account' => '86' . $this->param['mobile']]);

            CommonAPI::httpPost('CommonAPI/addCoursewaresync', [
                'key' => $key,
            ]);
        });

        $url = (string)Route::buildUrl('/static/wssx/index.html', ['url' => $apiRes['enterRoomUrl']])->domain(config('app.host.webapi'))->suffix('');
        return $this->success(['url' => $url]);
    }
}
