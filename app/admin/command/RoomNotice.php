<?php
declare (strict_types=1);

namespace app\admin\command;

use app\common\service\TaskManage;
use app\common\service\room\RoomStudentsMsg;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Queue;
use app\common\job\TencentLog;

class RoomNotice extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('room_notice')
            ->setDescription('每分钟生产教室报告通知');
    }

    protected function execute(Input $input, Output $output)
    {


    }
}