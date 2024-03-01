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
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = ['unship'];
        $params = $this->request->param();
        $content = $params['content'] ?? "";
        // $content = '{"message":{"customInfos":[{"format":"text","name":"抖音号","value":"lss688888"}],"note":"","group_status":"-1","is_wtt_order":"null","express_fee":"0.00","argue_flag":0,"is_wei_order":"0","express":"","express_no":"","total":"107.80","seller_name":"直播充值服务商","status_ori":"20","is_over_sold":"0","supplier_seller_id":0,"price":"107.80","refund_status_ori":"0","seller_phone":"18540373866","user_phone":"15735935348","original_total_price":"107.80","last_income":"107.80","order_type":"2","seller_id":"1755623969","quantity":"1","confirm_expire":"","status_desc":"待发货","buyer_info":{"phone":"15735935348","idCardNo":"","name":"15735935348","buyer_id":"1842809735"},"f_seller_id":"0","is_close":0,"is_cpn_order":"0","pay_time":"2024-03-01 17:47:07","express_fee_num":"0.00","express_note":"","weixin":"","order_type_desc":"直接到账","add_time":"2024-03-01 17:47:00","items":[{"sku_merchant_code":"","img":"https://si.geilicdn.com/wdseller1768534306-7ea00000018ac06ff3430a2313a2_800_800.jpg?w=110&h=110&cp=1","merchant_code":"DY_98","quantity":"1","total_price":"107.80","item_id":"6649359972","is_delivered":0,"deliver_id":"0","refund_info":{"refund_status":"","item_id":"","item_sku_id":"","refund_kind":"","refund_fee":"","refund_item_fee":"","can_refund":"1","refund_express_fee":""},"item_name":"980钻石充值，本店拒绝刷单，任何别人让你付款的都是骗子，一旦充值无法退款，请不要为他人充值。","sku_id":"0","sub_order_id":641054394053165,"url":"https://weidian.com/item.html?itemID=6649359972","sku_title":"","price":"107.80","deliver_status_desc":"待发货","id":641054394053165,"can_deliver":1}],"order_id":"824123248136749","express_type":"0","status":"pay"},"shopId":1755623969,"type":"weidian.order.already_payment"}';

        if (!$content) {
            $response = Response::create(["status" => "error"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $content = json_decode($content,true);
        $order_id = $content['message']['order_id'] ?? false;
        if (!$order_id) {
            $response = Response::create(["status" => "error."], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        try {
            $paymentModel = new \app\admin\model\Payment;
            $isset = $paymentModel->where('order_id', $order_id)->find();
            $token = get_token();
            //call api 获取订单详情
            beginDetail:
            $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
            $orderDetail = json_decode($orderDetail, true);
            if ($orderDetail['status']['status_code'] === 10013) {
                $token = get_token(true);
                goto beginDetail;
            }
            // var_export($orderDetail);die;
            if ($orderDetail['status']['status_code'] !== 0) {
                \think\Log::info('info_getPaymentList_for_data_get_detail_error' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE));;
            }
            $resOrderDetail = $orderDetail['result'];
            //check isset order_id
            $params = [
                'order_id'           => $resOrderDetail['order_id'],
                'total'              => $resOrderDetail['total'],
                'time'               => $resOrderDetail['add_time'],
                'seller_note'        => $resOrderDetail['seller_note'],
                'order_json'         => json_encode($resOrderDetail, JSON_UNESCAPED_UNICODE),
                'system_status'      => 0,
                'system_status_name' => '未处理',
                'order_type'         => $order_type_array,
            ];
            if ($isset) {
                unset($params['order_id']);
                unset($params['system_status']);
                unset($params['system_status_name']);
                $paymentModel->where('id', $isset['id'])->update($params);
            } else {
                $paymentModel->create($params);
            }

        } catch (Exception $e) {
            \think\Log::error('install_payment_list_error' . json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
            $response = Response::create(["status" => "error2"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $response = Response::create(["status" => "success"], 'json', 0)->header(200);
        throw new HttpResponseException($response);
    }

}

