<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use app\common\http\WebApi;
use app\home\model\saas\Room;
use app\home\model\saas\LessonRemak;
use app\home\model\saas\FrontUser;
use think\facade\Db;
use app\home\model\saas\RemarkItem;

class Lesson extends \app\home\controller\Base
{
    const RECORD_TYPE_NORMAL = 0;  //常规录制

    public function index()
    {
        $search = array_intersect(['month', 'week', 'day'], array_keys($this->param));
        array_push($search, 'user');
        return $this->success(Room::withSearch($search, $this->param)->select());
    }

    public function beforeAndAfterToday()
    {
        $day = [date('Y-m-d')];
        $before = Room::withSearch(['user'], $this->param)
            ->whereTime('starttime', '<', date('Y-m-d'))
            ->order('starttime', 'desc')
            ->value('starttime');
        $after = Room::withSearch(['user'], $this->param)
            ->whereTime('starttime', '>', date('Y-m-d 23:59:59'))
            ->order('starttime', 'asc')
            ->value('starttime');

        if (isset($before)) {
            array_unshift($day, date('Y-m-d', $before));
        }

        if (isset($after)) {
            array_push($day, date('Y-m-d', $after));
        }

        return $this->success($day);
    }

    /**
     * 课堂点评
     */
    public function remark()
    {
        $rule = [
            'students' => [
                'require',
                'array',
                'each' => ['integer'],
                function ($value) {
                    return FrontUser::withSearch(['student'])
                        ->alias('a')
                        ->join('room_user b', 'b.front_user_id=a.id')
                        ->whereIn('a.id', $value)
                        ->where('b.room_id', $this->param['serial'])
                        ->count() === count($value) ? true : lang('students_id_error');
                },
            ],
            'remark' => [
                'require',
                'array',
                'each' => [
                    'name' => ['require'],
                    'score' => ['require', 'integer', 'between:0,5'],
                ],
            ],
            'serial' => ['integer', 'exist:' . Room::class],
        ];
        $message = [
            'students.require' => lang('students_is_empty'),
            'remark.require' => lang('remark_is_empty'),
        ];

        $this->validate($this->param, $rule, $message);

        LessonRemak::duplicate(['remark_content' => Db::raw('VALUES(remark_content)')])
            ->insertAll(array_map(function ($value) {
                return ['student_id' => $value, 'room_id' => $this->param['serial'], 'remark_content' => $this->param['remark']];
            }, $this->param['students']));

        return $this->success();
    }

    /**
     * 点评详情
     * @param $id
     * @param $student_id
     */
    public function remarkDetail($serial, $student_id)
    {
        $rmModel = LessonRemak::where('room_id', $serial)
            ->where('student_id', $student_id)
            ->findOrEmpty();
        $data = null;
        if ($rmModel->isEmpty()) {
            $temp = Room::find($serial);
            $filter = $temp ? [0, $temp['companyid']] : [0];
            $model = RemarkItem::whereIn('company_id', $filter)
                ->order('company_id desc')
                ->field('content')
                ->find();
            if ($model) {
                $data = array_map(function ($value) {
                    return ['name' => $value, 'score' => 0];
                }, $model['content']);
            }
        } else {
            $data = $rmModel['remark_content'];
        }

        return $this->success($data);
    }

    /**
     * 课堂学生
     */
    public function student()
    {
        $data = FrontUser::withSearch(['serial'], $this->param)->select();
        return $this->success($data);
    }

    /**
     * 学生课堂报告
     * @param $serial
     */
    public function report($serial)
    {
        $data = (new Room)->getReportByRoomId($serial);
        return $this->success($data);
    }

    /**
     * 教室录制件
     *
     * @param int $lesson_id 课节id
     * @return void
     */
    public function roomRecords($lesson_id)
    {
        $model = Room::findOrFail($lesson_id);
        $records = WebApi::httpPost('WebAPI/batchgetrecordlist?error=0', ['serial' => $model['live_serial']]);

        $result = [];
        if (!empty($records['recordlist'])) {

            foreach ($records['recordlist'] as $key => $value) {

                if (!empty($value['playpath_mp4'])) {
                    $result[intval($value['recordtitle']) - 20]['title'] = $value['recordtitle'];
                    $result[intval($value['recordtitle']) - 20]['name'] = $value['recordtitle'];
                    $result[intval($value['recordtitle']) - 20]['url'] = $value['playpath_mp4'];
                    $result[intval($value['recordtitle']) - 20]['mp4url'] = $value['playpath_mp4'];
                    $result[intval($value['recordtitle']) - 20]['room_id'] = $model['live_serial'];
                } else {
                    $result[intval($value['recordtitle'])]['title'] = $value['recordtitle'];
                    $result[intval($value['recordtitle'])]['name'] = $value['recordtitle'];
                    $result[intval($value['recordtitle'])]['url'] = $value['playpath'];
                    $result[intval($value['recordtitle'])]['room_id'] = $model['live_serial'];
                }
            }
        }

        return $this->success(array_values($result));
    }

    /**
     * 教室详情
     *
     * @param int $lesson_id 课节id
     */
    public function roomDetail($lesson_id)
    {
        $model = Room::findOrFail($lesson_id);
        return $this->getRoomInfo($model);
    }

    /**
     * 教室课件
     *
     * @param int $lesson_id 课节id
     */
    public function roomFiles($lesson_id)
    {
        $model = Room::findOrFail($lesson_id);
        $showtip = $this->request->user['current_identity'] == FrontUser::TEACHER_TYPE ? 1 : 0;
        $files = WebApi::httpPost('WebAPI/getroomfile?error=0', ['serial' => $model['live_serial'], 'showtip' => $showtip]);

        $result = array_values(
            array_map(
                function ($value) {
                    return ['filetype'=>$value['filetype'],'download_url'=>$value['download_url'],'filename' => $value['filename'], 'preview_url' => $value['path_arr'][0] ?? ''];
                },
                array_filter($files['roomfile'] ?? [], function ($item) {
                    return $item['status'] == 1;
                })
            )
        );

        return $this->success($result);
    }

    /**
     * 根据教室号获取教室信息
     * @param $serial
     */
    public function roomInfo($serial)
    {
        $model = Room::where('live_serial', $serial)->findOrFail();
        return $this->getRoomInfo($model);
    }


    /**
     * @param $model
     *
     */
    private function getRoomInfo($model)
    {
        $username = FrontUser::where('user_account_id', $this->request->user['user_account_id'])
            ->where('userroleid', $this->request->user['current_identity'])
            ->where('company_id', $model['company_id'])
            ->value('nickname');

        $room = WebApi::httpPost(
            'WebAPI/getroom',
            [
                'serial' => $model['live_serial'],
                'username' => $username,
                'usertype' => FrontUser::IDENTITY_MAP[$this->request->user['current_identity']],
                'pid' => $this->request->user['userid'] ?: $this->request->user['user_account_id'],
            ]
        );

        return $this->success(
            [
                'pwd' => $this->request->user['current_identity'] == FrontUser::STUDENT_TYPE ? $room['confuserpwd'] : $room['chairmanpwd'],
                'enter_room_url' => $room['entryurl'] ?? '',
                'room_id' => $model['live_serial'],
                'state' => $model['state'],
            ]
        );
    }

}
