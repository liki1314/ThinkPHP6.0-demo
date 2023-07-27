<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use app\common\model\Company;
use app\home\controller\Base;
use app\home\model\saas\Freetime as SaasFreetime;
use app\home\model\saas\Room;
use app\home\validate\Freetime as ValidateFreetime;

class Freetime extends Base
{

    public function index()
    {
        if (!empty($this->request->param('company_id')) && in_array($this->request->param('company_id'), $this->request->user['companys'])) {
            $model = Company::cache(true, 12 * 3600)->find($this->request->param('company_id'));
        }
        $timeFormat = ($model['notice_config']['time_format']['h24'] ?? config('app.company_default_config.time_format.h24')) == 1 ? 'H:i' : 'h:i A';

        $models = SaasFreetime::withSearch(['start_date', 'end_date', 'teacher_id'], $this->param)
            ->select()
            ->withAttr('start_to_end_time', function ($value, $data) use ($timeFormat) {
                return date($timeFormat, $data['start_time']) . '~' . date($timeFormat, $data['end_time']);
            })
            ->append(['start_to_end_time']);

        return $this->success($models);
    }

    public function save()
    {
        $this->validate($this->param, ValidateFreetime::class);

        SaasFreetime::create($this->param);

        return $this->success();
    }

    public function read()
    {
        $models = Room::withSearch(['freetime'], $this->param)->select();

        return $this->success($models);
    }

    public function delete($id)
    {
        SaasFreetime::where('id', $id)->where('status', SaasFreetime::FREE_STATUS)->delete();

        return $this->success();
    }
}
