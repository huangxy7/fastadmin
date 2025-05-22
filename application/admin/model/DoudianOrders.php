<?php

namespace app\admin\model;

use think\Model;


class DoudianOrders extends Model
{

    // 表名
    protected $name = 'doudian_orders';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    //SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
    //0：订单充值中，1：订单充值成功，2 订单充值失败,3:平台通知订单取消充值，4：订单超时取消充值
    const STATUS_NAME_SUCCESS = 'SUCCESS';
    const STATUS_NAME_FAILED = 'FAILED';
    const STATUS_NAME_IN_PROCESS = 'IN_PROCESS';
    const STATUS_IN_PROCESS = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;
    const STATUS_CANCEL = 3;
    const STATUS_TIMEOUT = 4;

    // 定义字段类型
    const SYSTEM_STATUS_WAIT = 0;//未处理
    const SYSTEM_STATUS_IN_PRECESS = 1;//处理中
    const SYSTEM_STATUS_ = 3;//？？
}
