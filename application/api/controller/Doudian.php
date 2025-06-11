<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Exception;
use think\Log;
use think\exception\HttpResponseException;
use think\Response;

require_once ROOT_PATH . 'extend/doudian/src/autoload.php';

/**
 * 抖店接口
 */
class Doudian extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 构造方法
     */
    public function __construct()
    {
        parent::__construct();
        $doudianLogic = new \app\common\Logic\doudian();
        $res          = $doudianLogic->checkSpiSign($this->request);
        if (!$res) {
            $this->error('签名验证失败', [], 100001);
        }
    }

    /**
     * 首页
     *
     */
    public function index()
    {

    }

    /**
     * 充值结果通知
     * 接口简介: 收到用户订单信息，通知商家充值
     * 详细描述: 调用商家接口，通知商家充值
     * @throws HttpResponseException
     */
    public function notify()
    {
        $param   = $this->request->param();
        $headers = $this->request->header('Log-Id');
        if ($headers) {
            if (config('doudian.debug')) {
                Log::info('抖店通知参数Log-Id:' . json_encode(['Log-Id' => $headers]));
            }
        }
        $doudianLogic    = new \app\common\Logic\doudian();
        $trade_order_no  = $param['trade_order_no'];
        $topup_biz       = $param['topup_biz'];
        $time_start      = $param['time_start'];
        $time_start      = date('Y-m-d H:i:s', strtotime($time_start));
        $time_limit      = $param['time_limit'];
        $buy_num         = $param['buy_num'];
        $amount_unit     = $param['amount_unit'];
        $sku_id          = $param['sku_id'];
        $shop_id         = $param['shop_id'];
        $account_list    = json_encode($param['account_list']);
        $code            = $param['code'];
        $pay_amount      = $param['pay_amount'];
        $doudian_open_id = $param['doudian_open_id'];
        // 这里可以进行业务逻辑处理，比如保存到数据库等

        $res = $doudianLogic->insertOrUpdate([
            'trade_order_no'  => $trade_order_no,
            'topup_biz'       => $topup_biz,
            'time_start'      => $time_start,
            'time_limit'      => $time_limit,
            'buy_num'         => $buy_num,
            'amount_unit'     => $amount_unit,
            'sku_id'          => $sku_id,
            'shop_id'         => $shop_id,
            'account_list'    => $account_list,
            'code'            => $code,
            'pay_amount'      => $pay_amount,
            'doudian_open_id' => $doudian_open_id
        ]);
        if ($res === false) {
            $this->error('Failed to save order', [], 500);
        }
        $response = [
            'trade_order_no'      => $trade_order_no,
            'topup_biz'           => $topup_biz,
            'seller_order_no'     => '',
            'seller_order_status' => "IN_PROCESS" //订单状态，如果返回状态非以下状态则将进行接口重试，可选范围： SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
        ];
//        err_code	String	2001	错误码，当 seller_order_status 为FAILED时，需要填写充值失败的原因
//        err_desc	String	携号转网失败	错误描述
//        topup_failure_reason_code	String	10001	展示给用户查看的充值失败原因错误码，目前仅支持以下枚举值，若不传则默认使用10098 10001：手机号码无效 10002：商家缺货 10003：命中运营商风控策略 10098：其他商家处理时的异常
//        0	业务处理成功
//        100001	验签失败
//        100002	参数错误
//        100003	系统错误
        $response = Response::create($response, 'json', 0);
        throw new HttpResponseException($response);
    }

    /**
     * @return void
     *
     * @author
     * @time 2025/6/9
     * 接口简介: 查询商家订单充值状态
     * 详细描述: 查询商家订单充值状态
     */
    public function query()
    {
        $param          = $this->request->param();
        $doudianLogic   = new \app\common\Logic\doudian();
        $trade_order_no = $param['trade_order_no'] ?? '';
        $topup_biz      = $param['topup_biz'] ?? '';
        $shop_id        = $param['shop_id'] ?? '';
        if (empty($trade_order_no) || empty($topup_biz) || empty($shop_id)) {
            $this->error('参数错误', $param, 100002);
        }
        $data = $doudianLogic->getOrder([
            'trade_order_no' => $trade_order_no,
            'topup_biz'      => $topup_biz,
            'shop_id'        => $shop_id
        ]);
        if (!$data) {
            $this->error('订单不存在', [], 100002);
        }
        if ($data['status'] == \app\admin\model\DoudianOrders::STATUS_IN_PROCESS) {
            $response = [
                'trade_order_no'      => $trade_order_no,
                'topup_biz'           => $topup_biz,
                'seller_order_no'     => $data['seller_order_no'],
                'seller_order_status' => "IN_PROCESS" //订单状态，如果返回状态非以下状态则将进行接口重试，可选范围： SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
            ];
            $this->success('查询成功', $response);
        } elseif ($data['status'] == \app\admin\model\DoudianOrders::STATUS_SUCCESS) {
            $response = [
                'trade_order_no'      => $trade_order_no,
                'topup_biz'           => $topup_biz,
                'seller_order_no'     => $data['seller_order_no'],
                'seller_order_status' => "SUCCESS" //订单状态，如果返回状态非以下状态则将进行接口重试，可选范围： SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
            ];
            $this->success('查询成功', $response);
        } elseif (in_array($data['status'], [\app\admin\model\DoudianOrders::STATUS_FAILED, \app\admin\model\DoudianOrders::STATUS_CANCEL, \app\admin\model\DoudianOrders::STATUS_TIMEOUT])) {
            $response = [
                'trade_order_no'      => $trade_order_no,
                'topup_biz'           => $topup_biz,
                'seller_order_no'     => $data['seller_order_no'],
                'seller_order_status' => "FAILED", //订单状态，如果返回状态非以下状态则将进行接口重试，可选范围： SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
                'err_code'            => '2001',
                'err_desc'            => $data['handle_reason'],
            ];
            $this->success('查询成功', $response);
        }
        $this->error('订单状态异常', [], 100003);
    }

    /**
     * @return void
     *
     * @author
     * 接口简介: 通知商家取消充值
     * 详细描述: 充值时间超过时间限制，回调用此接口，
     * 商家侧再保证该接口功能正常可用的同时，平台建议商家系统也根据充值时效，自行控制充值取消，避免因该接口短暂不可用导致平台给用户退款，但商家系统并没有取消充值导致的资损问题。
     * @time 2025/6/10
     */
    public function cancel()
    {
        $param          = $this->request->param();
        $doudianLogic   = new \app\common\Logic\doudian();
        $trade_order_no = $param['trade_order_no'] ?? '';
        $topup_biz      = $param['topup_biz'] ?? '';
        $shop_id        = $param['shop_id'] ?? '';
        if (empty($trade_order_no) || empty($topup_biz) || empty($shop_id)) {
            $this->error('参数错误', $param, 100002);
        }
        $data = $doudianLogic->getOrder([
            'trade_order_no' => $trade_order_no,
            'topup_biz'      => $topup_biz,
            'shop_id'        => $shop_id
        ]);
        if (!$data) {
            $this->error('订单不存在', [], 100002);
        }
        if ($data['status'] == \app\admin\model\DoudianOrders::STATUS_SUCCESS) {
            $this->error('订单已充值成功，无法取消', [], 100003);
        }
        $response = [
            'trade_order_no'      => $trade_order_no,
            'topup_biz'           => $topup_biz,
            'seller_order_no'     => $data['seller_order_no'],
            'seller_order_status' => \app\admin\model\DoudianOrders::STATUS_CANCEL //订单状态，如果返回状态非以下状态则将进行接口重试，可选范围： SUCCESS 订单充值成功 FAILED 订单充值失败 IN_PROCESS 订单充值中
        ];
        if (in_array($data['status'], [\app\admin\model\DoudianOrders::STATUS_FAILED, \app\admin\model\DoudianOrders::STATUS_CANCEL, \app\admin\model\DoudianOrders::STATUS_TIMEOUT])) {
            $this->success('订单已处理', $response, 0);
        }
        $res = $doudianLogic->update([
            'trade_order_no' => $trade_order_no,
            'topup_biz'      => $topup_biz,
            'shop_id'        => $shop_id
        ], [
            'status'        => \app\admin\model\DoudianOrders::STATUS_CANCEL,
            'handle_reason' => '平台通知订单取消充值'
        ]);
        if (!$res) {
            $this->error('订单取消失败', [], 100003);
        }
        $this->success('订单取消成功', $response, 0);
    }

    /**
     * @return void
     *
     * @author
     * @time 2025/6/10
     * 接口简介: 查询商家侧订单信息（外部订单）
     * 详细描述: 查询外部商家订单信息
     */
    public function QueryOrderInfoForCreateOrder()
    {
//        outer_order_id	String	R1	是	"123456"	商家外部订单号
//        product_id_list	Json	-	否	[1234567]	商品id
//        sku_id_list	Json	-	否	[12345678]	商品skuId
//        shop_id	Number	-	是	90410
        $outer_order_id = $this->request->param('outer_order_id', '');
//        $product_id_list = $this->request->param('product_id_list', '');
//        $sku_id_list = $this->request->param('sku_id_list', '');
        $shop_id = $this->request->param('shop_id', '');
        if (empty($outer_order_id) || empty($shop_id)) {
            $this->error('参数错误', [], 100002);
        }
        if ($shop_id != config('doudian.shop_id')) {
            $this->error('商家校验不通过', [], 10001);
        }
        $doudianLogic = new \app\common\Logic\doudian();
        $data         = $doudianLogic->find([
            'seller_order_no' => $outer_order_id,
            'shop_id'         => $shop_id
        ]);
        if (!$data) {
            $this->error('订单不存在', [], 100002);
        }
//        参数名称	参数类型	示例值	参数描述
//        outer_order_id	String	"123456"	外部商家订单号
//        pay_amount	Number	10000	用户应该支付金额（单位分）
//        promotion_amount	Number	1000	优惠金额（单位分）；优惠金额+支付金额=商品价格
        $response = [
            'outer_order_id'   => $data['seller_order_no'],
            'pay_amount'       => $data['pay_amount'],
            'promotion_amount' => $data['promotion_amount'],
        ];
        $this->success('查询成功', $response, 0);
    }
}

