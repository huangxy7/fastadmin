<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Refretoken extends Command
{
    protected function configure()
    {
        $this->setName('refretoken')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        $Logic = new \app\common\Logic\doudian();
        $Logic->refreshToken();
    }
}