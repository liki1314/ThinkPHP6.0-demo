<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\common\http\FileHttp;
use app\common\service\Upload;
use think\exception\ValidateException;
use think\Model;
use app\Request;
use think\facade\Queue;

class Homework extends Base
{
    protected $json = ['resources'];

    //学生作业
    public function studentHomeworks()
    {
        return $this->hasMany(HomeworkRecord::class);
    }

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

    const FILE_L = 1;
    const FILE_C = 2;

    //发布类型
    const ISSUE_STATUS = 1;
    const ISSUE_STATUS_DATE = 2;

    //提交方式
    const SUBMIT_WAY_NO = 0;
    const SUBMIT_WAY_IMAGE = 1;
    const SUBMIT_WAY_VIDEO = 2;
    const SUBMIT_WAY_RECORD = 3;

    //是否是草稿
    const DRAFT_NO = 0;
    const DRAFT_YSE = 1;


    public function students()
    {
        return $this->hasMany(HomeworkRecord::class, 'homework_id');
    }

    public static function onAfterRead(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            if (!empty($request->user) && !isset($request->user['company_id'])) {
                $request->user = array_merge(
                    $request->user,
                    [
                        'company_id' => $model['company_id']
                    ]
                );
            }
        });
    }

    public static function onAfterUpdate(Model $model)
    {
        if (!empty($model->getOrigin('resources'))) {

            $deleted = array_column(array_udiff(
                $model->getOrigin('resources'),
                $model['resources'] ?? [],
                function ($a, $b) {
                    if ($a['id'] == $b['id'] && $a['source'] == 1 && $b['source'] == 1) {
                        return 0;
                    } else {
                        return -1;
                    }
                }
            ), 'id');

            if (!empty($deleted)) {
                File::whereIn('id', $deleted)->useSoftDelete('delete_time', time())->delete();
                Queue::push(\app\home\job\FileDelete::class, ['files' => $deleted, 'company_id' => $model['company_id']], 'file_delete');
            }
        }
    }

    public static function onAfterWrite(Model $model)
    {
        $files = array_column(array_filter($model['resources'] ?? [], function ($value) {
            return $value['source'] == 1;
        }), 'id');
        if (!empty($files)) {
            Queue::push(
                \app\home\job\FileConvert::class,
                ['files' => $files, 'company_id' => $model['company_id']],
                'file_convert'
            );
        }
    }

    public static function onAfterDelete(Model $model)
    {
        $deleted = array_column(array_filter($model['resources'] ?? [], function ($value) {
            return $value['source'] == 1;
        }), 'id');

        if (!empty($deleted)) {
            File::whereIn('id', $deleted)->useSoftDelete('delete_time', time())->delete();
            Queue::push(\app\home\job\FileDelete::class, ['files' => $deleted, 'company_id' => $model['company_id']], 'file_delete');
        }
    }

    public static function onBeforeInsert($model)
    {

        $model->set('day', isset($model['day']) && !empty($model['day']) ? $model['day'] : date('Y-m-d'));
        $model->set('issue_status', isset($model['issue_status']) && !empty($model['issue_status']) ? $model['issue_status'] : self::ISSUE_STATUS);
        $model->set('create_by', request()->user['user_account_id']);
        $model->set('company_id', request()->user['company_id']);
        $model->set('room_id', request()->param('lesson_id'));
        $model->set('resources', isset($model['resources']) && is_array($model['resources']) ? self::execResources($model['resources']) : []);
    }


    /**
     * @param $resources
     * @return array
     */
    public static function execResources($resources)
    {
        return array_map(function ($value) {
            if (isset($value['source'])) {
                $data['id'] = intval($value['id']);
                $data['source'] = intval($value['source']);
                isset($value['duration']) && $data['duration'] = intval($value['duration']);
            } else {
                $data['name'] = $value['name'];
                $data['path'] = $value['path'];
            }
            return $data;
        }, $resources);
    }

    public static function onBeforeUpdate($model)
    {
        self::onBeforeInsert($model);
    }

    public static function onBeforeDelete(Model $model)
    {
        if ($model['submits'] > 0) {
            throw new ValidateException(lang("submit_time_no_del"));
        }
    }

    public function searchDefaultAttr($query)
    {
        $query->field([
            'id as homework_id',
            'day',
            'title',
            'room_id',
            'submits',
            'remarks',
            'students',
            'create_time',
            'issue_status',
            'reads',
            'is_draft'
        ])->where('create_by', request()->user['user_account_id'])
            ->order('create_time', 'desc')
            ->append(['status', 'unsubmits', 'serial', 'unremarks', 'release', 'unreads'])
            ->hidden(['is_draft', 'remark_time', 'room_id']);
    }

    public function searchDetailAttr($query, $value, $data)
    {
        $query->where('create_by', $data['user']['user_account_id']);
    }

    /**
     * @param $query
     * @param $value 0:未完成； 1:全部完成；2:部分完成；3:一个也没完成
     */
    public function searchReadsAttr($query, $value)
    {
        $query->where('is_draft', self::DRAFT_NO);
        if (empty($value)) {
            $query->whereColumn('__TABLE__.reads', '<', '__TABLE__.students');
        } elseif ($value == 1) {
            $query->whereColumn('__TABLE__.reads', '__TABLE__.students');
        } elseif ($value == 2) {
            $query->where('reads', '>', 0)->whereColumn('__TABLE__.reads', '<', '__TABLE__.students');
        } elseif ($value == 3) {
            $query->where('reads', 0);
        }
    }


    /**
     * @param $query
     * @param $value 0:未完成； 1:全部完成；2:部分完成；3:一个也没完成
     */

    public function searchSubmitsAttr($query, $value)
    {
        $query->where('is_draft', self::DRAFT_NO);
        if (empty($value)) {
            $query->whereColumn('__TABLE__.submits', '<', '__TABLE__.students');
        } elseif ($value == 1) {
            $query->whereColumn('__TABLE__.submits', '__TABLE__.students');
        } elseif ($value == 2) {
            $query->where('submits', '>', 0)->whereColumn('__TABLE__.submits', '<', '__TABLE__.students');
        } elseif ($value == 3) {
            $query->where('submits', 0);
        }
    }

    /**
     *
     * @param $query
     * @param $value 0:作业未完成+草稿； 1:作业全部完成；2:作业部分完成；3:作业一个也没完成;4作业未完成
     */
    public function searchRemarksAttr($query, $value)
    {
        if (empty($value)) {
            $query->where(function ($query) {
                $query->whereColumn('__TABLE__.remarks', '<', '__TABLE__.students')
                    ->whereOr('is_draft', self::DRAFT_YSE);
            });
        } elseif ($value == 1) {
            $query->whereColumn('__TABLE__.remarks', '__TABLE__.students')
                ->where('is_draft', self::DRAFT_NO);
        } elseif ($value == 2) {
            $query->where('remarks', '>', 0)
                ->whereColumn('__TABLE__.remarks', '<', '__TABLE__.students')
                ->where('is_draft', self::DRAFT_NO);
        } elseif ($value == 3) {
            $query->where('remarks', 0)
                ->where('is_draft', self::DRAFT_NO);
        } elseif ($value == 4) {
            $query->whereColumn('__TABLE__.remarks', '<', '__TABLE__.students')
                ->where('is_draft', self::DRAFT_NO);
        }
    }


    /**
     * 草稿
     * @param $query
     * @param $value
     */
    public function searchIsDraftAttr($query, $value)
    {
        $query->where('is_draft', $value);
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
     * 0：草稿；1：未提交；2：已提交；3：已批阅
     * @param $value
     * @param $data
     * @return int
     */
    public function getStatusAttr($value, $data)
    {

        if ($data['is_draft'] == self::DRAFT_YSE) return 0;
        if ($data['students'] == $data['remarks']) return 3;
        if ($data['students'] == $data['submits']) return 2;
        return 1;
    }

    public function getUnsubmitsAttr($value, $data)
    {
        return $data['students'] - $data['submits'];
    }

    public function getSerialAttr($value, $data)
    {
        return $data['room_id'];
    }
    public function getStudentsTotalAttr($value, $data)
    {
        return $data['students'];
    }

    public function getUnremarksAttr($value, $data)
    {
        return $data['submits'] - $data['remarks'];
    }

    public function getCreateTimeAttr($value)
    {
        return $value;
    }

    /**
     * 状态
     * @param $value
     * @return false|string
     */
    public function getReleaseAttr($value, $data)
    {
        return $data['issue_status'] == 1 ? 1 : (strtotime($data['day']) < time() ? 1 : 0);
    }


    /**
     * 未读数
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getUnreadsAttr($value, $data)
    {
        return $data['students'] - $data['reads'];
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
                    'preview_url' => $v['preview_url'],
                    'type' => $v['type'],
                    'size' => human_filesize($v['size'])
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
                    'size' => human_filesize($v['size'])
                ];
                $data[self::FILE_L][$v['id']]['preview_url'] = $v['live_fileinfo']['preview_url'] ?? get_office_preview($data[self::FILE_L][$v['id']]['url']);
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

    /**
     * 提交率
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getSubmitRateAttr($value, $data)
    {
        return sprintf("%.2f%%", $data['submits'] / $data['students'] * 100);
    }

    /**
     * 已读率
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getReadRateAttr($value, $data)
    {
        return sprintf("%.2f%%", $data['reads'] / $data['students'] * 100);
    }
}
