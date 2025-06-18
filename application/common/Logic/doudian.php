<?php

namespace app\common\Logic;

use app\admin\model\DoudianOrders;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

require_once ROOT_PATH . 'extend/doudian/src/autoload.php';

class doudian
{
    private $model;

    /**
     * @var DoudianOrders
     */
    public function __construct()
    {
        $this->model = new DoudianOrders();

    }

    /**
     * @throws Exception
     */
    public function token($cache = true)
    {
        $token = cache('doudian_access_token');
        if ($token && $cache) {
            return $token;
        }
        \GlobalConfig::getGlobalConfig()->appKey    = config('doudian.appkey');
        \GlobalConfig::getGlobalConfig()->appSecret = config('doudian.secret');
        $shop_id                                    = config('doudian.shop_id'); // 替换成你的shop_id
        $accessToken                                = \AccessTokenBuilder::build($shop_id, 2);
        if (!$accessToken->isSuccess()) {
            throw new \think\Exception('获取抖店access_token失败: ' . $accessToken->getCode() . $accessToken->getMsg());
        }
        $token        = $accessToken->getAccessToken();
        $refreshToken = $accessToken->getRefreshToken();
        if (!$token) {
            throw new \think\Exception('获取抖店access_token失败: access_token 为空');
        }
        // 缓存access_token
        cache('doudian_access_token', $token, 3600 * 24 * 6); // 缓存6天
        cache('doudian_refresh_access_token', $refreshToken, 3600 * 24 * 13); // 缓存13天
        return $token;
    }

    /**
     * @return void
     *
     * @author HuangXianYun
     * @time 2025/6/12
     * 最好半个小时run一次
     */
    public function refreshToken()
    {
        try {
            \GlobalConfig::getGlobalConfig()->appKey    = config('doudian.appkey');
            \GlobalConfig::getGlobalConfig()->appSecret = config('doudian.secret');
            $res                                        = \AccessTokenBuilder::refresh(cache('doudian_refresh_access_token'));
            print_r($res);
            if (!$res->isSuccess()) {
                $this->token();
                throw new \think\Exception('刷新抖店access_token失败: ' . $res->getCode() . ' ' . $res->getMsg());
            }
            $accessToken  = $res->getAccessToken(); // 刷新后的 access_token
            $refreshToken = $res->getRefreshToken(); // 刷新后的 refresh_token
            cache('doudian_access_token', $accessToken, 3600 * 24 * 6); // 缓存6天
            cache('doudian_refresh_access_token', $refreshToken, 3600 * 24 * 13); // 缓存13天
            print_r('抖店access_token刷新成功: ' . $accessToken . PHP_EOL);
            print_r('抖店refresh_token刷新成功: ' . $refreshToken . PHP_EOL);
        } catch (Exception $e) {
            Log::error('抖店access_token刷新失败: ' . $e->getMessage());
            return;
        }
    }

    public function update($where, $update): bool
    {
        try {
            $result = $this->model->where($where)->update($update);
            return $result !== false;
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            // 处理异常
            Log::error('Error updating doudian order: ' . $e->getMessage());
        }
        return false;
    }

    public function find($where)
    {
        try {
            $data = $this->model->where($where)->find();
            if ($data) {
                return $data;
            } else {
                return null;
            }
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            // 处理异常
            Log::error('Error finding doudian order: ' . $e->getMessage());
        }
        return null;
    }

    public function insertOrUpdate($params): bool
    {
        try {
            $data = $this->model->where(['trade_order_no' => $params['trade_order_no']])->find();
            if ($data) {
                // 更新
                $params['id'] = $data['id'];
                $this->model->update($params);
            } else {
                // 插入
                $this->model->save($params);
            }
            return true;
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            // 处理异常
            Log::error('Error inserting or updating doudian order: ' . $e->getMessage());
        }
        return false;
    }

    public function getOrder(array $where)
    {
        try {
            $data = $this->model->where($where)->find();
            if ($data) {
                return $data;
            } else {
                return null;
            }
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            // 处理异常
            Log::error('Error finding doudian order: ' . $e->getMessage());
        }
        return null;
    }

