<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\HttpResponseException;
use think\Response;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }


    function lock($id)
    {
        Cache::set('cache' . $id, 1, 60);
    }

    function isLock($id): bool
    {
        if (cache('cache' . $id)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function get()
    {
        $get_data = \app\common\model\Config::where('name', 'get_data')->find();
        if ($get_data['value'] == 0) {
            $this->error('已停止处理订单');
        }
        $get_value = \app\common\model\Config::where('name', 'get_value')->find();
        $model = new \app\admin\model\Payment;
        while (true) {
            $dataArray = cache('get_array');
            if (!$dataArray) {
                $dataArray = $model->where('order_type', 'unship')->where('system_status', 0)->field('id')->where('total',"<=",$get_value['value'])->limit(50)->select();
//                \think\Log::info('info_getPaymentList_redis_array' . json_encode($dataArray, JSON_UNESCAPED_UNICODE));
                if (!$dataArray) {
                    $this->error('暂无数据');
                }
                cache('get_array', $dataArray, ['expire' => 60]);
            }
            foreach ($dataArray as $k => $v) {
                if (!$this->isLock($v['id'])) {
                    $this->lock($v['id']);
                    Db::startTrans();
                    $data = $model->where('id', $v['id'])->where('system_status', 0)->field('id,order_id,time,seller_note,total,order_json')->lock(true)->find();
                    if (!$data) {
                        Db::rollback();
                    } else {
                        $model->where('id', $data['id'])->update(['system_status' => 1, 'system_status_name' => "处理中"]);
                        Db::commit();
                    }
                    unset($dataArray[$k]);
                    $updateData = $dataArray;
//                    \think\Log::info('info_getPaymentList_redis' . json_encode($updateData, JSON_UNESCAPED_UNICODE));
                    cache('get_array', $updateData, ['expire' => 60]);
                    $order_json = json_decode($data['order_json'], true);
                    $res = [
                        'order_id'    => $data['order_id'],
                        'time'        => $data['time'],
                        'seller_note' => $data['seller_note'],
                        'total'       => $data['total'],
                        'items'       => $order_json['items'],
                        'customInfos' => $order_json['customInfos'],
                    ];
                    $this->success('请求成功', $res);
                }
            }
        }
    }

    public function post()
    {
        $param = $this->request->param();
        $name = $param['name'] ?? "";
        $order_id = $param['order_id'] ?? "";
        $device = $param['device'] ?? "";
        $accountName = $param['accountName'] ?? "";
         if (!$order_id) {
            $this->error('参数错误');
        }
        $model = new \app\admin\model\Payment;
        $data = $model->where('order_id', $order_id)->find();
        if (!$data) {
            $this->error('暂无数据');
        }
        $update = [
            'system_status'      => 3,
        ];
        if($name){
            $update['system_status_name'] = $name;
        }
        if($device){
            $update['device'] = $device;
        }
        if($accountName){
            $update['accountName'] = $accountName;
        }
        if ($name == '开始处理') {
            $update['error_time'] = date('Y-m-d H:i:s', time() + 180);
        }
        $model->where('order_id', $order_id)->update($update);
        if ($name == '开始处理') {
            $token = get_token();
            $result = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $order_id . '","express_type":"0"}&public={"method":"vdian.order.deliver","access_token":"' . $token . '","version":"1.0","format":"json"}');
            $result = json_decode($result, true);
            if ($result['status'] == 10013) {
                $token = get_token(true);
                $result = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $order_id . '","express_type":"0"}&public={"method":"vdian.order.deliver","access_token":"' . $token . '","version":"1.0","format":"json"}');
                $result = json_decode($result, true);
            }
            if ($result['status']['status_code'] !== 0) {
                $this->error($result['status']['status_reason']);
            }
        }
        $this->success('请求成功');
    }

    public function spider()
    {
        $page = 1;
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = ['unship'];
        $pageSize = 50;
        $allTotal = 1;
        $allPage = 1;
        try {
            $paymentModel = new \app\admin\model\Payment;
            $token = get_token();
            foreach ($order_type_array as $order_type) {
                while (true) {
                    if ($page > $allPage) {
                        break;
                    }
                    begin:
                    $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"' . $order_type . '"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
                    $result = json_decode($result, true);
                    if ($result['status']['status_code'] === 10013) {
                        $token = get_token(true);
                        goto begin;
                    }
                    if ($result['status']['status_code'] === 0) {
                        $allTotal = $result['result']['total_num'];
                        $data = $result['result']['orders'];
                        $params = [];
                        foreach ($data as $value) {
                            //call api 获取订单详情
                            beginDetail:
                            $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
                            $orderDetail = json_decode($orderDetail, true);
                            if ($orderDetail['status']['status_code'] === 10013) {
                                $token = get_token(true);
                                goto beginDetail;
                            }
                            if ($orderDetail['status']['status_code'] !== 0) {
                                \think\Log::info('info_getPaymentList_for_data_get_detail_error' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE));
                                continue;
                            }
                            $resOrderDetail = $orderDetail['result'];
                            //check isset order_id
                            $params[] = [
                                'buyer_info'         => json_encode($value['buyer_info']),
                                'buyer_note'         => $value['buyer_note'],
                                'express_fee'        => $value['express_fee'],
                                'express_no'         => $value['express_no'],
                                'express_type'       => $value['express_type'],
                                'f_seller_id'        => $value['f_seller_id'],
                                'group_status'       => $value['group_status'],
                                'img'                => $value['img'],
                                'is_wei_order'       => $value['is_wei_order'],
                                'order_id'           => $value['order_id'],
                                'refundStatus'       => $value['refundStatus'],
                                'seller_note'        => $value['seller_note'],
                                'status'             => $value['status'],
                                'time'               => $value['time'],
                                'total'              => $value['total'],
                                'update_time'        => $value['update_time'],
                                'system_status'      => 0,
                                'system_status_name' => '未处理',
                                'order_type'         => $order_type,
                                'order_json'         => json_encode($resOrderDetail, JSON_UNESCAPED_UNICODE),//商品总价格，不包含运费
                            ];
                        }
                        $paymentModel->saveAll($params);
                    }
                    $allPage = round($allTotal / $pageSize) + 1;
                    $page++;
                }
            }
        } catch (Exception $e) {
            \think\Log::error('install_payment_list_error' . json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
        }
        $response = Response::create(["status" => "success"], 'json', 0)->header(200);
        throw new HttpResponseException($response);
    }

}

