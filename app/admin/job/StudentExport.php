<?php

/**
 * 学生异步导出
 */

namespace app\admin\job;

use app\admin\model\FrontUser;
use app\common\facade\Excel;
use EasyWeChat\Factory;
use think\facade\{Filesystem, Db, Lang};
use think\queue\Job;

class StudentExport
{

    /**
     * 消费
     * @param Job $job
     * @param
     */
    public function fire(Job $job, $queParams)
    {
        Lang::load(app()->getBasePath() . 'admin/lang/' . $queParams['lang'] . '.php');

        request()->user = array_merge(request()->user ?? [], ['company_id' => $queParams['company_id']]);

        $data = FrontUser::withSearch(['studentexport'], $queParams)->select();

        $config = [
            'app_id' => config('wechat.app_id'),
            'secret' => config('wechat.secret'),
            'token' => config('wechat.token'),
            'response_type' => 'array',
        ];
        $this->wechatApp = Factory::officialAccount($config);

        $filePath = [];

        if (!$data->isEmpty()) {
            $data->withAttr('qrcode', function ($value) {
                return $value ? lang('bind') : lang('unbind');
            })->withAttr('sex', function ($value) {
                return $value ? lang('male') : lang('female');
            })->withAttr('link', function ($value, $all) use ($queParams, &$filePath) {
                $result = $this->wechatApp->qrcode->temporary($all['user_account_id'] . '-' . $queParams['company_id'], 6 * 24 * 3600);
                $url = $this->wechatApp->qrcode->url($result['ticket']);
                $content = file_get_contents($url);
                $fileName = $filePath[] = app()->getRuntimePath() . $queParams['company_id'] . '_' . MD5($all['user_account_id'] . '_' . $queParams['userroleid']) . '.png';
                file_put_contents($fileName, $content);
                return $fileName;
            });
        }

        $export = array_map(function ($value) {
            unset($value['user_account_id']);
            $value['p_name'] = $value['p_name'] ?: '';
            $value['relation'] = $value['relation'] ?: '';
            return $value;
        }, $data->toArray());


        $header = [
            lang('student_name'), lang('nickname'), lang('sex'), lang('birthday'),
            lang('mobile'), lang('domain_account'), lang('Parent name'), lang('relation'), lang('notice_helper'), lang('Little assistant QR code')
        ];

        $obj = Excel::export($export, $header, date('Y-m-d'));

        $path = 'excel/' . $queParams['fileName'] . '.xls';
        Filesystem::write($path, $obj->getContent());

        $save = [];
        $save['size'] = Filesystem::getSize($path);
        $save['path'] = $path;

        foreach ($filePath as $p) {
            if (file_exists($p)) {
                unlink($p);
            }
        }

        Db::table('saas_file_export')->where('id', $queParams['fileId'])->update($save);
        $job->delete();
    }
}
