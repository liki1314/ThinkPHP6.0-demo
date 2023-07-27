<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Course as CourseModel;
use app\admin\model\{Room, MicroCourse};
use app\admin\validate\Course as CourseValidate;
use app\common\http\FileHttp;
use app\common\http\WebApi;
use think\exception\ValidateException;
use think\facade\Db;
use think\paginator\driver\Bootstrap;

class Course extends Base
{
    /**
     * 课程列表
     * @return \think\response\Json
     */
    public function index()
    {
        return $this->success($this->searchList(CourseModel::class));
    }

    /**
     * 课程上课情况统计
     * @return \think\response\Json
     */
    public function statistics()
    {
        $data = [
            'ing_num' => CourseModel::whereBetweenTimeField('first_start_time', 'latest_end_time')->count(),
            'weekend_num' => CourseModel::whereWeek('latest_end_time')->count(),
        ];

        return $this->success($data);
    }

    public function getAllCourse()
    {
        return $this->success(CourseModel::withSearch(['state'], $this->param)->field('id,name')->select());
    }

    /**
     * 添加课程
     * @return \think\response\Json
     */
    public function save()
    {
        $this->validate($this->param, CourseValidate::class);

        $model = CourseModel::create($this->param);

        return $this->success($model);
    }

    /**
     * 获取详情
     * @param $id
     * @return \think\response\Json
     */
    public function read($id)
    {
        $model = CourseModel::withSearch(['detail'])->findOrFail($id);

        return $this->success($model);
    }

    /**
     * 修改课程
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */

    public function update($id)
    {
        $this->validate($this->param, CourseValidate::class);

        $model = CourseModel::findOrFail($id);

        //找到要删除的文件，求出原来和现在差集
        $resources = array_diff($model['resources'] ?? [], $this->param['resources']);

        sort($resources);

        $model->save($this->param);

        (new FileHttp)->deletePrivate($resources);

        return $this->success();
    }

    /**
     * 删除课程
     * @param $id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete($id)
    {
        Db::transaction(function () use ($id) {
            $course = CourseModel::findOrFail($id);
            $course->delete();
            Room::where('course_id', $id)
                ->useSoftDelete('delete_time', time())
                ->delete();
            (new FileHttp)->deletePrivate($course['resources']);
        });

        return $this->success();
    }

    /**
     * 删除课程资源
     * @param $course_id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function deleteResource($course_id)
    {
        $this->validate($this->param, [
            'ids' => ['require', 'array']
        ], [
            'ids.require' => 'params_empty',
        ]);
        $courseInfo = Db::name('course')->json(['resources'])->findOrFail($course_id);
        $resources = array_diff($courseInfo['resources'] ?? [], $this->param['ids']);
        sort($resources);
        Db::name('course')
            ->json(['resources'])
            ->where('id', $course_id)
            ->update(['resources' => $resources]);
        (new FileHttp)->deletePrivate(array_intersect($this->param['ids'], $courseInfo['resources']));

        return $this->success();
    }

    /**
     * 课程的资源
     * @param $course_id
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function courseFileList($course_id)
    {

        $courseInfo = CourseModel::field(['resources'])->findOrFail($course_id);

        $keywords = $this->param['name'] ?? '';

        return $this->success($courseInfo['resources'] ? (new FileHttp)->getIdsToFile($courseInfo['resources'], $keywords) : []);
    }

    /**
     * 课程录制件
     * @param $course_id
     */
    public function record($course_id)
    {
        $apiRes = [];

        $rule = [
            'type' => ['in:0,1']
        ];
        $message = [
            'type' => 'records_type_error',
        ];

        $this->validate($this->param, $rule, $message);

        if (isset($this->param['lesson_name'])) {
            $res = Room::where('course_id', $course_id)
                ->whereLike('roomname', "%" . $this->param['lesson_name'] . "%")
                ->select();
        } else {
            $res = Room::where('course_id', $course_id)->select();
        }

        if (!$res->isEmpty()) {
            $roomIdList = $res->column('custom_id');
            $apiParams = [
                'thirdroomids' => $roomIdList,
                'page' => $this->page,
                'numberPage' => $this->rows
            ];
            if (isset($this->param['type'])) {
                $apiParams['recordtype'][] = $this->param['type']==0 ? 0 : 5;
            }

            $lessonNameMap = Room::whereIn('custom_id', $roomIdList)->column('roomname', 'live_serial');
            $apiRes = WebApi::httpPost('/WebAPI/batchGetRecord?error=0', $apiParams);
        }

        $total = $apiRes['total'] ?? 0;
        $data = [];
        $typeMap = ['0' => lang('routine'), '5' => 'MP4', '6' => lang('micro_recording_course')];
        $temp = $apiRes['recordlist'] ?? [];
        $type = $this->param['type'] ?? 0;
        foreach ($temp as $v) {
            $data[] = [
                'id' => $v['recordid'],
                'name' => $v['recordtitle'],
                'type' => $v['type'],
                'type_name' => $typeMap[$v['type']] ?? lang('other'),
                'lesson_name' => $lessonNameMap[$v['serial']] ?? '',
                'duration' => in_array($v['type'], ['0', '5', '8']) ? timetostr($v['duration'] / 1000) : timetostr($v['duration']),
                'size' => human_filesize($v['size']),
                'start_time' => date('Y-m-d H:i:s', (int)$v['starttime']),
                'url' => $type == 0 ? $v['https_playpath'] : $v['https_playpath_mp4'],
            ];
        }

        $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
        return $this->success($res);
    }

