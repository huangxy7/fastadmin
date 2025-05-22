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

    public function token(){
        $token = get_token(true);
        $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . 1 . ',"page_size":' . 1 . ',"order_type":"unship"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
        $this->success('请求成功');
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
                        $model->where('id', $data['id'])->update(['system_status' => 1, 'system_status_name' => "处理中","error_time"=>date('Y-m-d H:i:s', time() + 240)]);
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
            $this->error('暂无数据2');
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
            'system_status' => 3,
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

    public function spiders()
    {
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = 'unship';
        $params = $this->request->param();
        $content = $params['content'] ?? "";
         if (!$content) {
            $response = Response::create(["status" => "error1"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $content = json_decode(htmlspecialchars_decode($content),true);
        $order_id = $content['message']['order_id'] ?? false;
        if($content['type'] != "weidian.order.already_payment"){//不是待发货不处理
            $response = Response::create(["status" => "success"], 'json', 0)->header(200);
            \think\Log::info('spiders_error不是待发货不处理,order_id:'.$order_id);
            throw new HttpResponseException($response);
        }
        if (!$order_id) {
            \think\Log::info('spiders_error订单不存在');
            $response = Response::create(["status" => "error2."], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
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
            if ($orderDetail['status']['status_code'] !== 0) {
                \think\Log::info('spiders_error订单api返回不对' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE)."url:".'https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
                 $response = Response::create(["status" => "error3"], 'json', 0)->header(500);
                  throw new HttpResponseException($response);
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

        $response = Response::create(["status" => "success"], 'json', 0)->header(200);
        throw new HttpResponseException($response);
    }

    public function notification()
    {
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = 'unship';
        $params = $this->request->param();
        $content = $params['content'] ?? "";
         if (!$content) {
            $response = Response::create(["status" => "error1"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $content = json_decode(htmlspecialchars_decode($content),true);
        $order_id = $content['message']['order_id'] ?? false;
        if($content['type'] != "weidian.order.already_payment"){//不是待发货不处理
            $response = Response::create(["status" => "success"], 'json', 0)->header(200);
            \think\Log::info('spiders_error不是待发货不处理,order_id:'.$order_id);
            throw new HttpResponseException($response);
        }
        if (!$order_id) {
            \think\Log::info('spiders_error订单不存在');
            $response = Response::create(["status" => "error2."], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
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
            if ($orderDetail['status']['status_code'] !== 0) {
                \think\Log::info('spiders_error订单api返回不对' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE)."url:".'https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
                 $response = Response::create(["status" => "error3"], 'json', 0)->header(500);
                  throw new HttpResponseException($response);
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

        $response = Response::create(["status" => "success"], 'json', 0)->header(200);
        throw new HttpResponseException($response);
    }
}

