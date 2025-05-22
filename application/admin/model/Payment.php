<?php

namespace app\admin\model;

use think\Model;


class Payment extends Model
{

    // 表名
    protected $name = 'payment';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;
    // 定义字段类型
    const SYSTEM_STATUS_WAIT = 0;//未处理
    const SYSTEM_STATUS_IN_PRECESS = 1;//处理中
    const SYSTEM_STATUS_ = 3;//？？
}
