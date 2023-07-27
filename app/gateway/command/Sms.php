<?php

declare(strict_types=1);

namespace app\gateway\command;

use app\common\lib\sms\Rule;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class Sms extends Command
{
    use Rule;
    protected function configure()
    {
        // 指令配置
        $this->setName('sms')
            ->addArgument('mobile', Argument::REQUIRED, '带区号的手机号')
            ->setDescription('模拟发送短信验证码');
    }

    protected function execute(Input $input, Output $output)
    {
        $mobile = $input->getArgument('mobile');
        $code = $this->createVerificationCode($mobile, config('sms.business_type'), 10 * 60);

        $output->writeln((string)$code);
    }
}
