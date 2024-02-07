<?php

return [
    'Total'        => '订单总价',
    'Group_status' => '只在待发货列表下生效,用于判断微团购订单是否已成团。其中 1已成团,0 未成团,-1 无意义新增此字段',
    'Update_time'  => '订单更新时间',
    'Is_wei_order' => 'is_wei_order 参数 不传 或者 传=0 时 ,能返回 普通订单、微中心订单、品牌供货订单；is_wei_order 参数=1 时,返回 微中心订单 、品牌供货订单；is_wei_order 参数=2 时,返回 普通订单。',
    'Order_id'     => '订单ID',
    'Buyer_note'   => '买家备注',
    'Time'         => '	
下单时间',
    'Seller_note'  => '卖家备注',
    'F_seller_id'  => '分销商ID',
    'Buyer_info'   => '买家信息',
    'Express_type' => '快递公司编号',
    'Status'       => '订单状态编号,返回值： 10 待付款；20 已付款,待发货；21 部分付款 (例如：定金预售)；30 已发货；31 部分发货；40 已确认收货；50 已完成；60 已关闭;注意：目前订单列表中的status和订单详情中的status输出不一致,请注意区别',
    'Img'          => '商品图URL',
    'Express_no'   => '快递单号',
    'Express_fee'  => '快递费用',
    'Refundstatus' => '退款状态'
];
