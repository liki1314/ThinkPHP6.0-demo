<?php

declare(strict_types=1);

namespace app\webapi\model;


use think\facade\Db;
use think\file\UploadedFile;
use app\common\service\Upload;
use app\common\http\WebApi;

class MicroCourse extends Base
{
    /** 微课类型 */
    const COURSE_TYPE = 1;
    /** 微课包类型 */
    const PACKAGE_TYPE = 2;

    /** 微录课类型 */
    const  ROOMTYPE_MICRO = 6;


    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->set('custom_id', uniqid('', true));
    }

    public function setPicAttr(UploadedFile $file)
    {
        $fileName = Upload::putFile($file);
        $this->set('pic', $fileName);
    }

    public function getPicAttr($value)
    {
        return $value ? Upload::getFileUrl($value) : '';
    }

    public function getSizeAttr($value)
    {
        return $value ? human_filesize($value) : 0;
    }


    public function getTimesAttr($value)
    {
        return gmdate('H:i:s', $value);
    }

    public function getStatusAttr($value, $data)
    {
        return $data['type'] == MicroCourse::PACKAGE_TYPE ? null : ($value ?? 0);
    }


    public function searchOnlypackageAttr($query, $value, $data)
    {
        if (isset($data['name'])) {
            $query->whereLike('name', '%' . $data['name'] . '%');
        }

        $query->where('type', self::PACKAGE_TYPE)
            ->visible(['id', 'name']);
    }

    public function searchPackageIdAttr($query, $value, $data)
    {
        $query->where('parent_id', $value);
    }

    public function searchUserIdAttr($query, $value)
    {
        $query->where('user_id', $value);
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('id,name,pic,intro,create_time,times,size,type,status,record preview')
            ->order('create_time', 'desc');

        if (!isset($data['id']) && !isset($data['name']) && !isset($data['package_id'])) {
            $query->where('parent_id', 0);
        }
    }


    public function searchNameAttr($query, $value)
    {
        $query->whereLike('name', '%' . $value . '%');
    }

    public function searchIdAttr($query, $value)
    {
        $query->where('__TABLE__.id', $value);
    }

    public function searchTypeAttr($query, $value, $data)
    {
        $query->where('type', $value);
    }

    /**
     * 创建微录课成功之后,返回进入教室的链接地址
     * @param $customId
     */
    public function getMicEnter($customId)
    {
        $type = 0; //教师身份
        $apiRes =  WebApi::httpPost(
            'WebAPI/getroom',
            [
                'key'=>request()->company['authkey'],
                'thirdroomid' => $customId,
                'usertype' => $type,
                'username' => rand(10000, 99999),
                'pid' => request()->param('user_id')
            ]
        );

        return $apiRes['entryurl'] ?? '';
    }


    /**
     * 微录课 预约房间
     * @param $params
     */
    public function createRoom($data)
    {
        $params = [];
        $params['key'] = request()->company['authkey'];
        $params['roomname'] = $data['roomname'];
        $params['thirdroomid'] = $data['custom_id'];
        $params['roomtype'] = self::ROOMTYPE_MICRO;
        $params['starttime'] = time();
        $params['endtime'] = time() + (60 * 60);
        $params['chairmanpwd'] = rand(1000, 9999);
        $params['assistantpwd'] = rand(1000, 9999);
        $params['patrolpwd'] = rand(1000, 9999);

        $template = Db::name('room_template')->json(['extra_info'])->find($data['room_template_id']);
        $params['videotype'] = $template['video_ratio'];
        $params['autoopenaudio'] = $template['extra_info']['auto_open_audio'] ?? 0;
        $params['autoopenvideo'] = $template['extra_info']['auto_open_video'] ?? 0;
        $params['ismp4record'] = $template['extra_info']['is_video'] ?? 0;
        $params['showYourself'] = $template['extra_info']['only_teacher_and_self'] ?? 0;
        $params['roomlayout'] = $template['layout_id'];
        $params['config']['chk_answering_machine'] = $template['extra_info']['answering_machine'] ?? 0;
        $params['config']['chk_turntable'] = $template['extra_info']['turntable'] ?? 0;
        $params['config']['chk_timer'] = $template['extra_info']['timer'] ?? 0;
        $params['config']['answering_machine'] = $template['extra_info']['first_answering_machine'] ?? 0;
        $params['config']['chk_triazolam'] = $template['extra_info']['triazolam'] ?? 0;
        $params['config']['AllowStudentCloseAudio']  = $template['extra_info']['student_close_a'] ?? 0;
        $params['config']['AllowStudentCloseVideo'] = $template['extra_info']['student_close_v'] ?? 0;
        $params['config']['chk_assistantopenav'] =  $template['extra_info']['assistantopenav'] ?? 0;
        $params['config']['HiddenKicking'] =  $template['extra_info']['hidden_kicking'] ?? 0;
        $params['config']['AVGuide'] =  $template['extra_info']['av_guide'] ?? 0;
        $params['config']['DeviceCheckContinue'] = $template['extra_info']['device_check_continue'] ?? 0;
        $params['config']['CutPicture'] =  $template['extra_info']['cut_picture'] ?? 0;

        return WebApi::httpPost('/WebAPI/roomcreate', $params);
    }

    /**
     * 统计包的大小
     * @param $data
     */
    public function countPackageSize($data)
    {
        if (!$data || !isset($data['data'])) {
            return $data;
        }

        $packageId = [];

        foreach ($data['data'] as $value) {
            if ($value['type'] == self::PACKAGE_TYPE) {
                $packageId[] = $value['id'];
            }
        }

        if (!$packageId) {
            return $data;
        }

        $sizeGroup =  Db::table('saas_micro_course')
            ->field('sum(size) totalSize,parent_id')
            ->whereIn('parent_id', $packageId)
            ->group('parent_id')
            ->select()
            ->toArray();

        if (!$sizeGroup) {
            return $data;
        }

        foreach ($data['data'] as &$item) {
            foreach ($sizeGroup as $size) {
                if ($item['type'] == self::PACKAGE_TYPE && $size['parent_id'] == $item['id']) {
                    $item['size'] = human_filesize($size['totalSize']);
                }
            }
        }
        return  $data;
    }
}
