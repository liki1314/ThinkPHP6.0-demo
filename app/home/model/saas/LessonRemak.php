<?php
declare(strict_types=1);

namespace app\home\model\saas;


class LessonRemak extends Base
{
    // 设置json类型字段
    protected $json = ['remark_content'];

    protected $pk =['room_id','student_id'] ;

    protected $deleteTime = false;
}