<?php

/**
 * Created by PhpStorm.
 * User: tengteng
 * Date: 2021-08-11
 * Time: 10:50
 */

namespace app\home\controller\v1;

use app\home\controller\Base;
use app\home\model\saas\FrontUser;
use app\home\model\saas\Room;
use app\home\model\saas\StudentGroup;

class Students extends Base
{

    public function index()
    {
        Room::findOrFail($this->param['lesson_id']);
        unset($this->param['lesson_id']);
        return $this->success($this->searchList(FrontUser::class, [], false));
    }

    public function allStudentGroup()
    {
        Room::findOrFail($this->param['lesson_id']);
        $models = StudentGroup::withSearch(['enable'])->field('id typeid,typename')->select();
        return $this->success($models);
    }
}
