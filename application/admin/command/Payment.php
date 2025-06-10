<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Exception;

class Payment extends Command
{
    protected function configure()
    {
        $this->setName('payment')->setDescription('Here is the remark ');
    }

    protected function execute(Input $input, Output $output)
    {
        $page = cache("payment_page");
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = ['unship'];
        $pageSize = 50;
        $allTotal = 1;
        if (!$page) {
            $page = 1;
        }
        try {
            $paymentModel = new \app\admin\model\Payment;
            $token = get_token();
            foreach ($order_type_array as $order_type){
                $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"'.$order_type.'"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
//            $result = '{"status":{"status_code":0,"status_reason":"OK"},"result":{"order_num":1,"orders":[{"buyer_info":{"address":"sUEdcjy0a57OvgpOqbqmEEnBsRonrikuVgl33qki/XA=","name":"sUEdcjy0a57OvgpOqbqmEEnBsRonrikuVgl33qki/XA=","phone":"sUEdcjy0a57OvgpOqbqmEEnBsRonrikuVgl33qki/XA="},"buyer_note":"","express_fee":"0.0","express_no":"","express_type":"0","f_seller_id":"340173424","group_status":-1,"img":"https://si.geilicdn.com/ch1768534306-7bd90000018ac067eddb0a231447_800_800.jpg?w=110&h=110&cp=1","is_wei_order":0,"order_id":"823701165911577","refundStatus":0,"seller_note":"","status":50,"time":"2024-01-30 23:19:40","total":"16.0","update_time":"2024-02-06 23:19:55"}],"total_num":61006},"aeskey_version":1}';
                $result = json_decode($result, true);
                if ($result['status']['status_code'] === 10013) {
                    $token = get_token(true);
                    $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"'.$order_type.'"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
                    $result = json_decode($result, true);
                }
                // \think\Log::info('info_getPaymentList_' . json_encode($result, JSON_UNESCAPED_UNICODE));
                if ($result['status']['status_code'] === 0) {
                    $allTotal = $result['result']['total_num'];
                    $data = $result['result']['orders'];
                    foreach ($data as $value) {
                        $isset = $paymentModel->where('order_id', $value['order_id'])->find();
                        //call api 获取订单详情
                        $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
                        $orderDetail = json_decode($orderDetail, true);
                        if ($result['status']['status_code'] === 10013) {
                            $token = get_token(true);
                            $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
                            $orderDetail = json_decode($orderDetail, true);
                        }
                        if ($orderDetail['status']['status_code'] !== 0) {
                            \think\Log::info('info_getPaymentList_for_data_get_detail_error' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE),['url'=>'https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}']);
                            continue;
                        }
                        $resOrderDetail = $orderDetail['result'];
                        //check isset order_id
                        $params = [
                            'buyer_info'   => json_encode($value['buyer_info']),
                            'buyer_note'   => $value['buyer_note'],
                            'express_fee'  => $value['express_fee'],
                            'express_no'   => $value['express_no'],
                            'express_type' => $value['express_type'],
                            'f_seller_id'  => $value['f_seller_id'],
                            'group_status' => $value['group_status'],
                            'img'          => $value['img'],
                            'is_wei_order' => $value['is_wei_order'],
                            'order_id'     => $value['order_id'],
                            'refundStatus' => $value['refundStatus'],
                            'seller_note'  => $value['seller_note'],
                            'status'       => $value['status'],
                            'time'         => $value['time'],
                            'total'        => $value['total'],
                            'update_time'  => $value['update_time'],
                            'system_status' => 0,
                            'system_status_name' => '未处理',
                            'order_type' => $order_type,
                            'order_json'   => json_encode($resOrderDetail, JSON_UNESCAPED_UNICODE),//商品总价格，不包含运费
                        ];
                        if($isset){
                            unset($params['order_id']);
                            unset($params['system_status']);
                            unset($params['system_status_name']);
                            $paymentModel->where('order_id', $value['order_id'])->update($params);
                        }else{
                            $result = $paymentModel->create($params); 
                        }
                 
                        if ($result === false) {
                            \think\Log::error('install_payment_list_data_error' . json_encode($params, JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
            }

        } catch (Exception $e) {
//            $this->error($e->getMessage());
            \think\Log::error('install_payment_list_error' . json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
        }
        $allPage = round($allTotal / $pageSize) + 1;
        if ($allPage > $page) {
            cache("payment_page", ++$page);
        } else {
            cache("payment_page", 1);
        }
        \think\Log::info('info_getPaymentList_' . json_encode(['total_num' => $allTotal, 'page' => $page], JSON_UNESCAPED_UNICODE));
        $output->info("Successed!");
    }
}