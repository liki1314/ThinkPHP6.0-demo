<?php

declare(strict_types=1);

namespace app\home\controller\v1;

use app\home\model\saas\Course as ModelCourse;

class Course extends \app\home\controller\Base
{
    public function index()
    {
        return $this->success($this->searchList(ModelCourse::class));
    }


    public function info($id)
    {
        $info = ModelCourse::withSearch(['detail'])->findOrFail($id);
        return $this->success($info);
    }
}
