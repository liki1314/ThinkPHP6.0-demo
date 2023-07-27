<?php

declare(strict_types=1);

namespace app\home\model\saas\teacher;


use app\home\model\saas\Base;
use app\home\model\saas\FrontUser;
use app\home\model\saas\Homework;
use app\home\model\saas\File;
use app\home\model\saas\HomeworkRecordLog;
use think\helper\Arr;
use think\facade\Queue;


class HomeworkRecord extends Base
{
    protected $json = ['submit_files', 'remark_files'];

    // protected $pk = ['homework_id', 'student_id'];

    protected $deleteTime = false;

    /** 未提交 */
    const UNSUBMIT_STATUS = 1;
    /** 已提交 */
    const SUBMIT_STATUS   = 2;
    /** 已批阅 */
    const REMARK_STATUS   = 3;
    // 未通过
    const UNPASS_STATUS = 4;
    /** 草稿*/
    const DRAFT_STATUS = 0;

    public function student()
    {
        return $this->hasOne(FrontUser::class, 'id', 'student_id');
    }

    public function teacher()
    {
        return $this->hasOne(UserAccount::class, 'id', 'teacher_id');
    }

    public function homework()
    {
        return $this->belongsTo(Homework::class)->bind(['company_id']);
    }

    public function records()
    {
        return $this->hasMany(HomeworkRecordLog::class);
    }

    public function getIdAttr($value, $data)
    {
        return $data['student_id'];
    }

    public function getStatusAttr($value, $data)
    {
        if (request()->header('version') == 'v3' && isset($data['is_pass']) && $data['is_pass'] == 0) {
            return self::UNPASS_STATUS;
        }

        return $data['submit_time'] == 0 ? (($data['submit_files'] || $data['submit_content']) ? self::DRAFT_STATUS : self::UNSUBMIT_STATUS) : ($data['remark_time'] == 0 ? self::SUBMIT_STATUS : self::REMARK_STATUS);
    }

    // 提交草稿
    public function setIsDraftAttr($value)
    {
        $this->set('submit_time', $value == 0 ? time() : 0);
        return $value;
    }

    // 提交文件
    public function setSubmitFilesAttr($value)
    {
        return array_map(function ($array) {
            return Arr::only($array, ['id', 'source', 'duration']);
        }, $value);
    }

    public static function onAfterUpdate($model)
    {
        switch (sprintf('%s-%s', $model->getStatusAttr('', $model->getOrigin()), $model->getAttr('status'))) {
            case '3-2':
                case '4-2': //未通过-重新提交作业
                    Homework::where('id', $model['homework_id'])
                        ->inc('submits')
                        ->update();
                    break;
                case '2-0': //已提交-撤回为草稿
                    Homework::where('id', $model['homework_id'])
                        ->dec('submits')
                        ->update();
                    break;
                case '0-2': //草稿-提交
                case '1-2': //未提交-提交
                    Homework::where('id', $model['homework_id'])
                        ->inc('submits')
                        ->update();
                    break;
                case '2-4': //已提交-未通过
                    Homework::where('id', $model['homework_id'])
                        ->dec('submits')
                        ->update();
                    break;
                case '2-3': //已提交-点评
                    Homework::where('id', $model['homework_id'])
                        ->inc('remarks')
                        ->update();
                    break;
                case '4-3': //未通过-通过
                    Homework::where('id', $model['homework_id'])
                        ->inc('remarks')
                        ->inc('submits')
                        ->update();
                    break;
                case '3-4': //通过-未通过
                    Homework::where('id', $model['homework_id'])
                        ->dec('remarks')
                        ->dec('submits')
                        ->update();
                    break;
        }

        $deleted = [];
        if (!empty($model->getOrigin('remark_files'))) {
            $deleted = array_merge($deleted, array_column(array_udiff(
                $model->getOrigin('remark_files'),
                $model['remark_files'] ?? [],
                function ($a, $b) {
                    if (isset($a['id']) && $a['id'] == $b['id'] && $a['source'] == 1 && $b['source'] == 1 || isset($a['path']) && $a['path'] == $b['path']) {
                        return 0;
                    } else {
                        return -1;
                    }
                }
            ), 'id'));
        }

        if (!empty($model->getOrigin('submit_files'))) {
            $deleted = array_merge($deleted, array_column(array_udiff(
                $model->getOrigin('submit_files'),
                $model['submit_files'] ?? [],
                function ($a, $b) {
                    if (isset($a['id']) && $a['id'] == $b['id'] && $a['source'] == 1 && $b['source'] == 1 || isset($a['path']) && $a['path'] == $b['path']) {
                        return 0;
                    } else {
                        return -1;
                    }
                }
            ), 'id'));
        }

        if (!empty($deleted)) {
            File::whereIn('id', $deleted)->useSoftDelete('delete_time', time())->delete();
            Queue::push(\app\home\job\FileDelete::class, ['files' => $deleted], 'file_delete');
        }
    }

    /**
     * 默认搜索器
     * @param $query
     */
    public function searchDefaultAttr($query)
    {
        $companyId = request()->user['company_id'];
        $query->withJoin(['student' => ['id', 'userroleid', 'nickname', 'avatar', 'sex']]);
        if (request()->get("remark") == 1) {
            $query->field(['auc.username as teacher_name', 'auc.avatar as teacher_avatar'])
                // ->leftJoin(['saas_company_user' => 'scu'], "scu.company_id = $companyId and scu.user_account_id=homework_record.teacher_id ")
                ->leftJoin(['saas_user_account' => 'auc'], "auc.id=__TABLE__.teacher_id");
        }
        $query->order('remark_time', 'asc')->append(['history_remark_time', 'history_submit_time', 'unreminds']);
    }

    public function searchIdAttr($query, $value)
    {
        $query->where("homework_id", $value);
    }

    /**
     * 获取点评的
     * @param $query
     * @param $value
     */
    public function searchRemarkAttr($query, $value)
    {
        if ($value == 1) {
            $query->where("remark_time", '>', 0);
        } else {
            $query->where("remark_time", 0);
        }
    }

    /**
     * 获取提交
     * @param $query
     * @param $value
     */
    public function searchSubmitAttr($query, $value)
    {
        if ($value == 1) {
            $query->where("submit_time", '>', 0);
        } else {
            $query->where("submit_time", 0);
        }
    }

    /**
     * 提交时间格式化
     * @param $value
     * @return false|string
     */
    public function getSubmitTimeAttr($value)
    {
        return $value > 0 ? date("Y-m-d H:i", $value) : 0;
    }

    /**
     * 提交时间格式化
     * @param $value
     * @return false|string
     */
    public function getRemarkTimeAttr($value)
    {
        return $value > 0 ? date("Y-m-d H:i", $value) : 0;
    }


    /**
     * 提交历史时间间隔
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getHistorySubmitTimeAttr($value, $data)
    {

        return $data['submit_time'] > 0 ? history_time($data['submit_time']) : '';
    }

    /**
     * 点评历史时间间隔
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getHistoryRemarkTimeAttr($value, $data)
    {
        return $data['remark_time'] > 0 ? history_time($data['remark_time']) : '';
    }


    /**
     * @param $value
     * @return false|int|string
     */
    public function getReadTimeAttr($value)
    {
        return $value > 0 ? date('Y-m-d H:i', $value) : 0;
    }
}
