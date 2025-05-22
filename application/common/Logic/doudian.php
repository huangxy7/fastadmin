<?php

namespace app\common\Logic;

class doudian
{
    public function __construct()
    {

    }

    public function testDemo($params)
    {
        // 收集参数
        $appKey      = config('payment.appkey');//  替换成你的app_key
        $appSecret   = config('payment.secret');// 替换成你的app_secret
        $accessToken = '*'; // 替换成你的access_token
        $host        = 'https://openapi-fxg.jinritemai.com';
        $method      = 'order.batchEncrypt';

        $timestamp = time();

//        $m = array(
//            'batch_encrypt_list' => array(
//                0 => array(
//                    'plain_text'       => "&<>='/ô汉😀",//附加符号、中日韩、Emoji都不转义
//                    'auth_id'          => '12345',
//                    'is_support_index' => false,
//                    'sensitive_type'   => 2,
//                )
//            )
//        );
        $m = $params;

// 序列化参数
        $paramJson = $this->marshal($m);
        print('param_json:' . $paramJson . "\n");

// 计算签名
        $signVal = $this->sign($appKey, $appSecret, $method, $timestamp, $paramJson);
        print('sign_val:' . $signVal . "\n");

// 发起请求
        $responseVal = $this->fetch($appKey, $host, $method, $timestamp, $paramJson, $accessToken, $signVal);
        print('response_val:' . $responseVal . "\n");
    }

    // 调用Open Api，取回数据
    function fetch(string $appKey, string $host, string $method, int $timestamp, string $paramJson, string $accessToken, string $sign): string
    {
        $methodPath = str_replace('.', '/', $method);
        $url        = $host . '/' . $methodPath .
            '?method=' . urlencode($method) .
            '&app_key=' . urlencode($appKey) .
            '&access_token=' . urlencode($accessToken) .
            '&timestamp=' . urlencode(strval($timestamp)) .
            '&v=' . urlencode('2') .
            '&sign=' . urlencode($sign) .
            '&sign_method=' . urlencode('hmac-sha256');
        $opts       = array('http' =>
                                array(
                                    'method'  => 'POST',
                                    'header'  => "Accept: */*\r\n" .
                                        "Content-type: application/json;charset=UTF-8\r\n",
                                    'content' => $paramJson
                                )
        );
        $context    = stream_context_create($opts);
        $result     = file_get_contents($url, false, $context);
        return $result;
    }

    // 计算签名
    function sign(string $appKey, string $appSecret, string $method, int $timestamp, string $paramJson): string
    {
        $paramPattern = 'app_key' . $appKey . 'method' . $method . 'param_json' . $paramJson . 'timestamp' . $timestamp . 'v2';
        $signPattern  = $appSecret . $paramPattern . $appSecret;

        print('sign_pattern:' . $signPattern . "\n");
        return hash_hmac("sha256", $signPattern, $appSecret);
    }

// 序列化参数，入参必须为关联数组
    function marshal(array $param): string
    {
        $this->rec_ksort($param); // 对关联数组中的kv，执行排序，需要递归
        $s = json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); // 重新序列化，确保所有key按字典序排序
        // 加入flag，确保斜杠不被escape，汉字不被escape
        return $s;
    }

// 关联数组排序，递归
    function rec_ksort(array &$arr)
    {
        $kstring = true;
        foreach ($arr as $k => &$v) {
            if (!is_string($k)) {
                $kstring = false;
            }
            if (is_array($v)) {
                $this->rec_ksort($v);
            }
        }
        if ($kstring) {
            ksort($arr);
        }
    }
}