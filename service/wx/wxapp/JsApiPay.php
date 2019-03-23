<?php
class JsApiPay
{
	public $mchid = null;
  public $appid = null;
  public $appKey = null;
  public $apiKey = null;
  public $notify_url = null;
  public $data = null;
	public $userinfo = null;

  public function __construct($mchid,$appid,$appKey,$apiKey,$notify_url) {
		$this->mchid=$mchid;//商户号
		$this->appid=$appid;//小程序的appid
		$this->appKey=$appKey;//secert
		$this->apiKey=$apiKey;//商户号对应的支付秘钥
		$this->notify_url=$notify_url;//回调地址
	}

  /**
   * 通过跳转获取用户的openid，跳转流程如下：
   * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
   * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
   * @return 用户的openid
   */
  public function GetOpenid() {
		//通过code获得openid
    if (!isset($_GET['code'])) {
			//触发微信返回code码
      $scheme = $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
      $baseUrl = urlencode($scheme . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] .'?' . $_SERVER['QUERY_STRING']);
      $url = self::__CreateOauthUrlForCode($baseUrl);
      header("Location:".$url);
      exit();
    } else {
			//获取code码，以获取openid
      $code = $_GET['code'];
      $openid = self::getOpenidFromMp($code);
      return $openid;
    }
  }

	function GetUserInfo(){
		$data = $this->data;
		$appid = $this->appid;
		$secret = $this->appKey;
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret";
		$token = json_decode(self::curlPost($url),true);
		$access_token = $token["access_token"];
		$openid = $data['openid'];
		$get_user_info_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid&lang=zh_CN";
		$userinfo = self::curlPost($get_user_info_url);
		$this->userinfo = $userinfo;
	}

  /**
   * 通过code从工作平台获取openid机器access_token
   * @param string $code 微信跳转回来带上的code
   * @return openid
   */
  public function GetOpenidFromMp($code) {
    $url = self::__CreateOauthUrlForOpenid($code);
    $res = self::curlGet($url);
		//取出openid
    $data = json_decode($res, true);
    $this->data = $data;
    $openid = $data['openid'];
    return $openid;
  }

  /**
   * 构造获取open和access_toke的url地址
   * @param string $code，微信跳转带回的code
   * @return 请求的url
   */
  private function __CreateOauthUrlForOpenid($code) {
    $urlObj["appid"] = $this->appid;
    $urlObj["secret"] = $this->appKey;
    $urlObj["code"] = $code;
    $urlObj["grant_type"] = "authorization_code";
    $bizString = self::ToUrlParams($urlObj);
    return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
  }

  /**
   * 构造获取code的url连接
   * @param string $redirectUrl 微信服务器回跳的url，需要url编码
   * @return 返回构造好的url
   */
  private function __CreateOauthUrlForCode($redirectUrl) {
    $urlObj["appid"] = $this->appid;
    $urlObj["redirect_uri"] = "$redirectUrl";
    $urlObj["response_type"] = "code";
    $urlObj["scope"] = "snsapi_base";
    $urlObj["state"] = "1" . "#wechat_redirect";
    $bizString = self::ToUrlParams($urlObj);
    return "https://open.weixin.qq.com/connect/oauth2/authorize?" . $bizString;
  }

  /**
   * 拼接签名字符串
   * @param array $urlObj
   * @return 返回已经拼接好的字符串
   */
  private function ToUrlParams($urlObj) {
    $buff = "";
    foreach ($urlObj as $k => $v) {
      if ($k != "sign")
        $buff .= $k . "=" . $v . "&";
    }
    $buff = trim($buff, "&");
    return $buff;
  }

  /**
   * 统一下单
   * @param string $openid 调用【网页授权获取用户信息】接口获取到用户在该公众号下的Openid
   * @param float $totalFee 收款总费用 单位元
   * @param string $outTradeNo 唯一的订单号
   * @param string $orderName 订单名称
   * @param string $notifyUrl 支付结果通知url 不要有问号
   * @param string $timestamp 支付时间
   * @return string
   */
  public function createJsBizPackage($openid, $totalFee, $outTradeNo, $orderName,$attach) {
    $config = array(
      'mch_id' => $this->mchid,
      'appid' => $this->appid,
      'key' => $this->apiKey,
    );
    $unified = array(
      'appid' => $config['appid'],
      'attach' => $attach, //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
      'body' => $orderName,
      'mch_id' => $config['mch_id'],
      'nonce_str' => self::createNonceStr(),
      'notify_url' => $this->notify_url,
      'openid' => $openid, //rade_type=JSAPI，此参数必传
      'out_trade_no' => $outTradeNo,
      'spbill_create_ip' => '127.0.0.1',
      'total_fee' => intval($totalFee * 100), //单位 转为分
      'trade_type' => 'JSAPI',
    );
    $unified['sign'] = self::getSign($unified, $config['key']);
    $responseXml = self::curlPost('https://api.mch.weixin.qq.com/pay/unifiedorder', self::arrayToXml($unified));
    $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($unifiedOrder === false) {
      return 'parse xml error';
    }
    if ($unifiedOrder->return_code != 'SUCCESS') {
      return $unifiedOrder->return_msg;
    }
    if ($unifiedOrder->result_code != 'SUCCESS') {
      return $unifiedOrder->err_code;
    }
		$timestamp = time();
    $arr = array(
      "appId" => $config['appid'],
      "timeStamp" => "$timestamp", //这里是字符串的时间戳，不是int，所以需加引号
      "nonceStr" => self::createNonceStr(),
      "package" => "prepay_id=" . $unifiedOrder->prepay_id,
      "signType" => 'MD5',
    );
    $arr['paySign'] = self::getSign($arr, $config['key']);
    return $arr;
  }

	//get请求
  public static function curlGet($url = '', $options = array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    if (!empty($options)) {
      curl_setopt_array($ch, $options);
    }
		//https请求 不验证证书和host
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }

	//post请求
  public static function curlPost($url = '', $postData = '', $options = array()) {
    if (is_array($postData)) {
      $postData = http_build_query($postData);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
    if (!empty($options)) {
      curl_setopt_array($ch, $options);
    }
		//https请求 不验证证书和host
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }

  public static function createNonceStr($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

	//  数组转xml
  public static function arrayToXml($arr) {
    $xml = "<xml>";
    foreach ($arr as $key => $val) {
      if (is_numeric($val)) {
        $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
      } else
        $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
    }
    $xml .= "</xml>";
    return $xml;
  }

	//生成加密串
  public static function getSign($params, $key) {
    ksort($params, SORT_STRING);
    $unSignParaString = self::formatQueryParaMap($params, false);
    $signStr = strtoupper(md5($unSignParaString . "&key=" . $key));
    return $signStr;
  }

	
  protected static function formatQueryParaMap($paraMap, $urlEncode = false) {
    $buff = "";
    ksort($paraMap);
    foreach ($paraMap as $k => $v) {
      if (null != $v && "null" != $v) {
        if ($urlEncode) {
          $v = urlencode($v);
        }
        $buff .= $k . "=" . $v . "&";
      }
    }
    $reqPar = '';
    if (strlen($buff) > 0) {
      $reqPar = substr($buff, 0, strlen($buff) - 1);
    }
    return $reqPar;
  }
}