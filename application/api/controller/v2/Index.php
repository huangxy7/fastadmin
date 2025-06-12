<?php

namespace app\api\controller\v2;

use app\common\controller\Api;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
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

    public function doudianToken()
    {
        $doudianLogic = new \app\common\Logic\doudian();
        $res = $doudianLogic->token();
        $this->success('请求成功', $res);
    }
    public function token()
    {
        $token  = get_token(true);
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
        $resData      = [
            'status'  => 0,
            'message' => '没有待处理订单',
        ];
        $weidianLogic = new \app\common\Logic\weidian();
        $doudianLogic = new \app\common\Logic\doudian();
        //随机选取一个
        $round = rand(1, 2);
        if ($round == 1) {
            $resData = $doudianLogic->getWaitHandleOrders();
            if (!$resData && !config('doudian.debug')) {
                $resData = $weidianLogic->getWaitHandleOrders();
            }
        } else {
            if (!config('doudian.debug')) {
                $resData = $weidianLogic->getWaitHandleOrders();
                if (!$resData) {
                    $resData = $doudianLogic->getWaitHandleOrders();
                }
            }
        }
        if ($resData['status'] == 0) {
            $this->error($resData['message']);
        }
        $this->success('请求成功', $resData['data']);
    }

    public function post()
    {
        $param       = $this->request->param();
        $name        = $param['name'] ?? "";
        $order_id    = $param['order_id'] ?? "";
        $device      = $param['device'] ?? "";
        $accountName = $param['accountName'] ?? "";
        $partner     = $param['partner'];
        if (!$order_id) {
            $this->error('参数错误, order_id不能为空');
        }
        if (!$partner) {
            $this->error('参数错误, partner不能为空');
        }
        if ($partner == "weidian") {
            $logic = new \app\common\Logic\weidian();
            if (!$logic->handlePost($order_id, $name, $device, $accountName)) {
                $this->error('处理失败');
            }
        } elseif ($partner == "doudian") {
            $logic = new \app\common\Logic\doudian();
            if (!$logic->handlePost($order_id, $name, $device, $accountName)) {
                $this->error('处理失败');
            }
        } else {
            $this->error('参数错误, partner参数错误');
        }

        $this->success('请求成功');
    }

    public function spiders()
    {
        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
        $order_type_array = 'unship';
        $params           = $this->request->param();
        $content          = $params['content'] ?? "";
        if (!$content) {
            $response = Response::create(["status" => "error1"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $content  = json_decode(htmlspecialchars_decode($content), true);
        $order_id = $content['message']['order_id'] ?? false;
        if ($content['type'] != "weidian.order.already_payment") {//不是待发货不处理
            $response = Response::create(["status" => "success"], 'json', 0)->header(200);
            \think\Log::info('spiders_error不是待发货不处理,order_id:' . $order_id);
            throw new HttpResponseException($response);
        }
        if (!$order_id) {
            \think\Log::info('spiders_error订单不存在');
            $response = Response::create(["status" => "error2."], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $paymentModel = new \app\admin\model\Payment;
        $isset        = $paymentModel->where('order_id', $order_id)->find();
        $token        = get_token();
        //call api 获取订单详情
        beginDetail:
        $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
        $orderDetail = json_decode($orderDetail, true);
        if ($orderDetail['status']['status_code'] === 10013) {
            $token = get_token(true);
            goto beginDetail;
        }
        if ($orderDetail['status']['status_code'] !== 0) {
            \think\Log::info('spiders_error订单api返回不对' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE) . "url:" . 'https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
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
        $params           = $this->request->param();
        $content          = $params['content'] ?? "";
        if (!$content) {
            $response = Response::create(["status" => "error1"], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $content  = json_decode(htmlspecialchars_decode($content), true);
        $order_id = $content['message']['order_id'] ?? false;
        if ($content['type'] != "weidian.order.already_payment") {//不是待发货不处理
            $response = Response::create(["status" => "success"], 'json', 0)->header(200);
            \think\Log::info('spiders_error不是待发货不处理,order_id:' . $order_id);
            throw new HttpResponseException($response);
        }
        if (!$order_id) {
            \think\Log::info('spiders_error订单不存在');
            $response = Response::create(["status" => "error2."], 'json', 500)->header(['code' => 500]);
            throw new HttpResponseException($response);
        }
        $paymentModel = new \app\admin\model\Payment;
        $isset        = $paymentModel->where('order_id', $order_id)->find();
        $token        = get_token();
        //call api 获取订单详情
        beginDetail:
        $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
        $orderDetail = json_decode($orderDetail, true);
        if ($orderDetail['status']['status_code'] === 10013) {
            $token = get_token(true);
            goto beginDetail;
        }
        if ($orderDetail['status']['status_code'] !== 0) {
            \think\Log::info('spiders_error订单api返回不对' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE) . "url:" . 'https://api.vdian.com/api?param={"order_id":"' . $order_id . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
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

