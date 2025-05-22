<?php

namespace app\common\Logic;

use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;


class weidian
{
    private $model;

    public function __construct()
    {
        $this->model = new \app\admin\model\Payment;
    }

    function lock($id)
    {
        Cache::set('cache_weidian' . $id, 1, 60);
    }

    function isLock($id): bool
    {
        if (cache('cache_weidian' . $id)) {
            return true;
        } else {
            return false;
        }
    }

    public function getWaitHandleOrders(): array
    {
        $getArrayKey = 'get_array_weidian';
        try {
            $get_data = \app\common\model\Config::where('name', 'get_data')->find();
            if ($get_data['value'] == 0) {
                return ['status' => 0, 'message' => '获取数据功能已关闭'];
            }
            $get_value = \app\common\model\Config::where('name', 'get_value')->find();
            while (true) {
                $dataArray = cache($getArrayKey);
                if (!$dataArray) {
                    $dataArray = $this->model->where('order_type', 'unship')->where('system_status', $this->model::SYSTEM_STATUS_WAIT)->field('id')->where('total', "<=", $get_value['value'])->limit(50)->select();
//                \think\Log::info('info_getPaymentList_redis_array' . json_encode($dataArray, JSON_UNESCAPED_UNICODE));
                    if (!$dataArray) {
                        return ['status' => 0, 'message' => '暂无数据'];
                    }
                    cache($getArrayKey, $dataArray, ['expire' => 60]);
                }
                foreach ($dataArray as $k => $v) {
                    if (!$this->isLock($v['id'])) {
                        $this->lock($v['id']);
                        Db::startTrans();
                        $data = $this->model->where('id', $v['id'])->where('system_status', $this->model::SYSTEM_STATUS_WAIT)->field('id,order_id,time,seller_note,total,order_json')->lock(true)->find();
                        if (!$data) {
                            Db::rollback();
                        } else {
                            $this->model->where('id', $data['id'])->update(['system_status' => $this->model::SYSTEM_STATUS_IN_PRECESS, 'system_status_name' => "处理中", "error_time" => date('Y-m-d H:i:s', time() + 240)]);
                            Db::commit();
                        }
                        unset($dataArray[$k]);
                        $updateData = $dataArray;
//                    \think\Log::info('info_getPaymentList_redis' . json_encode($updateData, JSON_UNESCAPED_UNICODE));
                        cache($getArrayKey, $updateData, ['expire' => 60]);
                        $order_json = json_decode($data['order_json'], true);
                        $res        = [
                            'order_id'    => $data['order_id'],
                            'partner'     => "weidian",
                            'time'        => $data['time'],
                            'seller_note' => $data['seller_note'],
                            'total'       => $data['total'],
                            'items'       => $order_json['items'],
                            'customInfos' => $order_json['customInfos'],
                        ];
                        return ['status' => 1, 'message' => '获取数据成功', 'data' => $res];
                    }
                }
                return ['status' => 0, 'message' => '暂无数据2'];
            }
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            Log::error('Error fetching wait handle orders: ' . $e->getMessage());
            return [];
        }
    }
    public function handlePost($order_id, $name, $device, $accountName): bool
    {
        $data = $this->model->where('order_id', $order_id)->find();
        if (!$data) {
            Log::error('Order not found: ' . $order_id);
            return false;
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
        $this->model->where('order_id', $order_id)->update($update);
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
                Log::error('Weidian order deliver failed: ' . $result['status']['status_reason']);
                return false;
            }
        }
        return true;
    }
}