<?php

declare(strict_types=1);

namespace app\home\model\saas;


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
        $query->where('__TABLE__.company_id', request()->user['company_id']);

        $query->field('*,id typeid')->append(['createtime'])->withCount(['users' => 'stu_num']);
    }



    public function getCreatetimeAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['create_time']);
    }
}
