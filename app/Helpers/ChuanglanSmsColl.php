<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class ChuanglanSmsColl {

    /**
     * 创蓝短信API地址（国内短信）
     * 【创蓝云智】欢迎使用创蓝公司产品，您的账号已开通，请打开 https://www.chuanglan.com ，使用账号 18126476210 临时密码 8N_e#Ti7^X93 登录并修改密码
     * @var string
     */
    protected $apiUrl = 'https://smssh1.253.com/msg/v1/send/json';

    /**
     * API账号（从创蓝控制台获取）
     * https://www.chuanglan.com/control/sms/cl_normal_sms/api_info
     * @var string
     */
    protected $account = 'N5222381'; // 替换为你的API账号

    /**
     * API密码（从创蓝控制台获取）
     * @var string
     */
    protected $password = '68388DUWg332de'; // 替换为你的API密码0123456Chuanglan b7p8EBh413d799

    
    
    /**
     * 发送国内短信（验证码/通知/营销）
     * @param string $phone 接收手机号（多个用英文逗号分隔，最多1000个）
     * @param string $content 短信内容（需包含已审核签名【】，营销短信需以"拒收请回复R"结尾）
     * @param array $options 可选参数：report（是否需要状态回执）、callbackUrl（回执回调地址）、uid（自定义参数）
     * @return array 包含成功状态和响应信息的数组
     */
    public function sendSms(string $phone, string $content, array $options = []): array {
        // 构造请求参数
        $params = [
            'account' => $this->account,
            'password' => $this->password,
            'msg' => "【东桓外贸】".$content,
            'phone' => $phone,
            'report' => $options['report'] ?? 'false',
            'callbackUrl' => $options['callbackUrl'] ?? '',
            'uid' => $options['uid'] ?? '',
            'sendtime' => $options['sendtime'] ?? '', // 格式：yyyyMMddHHmm（7天内定时）
            'extend' => $options['extend'] ?? '', // 扩展码（可选）
        ];

//        $this->info(json_decode($params,256+64));
        Log::info("sendSms ",$params);
        // 发送HTTP请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json;charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 生产环境建议开启SSL验证

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 处理响应
        if ($httpCode !== 200) {
            Log::error('创蓝短信API请求失败，HTTP状态码：' . $httpCode);
            return ['success' => false, 'message' => '网络请求失败'];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('创蓝短信API响应解析失败，原始响应：' . $response);
            return ['success' => false, 'message' => '响应解析失败'];
        }

        // 根据状态码处理结果（参考文档QYST71AR4I8FFG0R）
        if ($result['code'] === '0') {
            Log::info('短信发送成功', ['phone' => $phone, 'content' => $content, 'result' => $result]);
            return ['success' => true, 'message' => '短信提交成功', 'data' => $result];
        } else {
            $errorMsg = $this->getErrorCodeMessage($result['code']);
            Log::error('短信发送失败，错误码：' . $result['code'] . '，错误信息：' . $errorMsg, [
                'phone' => $phone,
                'content' => $content,
                'result' => $result
            ]);
            return ['success' => false, 'message' => $errorMsg, 'code' => $result['code']];
        }
    }

    /**
     * 根据错误码获取描述（参考文档QYST71AR4I8FFG0R）
     * @param string $code 错误码
     * @return string 错误描述
     */
    protected function getErrorCodeMessage(string $code): string {
        $errorMap = [
            '101' => '无此用户（API账号错误或已关停）',
            '102' => '密码错误',
            '103' => '提交过快（超过流速限制）',
            '104' => '系统忙（平台暂时无法处理）',
            '105' => '敏感短信（内容含敏感词）',
            '106' => '消息长度错误（>1036或<=0）',
            '107' => '包含错误的手机号码',
            '108' => '手机号码个数错误（>1000或<=0）',
            '109' => '无发送额度',
            '110' => '不在发送时间内',
            '116' => '签名不合法或未带签名',
            '117' => 'IP地址未加白',
            '127' => '定时发送时间格式错误（yyyyMMddHHmm）',
            '129' => 'JSON格式错误',
            '130' => '请求参数错误（缺少必传参数）',
            '158' => '退订语不符合规范（需为"拒收请回复R"）',
        ];
        return $errorMap[$code] ?? '未知错误（请参考创蓝文档QYST71AR4I8FFG0R）';
    }

    /**
     * 打电话国内（验证码/通知/营销）
     * @param string $phone 接收手机号（多个用英文逗号分隔，最多1000个）
     * @param string $content 短信内容（需包含已审核签名【】，营销短信需以"拒收请回复R"结尾）
     * @param array $options 可选参数：report（是否需要状态回执）、callbackUrl（回执回调地址）、uid（自定义参数）
     * @return array 包含成功状态和响应信息的数组
     */
    public function sendColl(array|string $phone, string $content, array $options = []) {
        $url = 'http://api.253.com/open/notify/batch-voice-notify';
        $mobile="";
        if(is_array($phone)){
            foreach ($phone as $k_1 => $v_1) {
                if($mobile){
                    $mobile.=";";
                }
                $v_1_1= trim($v_1);
                $mobile.=$v_1_1.",".$content."|". date("y-m-d");
            }
        }else{
            $mobile=$phone??"13257225590".",";
            $mobile.=$content??""."|". date("y-m-d");
        }
        Log::info("mobile $mobile");
        $params = [
//            'appId' => 'V3dwtFT5', // appId,登录万数平台查看
//            'appKey' => 'ckijkHkj', // appKey,登录万数平台查看
            'appId' => '70uJoSSO', // appId,登录万数平台查看
            'appKey' => 'LESmjUni', // appKey,登录万数平台查看
            'templateId'=>'1376627078934736896', # 您的帐号{1}异常，时间{2},请及时处理
//            'mobile' => '13257225590,abc123456|2025-5-26 18:51', // 在批量语音通知接口中调用成功后返回的 "callId"
//            'mobile' => '13257225590,abc123456|2025-5-26 18:51', // 在批量语音通知接口中调用成功后返回的 "callId"
            'mobile' => $mobile, // 在批量语音通知接口中调用成功后返回的 "callId"
//        'callId' => 'xxx', // 在批量语音通知接口中调用成功后返回的 "callId"
            "maxRecallCount"=>1
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = curl_exec($ch);

        var_dump($result);

        // 处理响应
        if ($httpCode !== 200) {
            Log::error('创蓝短信API请求失败，HTTP状态码：' . $httpCode);
            return ['success' => false, 'message' => '网络请求失败'];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('创蓝短信API响应解析失败，原始响应：' . $response);
            return ['success' => false, 'message' => '响应解析失败'];
        }

        // 根据状态码处理结果（参考文档QYST71AR4I8FFG0R）
        if ($result['code'] === '0') {
            Log::info('短信发送成功', ['phone' => $phone, 'content' => $content, 'result' => $result]);
            return ['success' => true, 'message' => '短信提交成功', 'data' => $result];
        } else {
            $errorMsg = $this->getErrorCodeMessage($result['code']);
            Log::error('短信发送失败，错误码：' . $result['code'] . '，错误信息：' . $errorMsg, [
                'phone' => $phone,
                'content' => $content,
                'result' => $result
            ]);
            return ['success' => false, 'message' => $errorMsg, 'code' => $result['code']];
        }
    }

}
