<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;

class Exceptiontime extends Command
{
    protected function configure()
    {
        $this->setName('exceptiontime')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        $data = \app\admin\model\Payment::where('system_status_name','开始处理')->select();
        $time = date('Y-m-d H:i:s',time());
        foreach ($data as $value){
            if($value['error_time'] < $time){
                \app\admin\model\Payment::update(['system_status_name' => "处理异常"],['id'=>$value['id']]);
            }
        }
    }
}