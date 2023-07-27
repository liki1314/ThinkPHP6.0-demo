<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Freetime as ModelFreetime;
use app\admin\model\Room as ModelRoom;
use app\admin\validate\Room;
use think\exception\ValidateException;

class Freetime extends Base
{
    public function teacherList()
    {
        $this->validate(
            $this->param,
            Room::class . '.freetime',
            [
                'week' => lang('Query criteria error'),
                'time' => lang('Query criteria error'),
                'lesson_id' => lang('Query criteria error'),
            ]
        );

        $data = $this->param;
        if (!empty($data['week'])) {
            $time = array_combine(array_column($data['week']['time'], 'week_id'), $data['week']['time']);
            $times = array_map(
                function ($item) use ($time) {
                    return [
                        'starttime' => strtotime($item['start_date'] . ' ' . $time[$item['week_id']]['start_time']),
                        'endtime' => strtotime($item['start_date'] . ' ' . $time[$item['week_id']]['end_time'])
                    ];
                },
                ModelRoom::getTimeByWeek(
                    $data['week']['start_date'],
                    $data['week']['num'] < count($data['week']['time']) ? count($data['week']['time']) : $data['week']['num'],
                    array_keys($time)
                )
            );
            $num = $data['week']['num'];
        }

        if (!empty($data['time'])) {
            $times = array_map(function ($item) {
                return [
                    'starttime' => strtotime($item['start_date'] . ' ' . $item['start_time']),
                    'endtime' => strtotime($item['start_date'] . ' ' . $item['end_time'])
                ];
            }, $data['time']);
            $num = count($data['time']);
        }

        if (!empty($data['lesson_id'])) {
            $times = ModelRoom::whereIn('id', $data['lesson_id'])
                ->field('starttime,endtime')
                ->select();
            $num = count($data['lesson_id']);
        }

        if (empty($times)) {
            throw new ValidateException(lang('Query criteria error'));
        }

        $models = [];
        ModelFreetime::withSearch(['teacher'], $this->param)
            ->select()
            ->filter(function ($model) use ($times) {
                foreach ($times as $time) {
                    if ($time['starttime'] >= $model['start_time'] && $time['endtime'] <= $model['end_time']) {
                        return true;
                    }
                }
                return false;
            })
            ->each(function ($model) use (&$models) {
                $models[$model['id']][] = $model;
            });
        foreach ($models as $key => $model) {
            $models[$key] = $model[0];
            $models[$key]['matchs'] = count($model);
            $models[$key]['match_rate'] = intval(count($model) / $num * 100) . '%';
            $models[$key]['match_week'] = array_values(array_unique(array_map(function ($item) {
                return intval(date('N', $item['start_time']));
            }, $model)));
        }
        usort($models, function ($a, $b) {
            return $b['matchs'] <=> $a['matchs'];
        });

        return $this->success($models);
    }

    public function freetimeList()
    {
        $timeFormat = ($this->request->user['company_model']['notice_config']['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';
        $models = ModelFreetime::withSearch(['start_date', 'end_date', 'teacher_id'], $this->param)
            ->select()
            ->withAttr('start_to_end_time', function ($value, $data) use ($timeFormat) {
                return date($timeFormat, $data['start_time']) . '~' . date($timeFormat, $data['end_time']);
            })
            ->append(['start_to_end_time']);

        return $this->success($models);
    }

    public function read()
    {
        $models = ModelRoom::withSearch(['freetime'], $this->param)->select();

        return $this->success($models);
    }
}
