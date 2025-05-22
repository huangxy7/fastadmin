<?php

namespace app\admin\controller\doudian;

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
class Orders extends Backend
{

    /**
     * Payment模型对象
     * @var \app\admin\model\DoudianOrders
     */
    protected $model = null;

    /**
     * @var string
     */

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\DoudianOrders;
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
            $list->items()[$k]['pay_amount'] = round($v['pay_amount']/100,0);
            if ($v['system_status'] == 3) {
                $list->items()[$k]['system_status'] = $v['system_status_name'];
            }
            $account_list = json_decode($v['account_list'], true);

            $merchant_code = [];
            foreach ($account_list as $vs) {
                $merchant_code[] = $vs['account_name'];
                $customInfos_value[] = $vs['account_val'];
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
            $row['account_list'] = json_decode($row['account_list'], true);
            $this->view->assign('row', $row);
            $this->view->assign('items', $row['account_list']);

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

}
