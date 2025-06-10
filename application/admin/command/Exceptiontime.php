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
        $time = date('Y-m-d H:i:s',time());
        \app\admin\model\Payment::where('system_status',1)->where('error_time','<',$time)->data(['system_status_name' => "处理异常",'system_status'=>2])->update();
        // $data = \app\admin\model\Payment::where('system_status',1)->where('error_time','<',$time)->select();
        // var_export($data);die;
    }
}