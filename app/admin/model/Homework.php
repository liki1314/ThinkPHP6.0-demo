<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-03-29
 * Time: 11:42
 */

namespace app\admin\model;

use app\common\http\FileHttp;
use app\common\service\Upload;
use think\exception\ValidateException;
use think\Model;

class Homework extends Base
{
    public static $fieldInsert = [
        'day',
        'title',
        'content',
        'students',
        'resources',
        'submit_way',
        'issue_status',
        'is_draft',
        'room_id',
        'create_by',
        'company_id'
    ];

    protected $json = ['resources'];

    //提交方式
    const SUBMIT_WAY_NO = 0;
    const SUBMIT_WAY_IMAGE = 1;
    const SUBMIT_WAY_VIDEO = 2;
    const SUBMIT_WAY_RECORD = 3;
    //发布类型
    const ISSUE_RELEASE = 1;
    const ISSUE_DATE = 2;
    //是否是草稿
    const DRAFT_NO = 0;
    const DRAFT_YSE = 1;

    const FILE_L = 1;
    const FILE_C = 2;

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->set('create_by', request()->user['user_account_id']);
        $model->set('students', count($model->students));
        $model->set('reads', 0);
        $model->set('submits', 0);
        $model->set('remarks', 0);
        $model->set('resources', is_array($model->getData('resources')) ? array_map(function ($value) {
            if (isset($value['source'])) {
                $data['id'] = intval($value['id']);
                $data['source'] = intval($value['source']);
                isset($value['duration']) && $data['duration'] = intval($value['duration']);
            } else {
                $data['name'] = $value['name'];
                $data['path'] = $value['path'];
            }
            return $data;
        }, $model->getData('resources')) : []);
    }

    public static function onBeforeDelete(Model $model)
    {
        if ($model['submits'] > 0) {
            throw new ValidateException(lang("submit_time_no_del"));
        }
    }

    public function students()
    {
        return $this->hasMany(HomeworkRecord::class, 'homework_id');
    }

    /**
     * 默认搜索器
     * @param $query
     */
    public function searchDefaultAttr($query)
    {
        $query->alias("hw")
            ->field([
                'hw.id',
                'scu.username as teacher_name',
                'hw.day',
                'hw.title',
                'hw.students as students_total',
                'hw.reads',
                'hw.submits',
                'hw.remarks',
                'hw.create_time',
                'hw.update_time',
                'hw.issue_status'
            ])
            ->join('user_account scu', 'hw.create_by = scu.id')
            // ->leftJoin(['saas_company_user' => 'scu'], 'hw.create_by = scu.user_account_id and hw.company_id = scu.company_id')
            ->append(['unsubmits', 'unreads', 'status'])
            ->hidden(['reads']);
    }

    /**
     * 标题模糊搜索器
     * @param $query
     * @param $value
     */
    public function searchTitleAttr($query, $value)
    {
        $query->whereLike('__TABLE__.title', '%' . $value . '%');
    }

    /**
     * 创建时间搜索器
     * @param $query
     * @param $value
     */
    public function searchAddTimeAttr($query, $value)
    {
        $where = [
            strtotime(date("Y-m-d 00:00:00", strtotime($value))),
            strtotime(date("Y-m-d 23:59:59", strtotime($value)))
        ];
        $query->whereBetween('__TABLE__.create_time', $where);
    }

    /**
     * 老师搜索器
     * @param $query
     * @param $value
     */
    public function searchTeacherAttr($query, $value)
    {

        $query->whereIn('__TABLE__.create_by', explode(',', $value));
    }

    /**
     * 是否草稿搜索器
     * @param $query
     * @param $value
     */
    public function searchIsDraftAttr($query, $value)
    {

        if ($value == self::DRAFT_YSE) {
            $query->where('__TABLE__.is_draft', self::DRAFT_YSE)->order('hw.update_time', 'desc');
        } else {
            $query->where('__TABLE__.is_draft', self::DRAFT_NO)->order('hw.create_time', 'desc');
        }
    }

    /**
     * 点评搜索器
     * @param $query
     * @param $value
     */
    public function searchIsAllRemarkAttr($query, $value)
    {
        if ($value == 1) {
            $query->whereColumn('__TABLE__.students', '<>', '__TABLE__.remarks');
        } elseif ($value == 2) {
            $query->whereColumn('__TABLE__.students', '__TABLE__.remarks');
        }
    }

    /**
     * 未提交数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getUnsubmitsAttr($value, $data)
    {
        return $data['students_total'] - $data['submits'];
    }

    /**
     * 未读数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getUnreadsAttr($value, $data)
    {
        return $data['students_total'] - $data['reads'];
    }

    /**
     * 创建时间格式化
     * @param $value
     * @return false|string
     */
    public function getCreateTimeAttr($value)
    {
        return date("Y-m-d H:i", $value);
    }

    /**
     * 更新时间格式化
     * @param $value
     * @return false|string
     */
    public function getUpdateTimeAttr($value)
    {
        return date("Y-m-d H:i", $value);
    }

    /**
     * 状态
     * @param $value
     * @return false|string
     */
    public function getStatusAttr($value, $data)
    {
        return $data['issue_status'] == 1 ? 1 : (strtotime($data['day']) < time() ? 1 : 0);
    }


    /**
     * 提交率
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getSubmitRateAttr($value, $data)
    {
        return sprintf("%.2f%%", $data['submits'] / $data['students_total'] * 100);
    }

    /**
     * 已读率
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getReadRateAttr($value, $data)
    {
        return sprintf("%.2f%%", $data['reads'] / $data['students_total'] * 100);
    }

    /**
     *
     * 获取文件真实数据
     * @param $item 为kv
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getResources($item)
    {
        if (empty($item)) return [];
        $ids1 = [];  //本地
        $ids2 = [];  //企业网盘
        $data = [
            self::FILE_L => [],
            self::FILE_C => []
        ];
        // duration  source
        foreach ($item as $key => $value) {
            foreach ($value as $k => $v) {
                if (!isset($v['source'])) {
                    $item[$key][$k] = [
                        'url' => Upload::getFileUrl($v['path']),
                        'name' => $v['name']
                    ];
                } else {
                    if ($v['source'] == self::FILE_L) {
                        $ids1[] = $v['id'];
                    } else {
                        $ids2[] = $v['id'];
                    }
                }
            }
        }

        if (!empty($ids2)) {
            $res = (new FileHttp())->getIdsToFile($ids2);
            foreach ($res as $v) {
                $data[self::FILE_C][$v['id']] = [
                    'id' => $v['id'],
                    'name' => $v['name'],
                    'url' => $v['download_url'],
                    'type' => $v['type'],
                    'size' => $v['size']
                ];
            }
        }

        if (!empty($ids1)) {
            $res = File::whereIn('id', $ids1)->select();
            foreach ($res as $v) {
                $data[self::FILE_L][$v['id']] = [
                    'id' => $v['id'],
                    'name' => $v['name'],
                    'url' => Upload::getFileUrl($v['path']),
                    'type' => $v['type'],
                    'size' => $v['size']
                ];
            }
        }


        foreach ($item as $key => $value) {
            foreach ($value as $k => $v) {
                if (isset($v['id'])) {
                    if ($v['source'] == self::FILE_L) {
                        if (isset($data[self::FILE_L][$v['id']])) {
                            $item[$key][$k] = array_merge($data[self::FILE_L][$v['id']], $item[$key][$k]);
                        } else {
                            unset($item[$key][$k]);
                        }
                    } else {
                        if (isset($data[self::FILE_C][$v['id']])) {
                            $item[$key][$k] = array_merge($data[self::FILE_C][$v['id']], $item[$key][$k]);
                        } else {
                            unset($item[$key][$k]);
                        }
                    }
                }
            }
        }
        foreach ($item as $key => $value) {
            $item[$key] = array_values($value);
        }
        return $item;
    }
}
