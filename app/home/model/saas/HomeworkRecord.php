<?php

declare(strict_types=1);

namespace app\home\model\saas;

use app\common\service\Upload;
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


    /** 已提交*/
    const STUDENT_SUBMIT_STATUS     = 1;
    /**  未提交 */
    const STUDENT_UNSUBMIT_STATUS   = 0;
    /** 草稿*/
    const DRAFT_STATUS = 0;


    public function homework()
    {
        return $this->belongsTo(Homework::class)->bind(['day', 'title', 'content', 'resources', 'submit_way', 'serial' => 'room_id', 'create_time', 'company_id', 'submits']);
    }

    public function user()
    {
        return $this->belongsTo(FrontUser::class, 'student_id')->bind(['nickname', 'name' => 'nickname', 'avatar']);
    }

    public function records()
    {
        return $this->hasMany(HomeworkRecordLog::class);
    }

    public static function onAfterUpdate($model)
    {
        switch (sprintf('%s-%s', $model->getStatusAttr('', $model->getOrigin()), $model->getAttr('status'))) {
            case '3-2': //已通过-删除点评
                Homework::where('id', $model['homework_id'])
                    ->dec('remarks')
                    ->update();
                break;
            case '4-2': //未通过-删除点评或重新提交作业
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
            default:
                # code...
                break;
        }

        if (!empty($model->getOrigin('submit_files'))) {
            $deleted = array_column(array_udiff(
                $model->getOrigin('submit_files'),
                $model['submit_files'] ?? [],
                function ($a, $b) {
                    if (isset($a['id']) && $a['id'] == $b['id'] && $a['source'] == 1 && $b['source'] == 1 || isset($a['path']) && $a['path'] == $b['path']) {
                        return 0;
                    } else {
                        return -1;
                    }
                }
            ), 'id');

            if (!empty($deleted)) {
                File::whereIn('id', $deleted)->useSoftDelete('delete_time', time())->delete();
                Queue::push(\app\home\job\FileDelete::class, ['files' => $deleted], 'file_delete');
            }
        }
    }


    public function searchDefaultAttr($query, $value, $data)
    {
        $query->field('homework_id,student_id,submit_time,remark_time,submit_files,submit_content')
            ->withJoin(['homework' => ['day', 'title', 'room_id', 'resources', 'create_time', 'company_id', 'submits']])
            ->order('homework.create_time', 'desc')
            ->append(['status', 'files'])
            ->hidden(['homework', 'remark_time', 'submit_files'])
            ->where('homework.delete_time', 0)
            ->where('homework.is_draft', 0)
            ->where(function ($query) {
                $query->where('homework.issue_status', 1)->whereOr('homework.day', '<=', date('Y-m-d'));
            })
            ->join(['saas_front_user' => 'k'], 'k.id=__TABLE__.student_id')
            ->where('k.user_account_id', request()->user['user_account_id'])
            ->when(!empty(request()->user['company_id']), function ($q) {
                $q->where('homework.company_id', request()->user['company_id']);
            });

        if (isset($data['day'])) {
            $query->where('homework.day', $data['day']);
        }

        if (isset($data['serial'])) {
            $query->where('homework.room_id', $data['serial']);
        }
    }


    public function searchDetailAttr($query, $value, $data)
    {
        $query->field('homework_id,student_id,submit_time,remark_time,submit_content,submit_files,remark_content,remark_files,rank,read_time')
            ->withJoin(['homework' => ['day', 'title', 'content', 'resources', 'submit_way', 'company_id']])
            ->where('homework_id', $data['homework_id'])
            ->where('student_id', $data['student_id'])
            ->append(['status', 'answer', 'remark'])
            ->hidden([
                'homework',
                'submit_time',
                'remark_time',
                'submit_content',
                'submit_files',
                'remark_content',
                'remark_files',
                'rank',
            ]);
    }

    public function searchRecordAttr($query, $value, $data)
    {
        $query->field('id,homework_id,submit_time,remark_time,submit_content,submit_files,is_pass')
            ->withJoin([
                // 'homework' => ['title', 'content', 'resources', 'submit_way', 'create_time'],
                'user' => ['id', 'user_account_id', 'avatar', 'nickname', 'userroleid', 'sex'],
            ])
            ->with([
                'homework' => function ($query) {
                    $query->withField(['id', 'title', 'content', 'resources', 'submit_way', 'create_time']);
                },
                'records' => function ($query) {
                    $query->field([
                        'id',
                        'homework_record_id',
                        'submit_content' => 'content',
                        'submit_time',
                        'submit_files' => 'resources',
                        'remark_content',
                        'remark_files',
                        'remark_time',
                        'rank',
                        'is_pass',
                    ])
                        ->withJoin(['teacher' => ['username', 'avatar']])
                        ->append(['remark'])
                        ->hidden([
                            'homework_record_id',
                            'remark_time',
                            'remark_content',
                            'remark_files',
                            'rank',
                            'is_pass',
                            'teacher',
                        ]);
                }
            ])
            ->where('homework_id', $data['homework_id'])
            ->when(
                $data['user']['current_identity'] == FrontUser::TEACHER_TYPE,
                function ($query) use ($data) {
                    $query->where('user.id', $data['student_id']);
                },
                function ($query) use ($data) {
                    $query->where('user.user_account_id', $data['user']['user_account_id']);
                }
            )

            ->withAttr('resources', function ($value, $data) {
                return Homework::getResources([$data['homework_id'] => $value])[$data['homework_id']];
            })
            ->append(['student', 'status'])
            ->hidden([
                'homework', 'user', 'day', 'serial', 'company_id', 'submits', 'nickname', 'submit_time',
                'remark_time',
                'submit_content',
                'submit_files',
                'name',
                'avatar',
                'id',
                'homework_id',
            ]);
    }

    public function searchStudentsAttr($query, $value, $data)
    {
        $query->field('submit_time,rank,submit_files,submit_content,remark_time,reminds,id,is_pass')
            ->withJoin(['user' => ['id', 'user_account_id', 'avatar', 'nickname', 'userroleid', 'sex']])
            ->join('company', 'user.company_id=company.id')
            ->field('user.id student_id,company.notice_config')
            ->with(['records' => function ($query) {
                $query->order('id', 'desc')->withLimit(1);
            }])
            ->where('homework_id', $data['homework_id'])
            ->when(
                !empty($data['is_submit']),
                function ($query) {
                    $query->where('submit_time', '>', 0)->where('is_pass', '<>', 0);
                },
                function ($query) {
                    $query->where(function ($query) {
                        $query->where('submit_time', 0)->whereOr('is_pass', 0);
                    });
                }
            )
            ->json(['notice_config'])
            ->append(['status', 'unreminds'])
            ->hidden(['records', 'submit_files', 'submit_content', 'remark_time', 'user', 'nickname', 'reminds', 'notice_config']);
    }

    public function getUnremindsAttr($value, $data)
    {
        $max = ((array)$data['notice_config'])['homework_remind']['time'] ?? config('app.notice.homework_remind.time');
        return max(($max - $data['reminds']), 0);
    }

    public function getStudentAttr()
    {
        return [
            'name' => $this->user->nickname,
            'avatar' => $this->user->avatar,
        ];
    }

    // 作业状态
    public function getStatusAttr($value, $data)
    {
        if (request()->header('version') == 'v3' && isset($data['is_pass']) && $data['is_pass'] == 0) {
            return self::UNPASS_STATUS;
        }

        return $data['submit_time'] == 0 ? (($data['submit_files'] || $data['submit_content']) ? self::DRAFT_STATUS : self::UNSUBMIT_STATUS) : ($data['remark_time'] == 0 ? self::SUBMIT_STATUS : self::REMARK_STATUS);
    }

    // 提交内容
    public function getAnswerAttr($value, $data)
    {
        return [
            'content' => $data['submit_content'],
            'resources' => $data['submit_files'] ? array_map(
                function ($array) {
                    if (isset($array['path'])) {
                        $array['url'] = Upload::getFileUrl($array['path']);
                    }
                    return $array;
                },
                $data['submit_files']
            ) : $data['submit_files'],
        ];
    }

    // 点评内容
    public function getRemarkAttr($value, $data)
    {
        return [
            'content' => $data['remark_content'],
            'resources' => $data['remark_files'] ? array_map(
                function ($array) {
                    if (isset($array['path'])) {
                        $array['url'] = Upload::getFileUrl($array['path']);
                    }
                    return $array;
                },
                $data['remark_files']
            ) : $data['remark_files'],
            'rank' => $data['rank'],
        ];
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
        return array_filter(array_map(function ($array) {
            return Arr::only($array, ['id', 'source', 'duration', 'name', 'path']);
        }, $value));
    }

    /**
     * 获取发布附件数量
     * @param $value
     * @param $data
     */
    public function getFilesAttr($value, $data)
    {
        $res = is_array($data['resources']) ? $data['resources'] : json_decode($data['resources'], true);
        return $data['resources'] ? count($res) : 0;
    }

    public function searchSubmitsAttr($query, $value)
    {
        //未提交
        if (self::STUDENT_UNSUBMIT_STATUS == $value) {
            $query->where(function ($query) {
                $query->where('__TABLE__.submit_time', 0)->whereOr('is_pass', 0);
            });
        }

        //已提交
        if (self::STUDENT_SUBMIT_STATUS == $value) {
            $query->where('__TABLE__.submit_time', '<>', 0)->where('is_pass', '<>', 0);
        }
    }

    public function searchInfoAttr($query, $value, $data)
    {
        $query->field('homework_id,student_id student,student_id,submit_time,remark_time,submit_content,submit_files,remark_content,remark_files,rank,read_time,teacher_id')
            ->withJoin(['homework' => ['title', 'content', 'resources', 'submit_way', 'room_id', 'company_id', 'create_time']])
            ->where('homework_id', $data['homework_id'])
            ->where('student_id', $data['student_id'])
            ->append(['status'])
            ->hidden([
                'homework',
                'day',
            ])
            ->withAttr('teacher_id', function ($value, $data) {
                return  $value ? FrontUser::withSearch('teacher')
                    ->where('user_account_id', $value)
                    ->where('company_id', $data['company_id'])
                    ->field('avatar,nickname,sex,userroleid')
                    ->hidden(['sex', 'userroleid'])
                    ->find()
                    : null;
            })
            ->withAttr('student', function ($value, $data) {
                return  $value ? FrontUser::withSearch('student')
                    ->where('id', $value)
                    ->where('company_id', $data['company_id'])
                    ->field('avatar,nickname,sex,userroleid')
                    ->hidden(['sex', 'userroleid'])
                    ->find()
                    : null;
            });
    }

    public function getIsPassAttr()
    {
        return $this->getAttr('records')->last()['is_pass'] ?? 1;
    }

    public function searchListAttr($query, $value, $data)
    {
        $this->searchDefaultAttr($query, $value, $data);

        $query->field('__TABLE__.id,__TABLE__.is_pass')
            ->with(['records' => function ($query) {
                $query->field('is_pass,homework_record_id')
                    ->order('id', 'desc')
                    ->withLimit(1);
            }])
            ->append(['status', 'files'])
            ->hidden([
                'records', 'homework', 'id', 'submit_files', 'submit_content',
                'resources', 'remark_time', 'content', 'submit_way', 'company_id'
            ]);
    }

    /**
     * 获取详情远程的各种资源
     * @param $data
     */
    public function getFile($data)
    {
        if (!$data) return $data;

        if (!isset(request()->user['company_id'])) {
            request()->user = array_merge(
                request()->user,
                ['company_id' => $data['company_id']]
            );
        }

        $resources = is_array($data['resources']) ? $data['resources'] : ($data['resources'] ? json_decode($data['resources'], true) : []);
        $submit_files = is_array($data['submit_files']) ? $data['submit_files'] : ($data['submit_files'] ? json_decode($data['submit_files'], true) : []);
        $remark_files = is_array($data['remark_files']) ? $data['remark_files'] : ($data['remark_files'] ? json_decode($data['remark_files'], true) : []);

        $idList = array_merge($resources, $submit_files, $remark_files);

        $data['answer']['content'] = $data['submit_content'];
        $data['answer']['submit_time'] = $data['submit_time'];
        $data['remark']['rank'] = $data['rank'];
        $data['remark']['content'] = $data['remark_content'];
        $data['remark']['remark_time'] = $data['remark_time'];
        $data['remark']['teacher'] = null;
        if ($data['teacher_id']) {
            $data['teacher_id']['name'] = $data['teacher_id']['nickname'];
            $data['remark']['teacher'] = $data['teacher_id'];
        }

        unset($data['resources'], $data['submit_time'], $data['teacher_id'], $data['remark_time'], $data['submit_files'], $data['remark_files'], $data['rank'], $data['company_id'], $data['submit_content'], $data['remark_content']);

        //区分本地和网盘类型
        $cloudList = $localList = [];
        //统计时长
        $cloudMap = $localMap = [];

        $oldData = [];

        foreach ($idList as $idTemp) {

            if (isset($idTemp['source'])) {
                if ($idTemp['source'] == File::LOCAL_TYPE) {
                    $localList[] = $idTemp;
                    $localMap[$idTemp['id']] = $idTemp;
                } else {
                    $cloudList[] = $idTemp;
                    $cloudMap[$idTemp['id']] = $idTemp;
                }
            } else {
                $oldData[] = $idTemp;
            }
        }

        $data['resources'] = $data['answer']['resources'] = $data['remark']['resources'] = [];

        $data['answer']['student'] = ['name' => $data['student']['nickname'], 'avatar' => $data['student']['avatar']];
        unset($data['student']);

        $fileModel = new File;
        $cloudRes  = $fileModel->getCloudFile(array_column($cloudList, 'id'), $cloudMap);
        $localRes  = $fileModel->getLocalFile(array_column($localList, 'id'), $localMap);

        //发布资源
        foreach ($resources as $r) {

            if (isset($r['source'])) {
                if ($r['source'] == File::LOCAL_TYPE) {
                    foreach ($localRes as $lr) {
                        if ($lr['id'] == $r['id']) {
                            $data['resources'][] = $lr;
                        }
                    }
                } else {
                    foreach ($cloudRes as $cr) {
                        if ($cr['id'] == $r['id']) {
                            $data['resources'][] = $cr;
                        }
                    }
                }
            } else {
                $r['url'] = Upload::getFileUrl($r['path']);
                $data['resources'][] = $r;
            }
        }

        //作业资源
        foreach ($submit_files as $r) {
            if (isset($r['source'])) {
                if ($r['source'] == File::LOCAL_TYPE) {
                    foreach ($localRes as $lr) {
                        if ($lr['id'] == $r['id']) {
                            $data['answer']['resources'][] = $lr;
                        }
                    }
                } else {
                    foreach ($cloudRes as $cr) {
                        if ($cr['id'] == $r['id']) {
                            $data['answer']['resources'][] = $cr;
                        }
                    }
                }
            } else {
                $r['url'] = Upload::getFileUrl($r['path']);
                $data['answer']['resources'][] = $r;
            }
        }

        //点评资源
        foreach ($remark_files as $r) {
            if (isset($r['source'])) {
                if ($r['source'] == File::LOCAL_TYPE) {
                    foreach ($localRes as $lr) {
                        if ($lr['id'] == $r['id']) {
                            $data['remark']['resources'][] = $lr;
                        }
                    }
                } else {
                    foreach ($cloudRes as $cr) {
                        if ($cr['id'] == $r['id']) {
                            $data['remark']['resources'][] = $cr;
                        }
                    }
                }
            } else {
                $r['url'] = Upload::getFileUrl($r['path']);
                $data['answer']['resources'][] = $r;
            }
        }

        return  $data;
    }

    /**
     * 获取V1版本资源
     * @param $data
     */
    public function getV1File($data)
    {
        if (!$data) return $data;

        if (!isset(request()->user['company_id'])) {
            request()->user = array_merge(
                request()->user,
                ['company_id' => $data['company_id']]
            );
        }

        $resources = is_array($data['resources']) ? $data['resources'] : ($data['resources'] ? json_decode($data['resources'], true) : []);
        $submit_files = is_array($data['answer']['resources']) ? $data['answer']['resources'] : ($data['answer']['resources'] ? json_decode($data['answer']['resources'], true) : []);
        $remark_files = is_array($data['remark']['resources']) ? $data['remark']['resources'] : ($data['remark']['resources'] ? json_decode($data['remark']['resources'], true) : []);

        $idList = array_merge($resources, $submit_files, $remark_files);

        //区分本地和网盘类型
        $cloudList = $localList = [];

        foreach ($idList as $idTemp) {
            if (isset($idTemp['source'])) {
                if ($idTemp['source'] == File::LOCAL_TYPE) {
                    $localList[] = $idTemp;
                    $localMap[$idTemp['id']] = $idTemp;
                } elseif ($idTemp['source'] == File::CLOUD_TYPE) {
                    $cloudList[] = $idTemp;
                    $cloudMap[$idTemp['id']] = $idTemp;
                }
            }
        }

        $idResources = array_column($resources, 'id');
        $idAnswer = array_column($submit_files, 'id');
        $idRemark = array_column($remark_files, 'id');
        $fileModel = new File;
        $cloudRes  = $fileModel->getCloudFile(array_column($cloudList, 'id'));
        $localRes  = $fileModel->getLocalFile(array_column($localList, 'id'));

        $data['resources'] =  $data['answer']['resources'] = $data['answer']['resources']  = [];
        //作业
        if ($idResources) {
            foreach ($resources as $r) {
                if (isset($r['source'])) {
                    if ($r['source'] == File::LOCAL_TYPE) {
                        foreach ($localRes as $lr) {
                            if ($lr['id'] == $r['id']) {
                                $data['resources'][] = $lr;
                            }
                        }
                    } else {
                        foreach ($cloudRes as $cr) {
                            if ($cr['id'] == $r['id']) {
                                $data['resources'][] = $cr;
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($resources as $r) {
                if (!isset($r['url'])) {
                    $r['url'] = Upload::getFileUrl($r['path']);
                }
                $data['resources'][] = $r;
            }
        }

        //作答资源
        if ($idAnswer) {
            foreach ($submit_files as $r) {
                if (isset($r['source'])) {
                    if ($r['source'] == File::LOCAL_TYPE) {
                        foreach ($localRes as $lr) {
                            if ($lr['id'] == $r['id']) {
                                $data['answer']['resources'][] = $lr;
                            }
                        }
                    } else {
                        foreach ($cloudRes as $cr) {
                            if ($cr['id'] == $r['id']) {
                                $data['answer']['resources'][] = $cr;
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($submit_files as $r) {
                if (!isset($r['url'])) {
                    $r['url'] = Upload::getFileUrl($r['path']);
                }
                $data['answer']['resources'][] = $r;
            }
        }

        //点评资源
        if ($idRemark) {
            foreach ($remark_files as $r) {
                if (isset($r['source'])) {
                    if ($r['source'] == File::LOCAL_TYPE) {
                        foreach ($localRes as $lr) {
                            if ($lr['id'] == $r['id']) {
                                $data['remark']['resources'][] = $lr;
                            }
                        }
                    } else {
                        foreach ($cloudRes as $cr) {
                            if ($cr['id'] == $r['id']) {
                                $data['remark']['resources'][] = $cr;
                            }
                        }
                    }
                }
            }
        } else {
            foreach ($remark_files as $r) {
                if (!isset($r['url'])) {
                    $r['url'] = Upload::getFileUrl($r['path']);
                }
                $data['remark']['resources'][] = $r;
            }
        }
        return  $data;
    }
}