    /**
     * 删除录制件
     * @param $course_id
     */
    public function deleleRecord($course_id=0)
    {
        $rule = [
            'records' => [
                'require',
                'array',
            ]
        ];
        $message = [
            'records.require' => 'records_empty',
            'records.array' => 'records_type_error',
        ];

        $this->validate($this->param, $rule, $message);
        $apiRes = WebApi::httpPost('WebAPI/deleteRecords', ['recordids' => $this->param['records']]);
        return $this->success($apiRes);
    }

    /**
     * 回放录制件列表
     *
     */
    public function recordList()
    {
        $rule = [
            'start_date' => ['require', 'date'],
            'end_date' => ['require', 'date'],
            'teacher_id' => ['integer'],
        ];

        $message = [
            'start_date' => 'start_date_error',
            'end_date' => 'end_date_error',
            'teacher_id' => 'teacher_id_error'
        ];

        $this->validate($this->param, $rule, $message);

        $apiFilter = [];

        $apiFilter['page'] = $this->page;
        $apiFilter['numberPage'] = $this->rows;
        $apiFilter['order_starttime'] = $this->param['order_starttime'] ?? 'asc';
        $finalSort = $apiFilter['order_starttime'] == 'asc' ? SORT_ASC : SORT_DESC;

        if (isset($this->param['lesson_name']) && $this->param['lesson_name']) {
            $tempLive = Room::whereLike('roomname', "%" . $this->param['lesson_name'] . "%")
                ->column('live_serial');
            if ($tempLive) {
                $apiFilter['serials'] = $tempLive;
            }
        }

        if (isset($this->param['teacher_id']) && $this->param['teacher_id']) {
            $tempLive = Room::where('teacher_id', $this->param['teacher_id'])
                ->column('live_serial');
            if ($tempLive) {
                $apiFilter['serials'] = array_merge($apiFilter['serials'] ?? [], $tempLive);
            }
        }

        if (isset($this->param['start_date']) && $this->param['start_date']) {
            $apiFilter['starttime'] = strtotime($this->param['start_date'] . ' 00:00:00');
            $apiFilter['endtime'] = strtotime($this->param['end_date'] . ' 23:59:59');

            if ($apiFilter['endtime'] - $apiFilter['starttime'] > (31 * 24 * 60 * 60)) {
                throw new ValidateException(lang('start_time_error'));
            }
        }

        $apiFilter['recordtype'] = [0, 5]; //只获取常规和MP4

        $apiRes = WebApi::httpPost('/WebAPI/batchGetRecord?error=0', $apiFilter);
        $total = $apiRes['total'] ?? 0;
        $temp = $apiRes['recordlist'] ?? [];

        $data = [];
        $typeMap = ['0' => lang('routine'), '5' => 'MP4', '6' => lang('micro_recording_course')];

        $serialList = array_column($temp, 'serial');
        $teacherMap = $roomMap = [];

        if ($serialList) {
            $teacherMap = Db::table('saas_front_user')
                ->alias('a')
                ->join(['saas_room' => 'b'], 'a.id=b.teacher_id')
                ->whereIn('b.live_serial', $serialList)
                ->column('a.nickname', 'b.live_serial');

            $roomData = Db::table('saas_room')
                ->whereIn('live_serial', $serialList)
                ->select()
                ->toArray();

            foreach ($roomData as $r) {
                $roomMap[$r['live_serial']] = $r;
            }
        }

        $roomModel = new Room;
        foreach ($temp as $v) {
            if (empty($teacherMap[$v['serial']])) {
                continue;
            }
            $data[] = [
                'id' => $v['recordid'],
                'serial' => $v['serial'],
                'teacher_name' => $teacherMap[$v['serial']] ?? '',
                'start_lesson_time' => isset($roomMap[$v['serial']]) ? $roomModel->getStartLessonTimeAttr([], $roomMap[$v['serial']]) : '',
                'name' => $v['recordtitle'],
                'type' => $v['type'],
                'type_name' => $typeMap[$v['type']] ?? lang('other'),
                'lesson_name' => $roomMap[$v['serial']]['roomname'] ?? '',
                'duration' => in_array($v['type'], ['0', '5', '8']) ? timetostr($v['duration'] / 1000) : timetostr($v['duration']),
                'size' => human_filesize($v['size']),
                'start_time' => date('Y-m-d H:i:s', (int)$v['starttime']),
                'url' => $v['type'] == 0 ? $v['https_playpath'] : $v['https_playpath_mp4'],
                'sort' => $roomMap[$v['serial']]['starttime'] ?? 0,
            ];
        }
        //排序
        $sortList = array_column($data, 'sort');
        array_multisort($sortList, $finalSort, $data);
        $res = new Bootstrap($data, $this->rows, $this->page, (int)$total);
        return $this->success($res);
    }

}
