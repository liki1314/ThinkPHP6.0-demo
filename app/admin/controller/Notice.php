<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\model\Company as CompanyModel;
use think\helper\Arr;

class Notice extends Base
{
    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getConfig()
    {
        $config = CompanyModel::getNoticeConfigByCompanyId();

        if ($this->request->has('only', 'route')) {
            $config = Arr::only($config, $this->request->route('only'));
        }

        return $this->success($config);
    }

    /**
     * @return \think\response\Json
     */
    public function saveConfig()
    {
        $model = CompanyModel::field(['id', 'notice_config'])->findOrEmpty($this->request->user['company_id']);

        $config = $this->request->only(
            [
                'course',
                'homework',
                'teacher_enter_in_advance',
                'student_enter_in_advance',
                'prepare_lessons',
                'preview_lessons',
                'room',
                'homework_remark',
                'homework_remind',
                'repeat_lesson',
            ],
            'post'
        );
        $model->notice_config = array_replace_recursive($model['notice_config'] ?? [], $config);
        $model->force()->save();

        return $this->success();
    }

}
