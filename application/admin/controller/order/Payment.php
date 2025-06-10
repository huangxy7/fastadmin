<?php

namespace app\admin\controller\order;

use app\common\controller\Backend;
use think\Config;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 订单列管理
 *
 * @icon fa fa-circle-o
 */
class Payment extends Backend
{

    /**
     * Payment模型对象
     * @var \app\admin\model\Payment
     */
    protected $model = null;

    /**
     * @var string
     */

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Payment;
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function index()
    {
        $get_data = \app\common\model\Config::where('name', 'get_data')->find();
        $this->assign('get_data', $get_data['value']);
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }

        // 获取前端提交的参数
        $filter = json_decode($this->request->get("filter", ''),true);
        $op = json_decode($this->request->get("op", '', 'trim'),true);
        // 更改订单筛选条件
        if (isset($filter['total'])) {
            if ($filter['total']){
                $op['total'] = '>'; // 条件
            }
        }
        $this->request->get(['filter'=>json_encode($filter,true)]);
        $this->request->get(['op'=>json_encode($op,true)]);

        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        foreach ($list->items() as $k => $v) {
            if ($v['system_status'] == 3) {
                $list->items()[$k]['system_status'] = $v['system_status_name'];
            }
            $order_json = json_decode($v['order_json'], true);
            $list->items()[$k]['order_json'] = $order_json;
            $merchant_code = [];
            foreach ($order_json['items'] as $vs) {
                $merchant_code[] = $vs['merchant_code'];
            }
            $customInfos_value = [];
            foreach ($order_json['customInfos'] ?? [] as $vcustomInfos) {
                $customInfos_value[] = $vcustomInfos['value'];
            }
            $list->items()[$k]['customInfos_value'] = implode(",", $customInfos_value);
            $list->items()[$k]['merchant_code'] = implode(",", $merchant_code);
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    public function edit($ids = null)
    {
        $row = $this->model->where('id',$ids)->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $row['order_json'] = json_decode($row['order_json'], true);
            $this->view->assign('row', $row);
            $this->view->assign('items', $row['order_json']['items']);
            $this->view->assign('customInfos', $row['order_json']['customInfos'] ?? []);

            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }

        if ($params['system_status_name'] == "处理中") {
            $this->error(__('不能手动设置为处理中', ''));
        }
        if ($params['system_status_name'] == "未处理") {
            $params['system_status'] = 0;
        }
        $params = $this->preExcludeFields($params);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 启用
     */
    public function start($ids = '')
    {
        \app\common\model\Config::update(['value' => 1], ['name' => 'get_data']);
        $this->success("模拟启动成功");
    }

    /**
     * 暂停
     */
    public function pause($ids = '')
    {
        \app\common\model\Config::update(['value' => 0], ['name' => 'get_data']);
        $this->success("模拟暂停成功");
    }

//    public function reload()
//    {
//        //unship：待发货；unpay：待付款；shiped：已发货；refunding：退款中；finish：已完成；close：已关闭；
//        $order_type_array = ['unpay', 'shiped', 'refunding', 'finish', 'close'];
////        try {
//            $paymentModel = new \app\admin\model\Payment;
//            $token = get_token();
//            foreach ($order_type_array as $order_type) {
//                $page = 1;
//                $pageSize = 50;
//                $allTotal = 1;
//                $allPage = 1;
//                while (true) {
//                    if ($page > $allPage) {
//                        break;
//                    }
//                    $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"' . $order_type . '"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
//                    \think\Log::error('huangxy'.'https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"' . $order_type . '"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
//
//                    $result = json_decode($result, true);
//                    if ($result['status']['status_code'] === 10013) {
//                        $token = get_token(true);
//                        $result = \fast\Http::get('https://api.vdian.com/api?param={"page_num":' . $page . ',"page_size":' . $pageSize . ',"order_type":"' . $order_type . '"}&public={"method":"vdian.order.list.get","access_token":"' . $token . '","version":"1.2"}');
//                        $result = json_decode($result, true);
//                    }
//                    if ($result['status']['status_code'] === 0) {
//                        $allTotal = $result['result']['total_num'];
//                        $data = $result['result']['orders'];
//                        $params = [];
//                        foreach ($data as $value) {
//
//                            //call api 获取订单详情
//                            $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
//                            $orderDetail = json_decode($orderDetail, true);
//                            if ($orderDetail['status']['status_code'] === 10013) {
//                                $token = get_token(true);
//                                $orderDetail = \fast\Http::get('https://api.vdian.com/api?param={"order_id":"' . $value['order_id'] . '"}&public={"method":"vdian.order.get","access_token":"' . $token . '","version":"1.0","format":"json"}');
//                                $orderDetail = json_decode($orderDetail, true);
//                            }
//                            if ($orderDetail['status']['status_code'] !== 0) {
//                                \think\Log::info('info_getPaymentList_for_data_get_detail_error' . json_encode($orderDetail, JSON_UNESCAPED_UNICODE));
//                                continue;
//                            }
//                            $resOrderDetail = $orderDetail['result'];
//
//                            //check isset order_id
//                            $params[] = [
//                                'buyer_info'         => json_encode($value['buyer_info']),
//                                'buyer_note'         => $value['buyer_note'],
//                                'express_fee'        => $value['express_fee'],
//                                'express_no'         => $value['express_no'],
//                                'express_type'       => $value['express_type'],
//                                'f_seller_id'        => $value['f_seller_id'],
//                                'group_status'       => $value['group_status'],
//                                'img'                => $value['img'],
//                                'is_wei_order'       => $value['is_wei_order'],
//                                'order_id'           => $value['order_id'],
//                                'refundStatus'       => $value['refundStatus'],
//                                'seller_note'        => $value['seller_note'],
//                                'status'             => $value['status'],
//                                'time'               => $value['time'],
//                                'total'              => $value['total'],
//                                'update_time'        => $value['update_time'],
//                                'system_status'      => 0,
//                                'system_status_name' => '未处理',
//                                'order_type'         => $order_type,
//                                'order_json'         => json_encode($resOrderDetail, JSON_UNESCAPED_UNICODE),//商品总价格，不包含运费
//                            ];
//                        }
//                        $paymentModel->saveAll($params);
//                    }
//                    $allPage = round($allTotal / $pageSize) + 1;
//                    $page++;
//                }
//            }
////        } catch (Exception $e) {
////            \think\Log::error('install_payment_list_error' . json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE));
////        }
//        $this->success("刷新成功");
//    }

}
