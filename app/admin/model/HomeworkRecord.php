<?php
/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-29
 * Time: 14:33
 */

namespace app\admin\model;

use app\gateway\model\UserAccount;

class HomeworkRecord extends Base
{
    protected $deleteTime = false;

    protected $pk = ['homework_id', 'student_id'];

    protected $json = ['submit_files', 'remark_files'];

    /**
     * 作业学生列表
     * @return \think\model\relation\HasOne
     */
    public function student()
    {
        return $this->hasOne(FrontUser::class, 'id', 'student_id');
    }

    public function teacher()
    {
        return $this->hasOne(UserAccount::class, 'id', 'teacher_id');
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
            $query->field(['scu.username as teacher_name', 'auc.avatar as teacher_avatar'])
                ->leftJoin(['saas_company_user' => 'scu'], "scu.company_id = $companyId and scu.user_account_id=homework_record.teacher_id ")
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
