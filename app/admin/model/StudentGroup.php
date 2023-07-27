<?php

declare(strict_types=1);

namespace app\admin\model;

use app\Request;

class StudentGroup extends Base
{
    protected $deleteTime = false;

    const ENABLE = 1;

    const DISABLE = 0;

    public function users()
    {
        return $this->belongsToMany(FrontUser::class, 'frontuser_group', '', 'group_id');
    }

    public function searchEnableAttr($query)
    {
        $query->where('state', self::ENABLE);
    }

    public function searchDefaultAttr($query, $value, $data)
    {
        if (isset($data['state'])) {
            if (in_array($data['state'], [self::ENABLE, self::DISABLE])) {
                $query->where('state', $data['state']);
            }
        }

        if (isset($data['typename'])) {
            $query->where('typename', 'like', "%" . $data['typename'] . "%");
        }

        $query->field('*,id typeid')->append(['createtime'])->withCount(['users' => 'stu_num']);
    }

    /**
     * 新增分组
     * @param array $names 分组名称数组
     */
    public function addGroup($names, Request $request)
    {
        $dataInsert = [];
        foreach ($names as $name) {
            $dataInsert[] = [
                'typename' => $name,
                'company_id' => $request->user['company_id'],
                'create_time' => time(),
                'userroleid' => FrontUser::STUDENT_TYPE,
            ];
        }

        $this->insertAll($dataInsert);
    }

    public function getCreatetimeAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['create_time']);
    }
}