    /**
     */
    public function checkSpiSign(Request $request): bool
    {
        try {
            // 获取应用密钥
            $appSecret = config('doudian.secret');
            if (!$appSecret) {
                throw new \Exception('App secret is not configured.');
            }
            // 根据请求方法获取参数
            $appkey          = $request->param('app_key');
            $timestamp       = $request->param('timestamp');
            $paramJson       = $request->getContent();
            $sign            = $request->param('sign');
            $get_sign_method = $request->param('sign_method');
            // 处理 param_json 参数
            $paramJson = json_decode($paramJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }
            // 将 string 类型的 paramJson 转成数组
            $param_json = \SignUtil::marshal($paramJson);
            // 转换签名方法为数字
            $sign_method = 1; //默认md5
            if ($get_sign_method == 'hmac-sha256') {
                $sign_method = 2; // hmac-sha256
            }
            // 计算签名
            $calcSign = \SignUtil::spiSign($appkey, $appSecret, $timestamp, $param_json, $sign_method);
            // 验证签名
            if ($sign !== $calcSign) {
                throw new \Exception('Sign or calculated sign is empty.' . ' Received sign: ' . $sign . ', Calculated sign: ' . $calcSign);
            }
            return true;
        } catch (\Exception $e) {
            // 记录日志或返回错误信息
            Log::error('Signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    function lock($id)
    {
        Cache::set('cache_doudian_' . $id, 1, 60);
    }

    function isLock($id): bool
    {
        if (cache('cache_doudian_' . $id)) {
            return true;
        } else {
            return false;
        }
    }

    public function getWaitHandleOrders(): array
    {
        $dataCacheKey = 'get_array_doudian';
        try {
            $get_data = \app\common\model\Config::where('name', 'get_data_doudian')->find();
            if ($get_data['value'] == 0) {
                return ['status' => 0, 'message' => '获取数据功能已关闭'];
            }
            $get_value = \app\common\model\Config::where('name', 'get_value_doudian')->find();
            $amount    = $get_value['value'] * 100;
            while (true) {
                $dataArray = cache($dataCacheKey);
                if (!$dataArray) {
                    $dataArray = $this->model->where('status', $this->model::STATUS_IN_PROCESS)->where('system_status', $this->model::SYSTEM_STATUS_WAIT)->field('id')->where('pay_amount', "<=", $amount)->limit(50)->select();
                    if (!$dataArray) {
                        return ['status' => 0, 'message' => '暂无数据1'];
                    }
                    cache($dataCacheKey, $dataArray, ['expire' => 60]);
                }
                foreach ($dataArray as $k => $v) {
                    if (!$this->isLock($v['id'])) {
                        $this->lock($v['id']);
                        Db::startTrans();
                        $data = $this->model->where('id', $v['id'])->where('status', $this->model::STATUS_IN_PROCESS)->where('system_status', $this->model::SYSTEM_STATUS_WAIT)->field('id,status,code,trade_order_no,time_start,account_list,pay_amount')->lock(true)->find();
                        if (!$data) {
                            Db::rollback();
                        } else {
                            if ($data['status'] === $this->model::STATUS_IN_PROCESS) {
                                $this->model->where('id', $data['id'])->update(['system_status' => $this->model::SYSTEM_STATUS_IN_PRECESS, 'system_status_name' => "处理中", "error_time" => date('Y-m-d H:i:s', time() + 240)]);
                                Db::commit();
                            } else {
                                unset($dataArray[$k]);
                                continue;
                            }
                        }
                        unset($dataArray[$k]);
                        $updateData = $dataArray;
//                    \think\Log::info('info_getPaymentList_redis' . json_encode($updateData, JSON_UNESCAPED_UNICODE));
                        cache($dataCacheKey, $updateData, ['expire' => 60]);
                        $account_list = json_decode($data['account_list'], true);
                        $res          = [
                            'order_id'     => $data['trade_order_no'],
                            'partner'      => "doudian",
                            'time'         => $data['time_start'],
                            'seller_note'  => $data['code'],
                            'total'        => round($data['pay_amount']/100,2),
                            'account_list' => $account_list,
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
        $data = $this->model->where('trade_order_no', $order_id)->find();
        if (!$data) {
            return false;
        }
        $update = [
            'system_status' => 3,
        ];
        if ($name) {
            $update['system_status_name'] = $name;
        }
        if ($device) {
            $update['device'] = $device;
        }
        if ($accountName) {
            $update['accountName'] = $accountName;
        }
        if ($name == '充值成功') {
            $update['status']     = $this->model::STATUS_SUCCESS;
            $update['error_time'] = null; // 成功时不需要错误时间
            //通知抖店充值成功
            $this->orderResult($data, $this->model::STATUS_SUCCESS);
        }
        $res = $this->model->where('trade_order_no', $order_id)->update($update);
        if ($res === false) {
            Log::error('Error updating doudian order status: ' . $this->model->getError());
            return false;
        }
        return true;
    }

    public function orderResult($orderDetail, $status): bool
    {
        try {
            $request                = new \TopupResultRequest();
            $param                  = new \TopupResultParam();
            $param->trade_order_no  = $orderDetail['trade_order_no'];
            $param->topup_biz       = $orderDetail['topup_biz'];
            $param->seller_order_no = $orderDetail['seller_order_no'];
            $request->setParam($param);
            if ($status == $this->model::STATUS_SUCCESS) {
                $param->seller_order_status = "SUCCESS";
            } else {
                $param->seller_order_status = "FAIL";
            }
//        $param->err_code = "1003";
//        $param->err_desc = "参数校验失败";
//        $param->url = "";//去使用链接
//        $param->url_type = "normal";//去使用链接类型 小程序：microapp 普通链接：normal
//        $param->topup_failure_reason_code = "10001";//充值失败错误码 10001：手机号码无效 10002：商家缺货 10003 ： 命中运营商风控策略
            $accessToken = $this->token();
            $response    = $request->execute($accessToken);
            if (!$response) {
                Log::error('Error submitting doudian order result: Response is empty');
                return false;
            }
            // 检查响应是否成功
            if ($response['code'] != 10000) {
                Log::error('Error submitting doudian order result: ' . $response->getMsg() . ' Sub Code: ' . $response->getSubCode() . ' Sub Msg: ' . $response->getSubMsg());
                return false;
            }
        } catch (Exception $e) {
            Log::error('Error submitting doudian order result: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    public function OrderBatchDecrypt($order_id, $cipher_text)
    {
        try {
            \GlobalConfig::getGlobalConfig()->appKey    = config('doudian.appkey');
            \GlobalConfig::getGlobalConfig()->appSecret = config('doudian.secret');
            $shop_id                                    = config('doudian.shop_id'); // 替换成你的shop_id
            $accessToken                                = \AccessTokenBuilder::build($shop_id, 2);
            \GlobalConfig::getGlobalConfig()->appKey    = config('doudian.appkey');
            \GlobalConfig::getGlobalConfig()->appSecret = config('doudian.secret');
            $request                                    = new \OrderBatchDecryptRequest();
            $param                                      = new \OrderBatchDecryptParam();
            $param->cipher_infos                        = [
                [
                    'auth_id'     => $order_id,
                    'cipher_text' => $this->recursive_stripslashes($cipher_text), // 这里需要对字符串进行 stripslashes 处理
                ],
            ];
            $request->setParam($param);
            $response = $request->execute($accessToken);
            if ($response->code != 10000) {
                Log::error('Error decrypting doudian order batch: ' . 'data: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                return "";
            }
            $decryptedData = '';
            // 通过对象方式获取 decrypt_text
            if (json_last_error() === JSON_ERROR_NONE && isset($response->data->decrypt_infos[0]->decrypt_text)) {
                $decryptedData = $response->data->decrypt_infos[0]->decrypt_text;
            } else {
                Log::error('Error decrypting doudian order batch: ' . json_last_error_msg());
            }
            if (empty($decryptedData)) {
                Log::error('Error decrypting doudian order batch: Decrypted data is empty');
                return "";
            }
            // 返回解密后的数据
            return $decryptedData;
        } catch (Exception $e) {
            Log::error('Error decrypting doudian order batch: ' . $e->getMessage());
            return "";
        }
    }

    function recursive_stripslashes($data)
    {
        if (is_array($data)) {
            return array_map('recursive_stripslashes', $data);
        } else {
            return stripslashes($data);
        }
    }
}