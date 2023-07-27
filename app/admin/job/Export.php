<?php

/**
 * 文件异步导出
 */

namespace app\admin\job;

use app\admin\model\FrontUser;
use app\admin\model\Room;
use app\admin\model\RoomTemplate;
use app\common\facade\Excel;
use app\common\http\WebApi;
use app\gateway\model\UserAccount;
use think\facade\{Filesystem, Db, Lang};
use think\queue\Job;

class Export
{

    /**
     * 消费
     * @param Job $job
     * @param
     */
    public function fire(Job $job, $queParams)
    {
        Lang::load(app()->getBasePath() . 'admin/lang/' . $queParams['lang'] . '.php');
        /** @var \think\model\Collection */
        $data = Room::withTrashed()
            ->withSearch(['export'], $queParams)
            ->append(['create_by', 'teacher', 'file_name', 'class_time', 'actual_time'])
            ->hidden(['room_id', 'starttime', 'endtime', 'resources', 'room_id', 'createBy', 'actual_start_time', 'actual_end_time', 'teacher_id'])
            ->select();

        if (!$data->isEmpty()) {
            $urls = array_map(function ($serial) use ($queParams) {
                return sprintf('WebAPI/getroomfile?error=0&serial=%s&key=%s', $serial, $queParams['key']);
            }, $data->where('delete_time', '=', 0)->column('live_serial', 'live_serial'));

            $fileNameMap = [];
            foreach (array_chunk($urls, 100, true) as $urls) {
                $result = WebApi::httpGet($urls, ['verify' => false]);
                foreach ($result as &$value) {
                    $value = !empty($value['roomfile']) ? implode(',', array_column($value['roomfile'], 'filename')) : null;
                }

                $fileNameMap += $result;
            }


            $createBy = UserAccount::whereIn('id', $data->column('createBy'))->column('username', 'id');
            $teacherIdList = FrontUser::whereIn('id', $data->column('teacher_id'))->column('nickname', 'id');

            $data->withAttr('class_time', function ($value, $data) {
                return date('Y-m-d H:i', $data['starttime']) . '~' . date('H:i', $data['endtime']);
            })->withAttr('actual_time', function ($value, $data) {
                return $data['actual_end_time'] && $data['actual_start_time'] ? date('Y-m-d H:i:s', $data['actual_start_time']) . '~' . date('H:i:s', $data['actual_end_time']) : '';
            })->withAttr('delete_time', function ($value, $data) {
                return $value ? lang('delete') : ($data['actual_end_time'] && $data['actual_start_time'] ? lang('Already in class') : lang('waiting_for_class'));
            })->withAttr('roomtype', function ($value) {
                if ($value == Room::ROOMTYPE_LARGEROOM) {
                    return lang('Large Live Class');
                } elseif ($value == Room::ROOMTYPE_ONEROOM) {
                    return lang('Small Interactive Class');
                } else {
                    return lang('one to many');
                }
            })->withAttr('create_by', function ($value, $data) use ($createBy) {
                return $createBy[$data['createBy']] ?? '';
            })->withAttr('video_ratio', function ($value) {
                return RoomTemplate::VIDEO_TYPE_LIST[$value] ?? '';
            })->withAttr('teacher', function ($value, $data) use ($teacherIdList) {
                return $teacherIdList[$data['teacher_id']] ?? '';
            })->withAttr('file_name', function ($value, $data) use ($fileNameMap) {
                return $fileNameMap[$data['live_serial']] ?? '';
            });
        }

        $export = array_map(function ($value) {
            $temp = [];
            $temp['nickname'] = $value['nickname'];
            $temp['domain_account'] = $value['domain_account'];
            $temp['account'] = $value['account'];
            $temp['class_time'] = $value['class_time'];
            $temp['create_by'] = $value['create_by'];
            $temp['actual_time'] = $value['actual_time'];
            $temp['delete_time'] = $value['delete_time'];
            $temp['roomtype'] = $value['roomtype'];
            $temp['video_ratio'] = $value['video_ratio'];
            $temp['typename'] = $value['typename'];
            $temp['teacher'] = $value['teacher'];
            $temp['file_name'] = $value['file_name'];
            return $temp;
        }, $data->toArray());


        $header = [
            lang('student_name'), lang('domain_account'), lang('mobile'), lang('Schedule time'), lang('op_name'),
            lang('Class time'), lang('lesson_status'), lang('room_type'), lang('video_ratio'), lang('group_name'), lang('teacher'),
            lang('Courseware name')
        ];

        $obj = Excel::export($export, $header, date('Y-m-d'));

        $path = 'excel/' . $queParams['fileName'] . '.xls';
        Filesystem::write($path, $obj->getContent());

        $save = [];
        $save['size'] = Filesystem::getSize($path);
        $save['path'] = $path;

        Db::table('saas_file_export')->where('id', $queParams['fileId'])->update($save);
        $job->delete();
    }
}
