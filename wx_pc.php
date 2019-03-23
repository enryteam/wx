<?php
defined('IN_PHPFRAME') or exit('No permission resources.');

pc_base::load_app_class('RestAction');
error_reporting(0);
class wx_pc extends RestAction
{

	protected $mchid;
	protected $appid;
	protected $appKey;
	protected $apiKey;
	protected $notifyUrl;
  private $model = null;

  public function __construct() {
    $model = D('rest');
		$this->model = $model;
		$this->mchid='';//商户号
		$this->appid='';//appid
		$this->appKey='';//secret
		$this->apiKey='';//商户秘钥
		$this->notify_url='';//回调地址
  }
  ////////////////////////      订单  start           ////////
  
  //扫码登陆
	public function login(){
		echo 123;die;
	}
  
  //生成付款二维码
  public function pay(){
    $model = $this->model;
		ini_set('date.timezone','Asia/Shanghai');
		$ddbh = date('YmdHis').rand(1000,9999);
		$outTradeNo = $ddbh;     //你商城的商品订单号
		$payAmount = (float)getgpc('price');          //金额，单位:元
		$orderName = '订单支付';    //订单标题
		$notifyUrl = $this->notifyUrl;     //付款成功后的回调地址(不要有问号),可直接放根目录
		$payTime = time(); 
		$arr = $this->createJsBizPackage($payAmount,$outTradeNo,$orderName,$notifyUrl,$payTime);
		//生成二维码

		$url2 = $arr['code_url'];
		echo $url2;
		die;
  }
  
	/**
     * 发起订单
     * @param float $totalFee 收款总费用 单位元
     * @param string $outTradeNo 唯一的订单号
     * @param string $orderName 订单名称
     * @param string $notifyUrl 支付结果通知url 不要有问号
     * @param string $timestamp 订单发起时间
     * @return array
     */
    public function createJsBizPackage($totalFee, $outTradeNo, $orderName, $notifyUrl, $timestamp)
    {
        $config = array(
            'mch_id' => $this->mchid,
            'appid' => $this->appid,
            'key' => $this->apiKey,
        );
				//print_r($config);die;
        //$orderName = iconv('GBK','UTF-8',$orderName);
        $unified = array(
            'appid' => $config['appid'],
            'attach' => 'pay',             //商家数据包，原样返回，如果填写中文，请注意转换为utf-8
            'body' => $orderName,
            'mch_id' => $config['mch_id'],
            'nonce_str' => self::createNonceStr(),
            'notify_url' => $notifyUrl,
            'out_trade_no' => $outTradeNo,
            'spbill_create_ip' => '127.0.0.1',
            'total_fee' => intval($totalFee * 100),       //单位 转为分
            'trade_type' => 'NATIVE',
        );
        $unified['sign'] = self::getSign($unified, $config['key']);
        $responseXml = self::curlPost('https://api.mch.weixin.qq.com/pay/unifiedorder', self::arrayToXml($unified));
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($unifiedOrder === false) {
            die('parse xml error');
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            die($unifiedOrder->return_msg);
        }
        if ($unifiedOrder->result_code != 'SUCCESS') {
            die($unifiedOrder->err_code);
        }
        $codeUrl = (array)($unifiedOrder->code_url);
        if(!$codeUrl[0]) exit('get code_url error');
        $arr = array(
            "appId" => $config['appid'],
            "timeStamp" => $timestamp,
            "nonceStr" => self::createNonceStr(),
            "package" => "prepay_id=" . $unifiedOrder->prepay_id,
            "signType" => 'MD5',
            "code_url" => $codeUrl[0],
        );
        $arr['paySign'] = self::getSign($arr, $config['key']);
        return $arr;
    }
    public function notify()
    {
			$config = array(
					'mch_id' => $this->mchid,
					'appid' => $this->appid,
					'key' => $this->apiKey,
			);
			$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
			$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			if ($postObj === false) {
					die('parse xml error');
			}
			if ($postObj->return_code != 'SUCCESS') {
					die($postObj->return_msg);
			}
			if ($postObj->result_code != 'SUCCESS') {
					die($postObj->err_code);
			}
			$arr = (array)$postObj;
			unset($arr['sign']);
			if (self::getSign($arr, $config['key']) == $postObj->sign) {
					echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
					return $postObj;
			}
	}
	/**
	 * curl get
	 *
	 * @param string $url
	 * @param array $options
	 * @return mixed
	 */
	public static function curlGet($url = '', $options = array())
	{
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
	public static function curlPost($url = '', $postData = '', $options = array())
	{
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
	public static function createNonceStr($length = 16)
	{
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			$str = '';
			for ($i = 0; $i < $length; $i++) {
					$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
			}
			return $str;
	}
	public static function arrayToXml($arr)
	{
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
	/**
	 * 获取签名
	 */
	public static function getSign($params, $key)
	{
			ksort($params, SORT_STRING);
			$unSignParaString = self::formatQueryParaMap($params, false);
			$signStr = strtoupper(md5($unSignParaString . "&key=" . $key));
			return $signStr;
	}
	protected static function formatQueryParaMap($paraMap, $urlEncode = false)
	{
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

	//转出到用户微信号
	public function paytouser(){
		$openid = getgpc('openid');
		$amount = intval(getgpc('amount'));
		$amount = $amount*100;
		require_once "../service/wx/wxapp/WxPayUser.php";
		$mchPay = new PayToUser();
    $mchPay->setappid($this->appid);
    $mchPay->setmchid($this->mchid);
    $mchPay->setkey($this->apiKey);
		// 用户openid
		$mchPay->setParameter('openid', $openid);
		// 商户订单号
		$mchPay->setParameter('partner_trade_no', 'lilianmx'.date("YmdHis").rand (1000,9999));
		// 校验用户姓名选项
		$mchPay->setParameter('check_name', 'NO_CHECK');
		// 企业付款金额  单位为分
		$mchPay->setParameter('amount', $amount);
		// 企业付款描述信息
		$mchPay->setParameter('desc', '微信提现');
		// 调用接口的机器IP地址  自定义
		$mchPay->setParameter('spbill_create_ip', '0.0.0.0'); # getClientIp()
		$wxpay_response = $mchPay->pay();
		$data = $this->xml($wxpay_response);
		//print_r($data);
		if($arr['RESULT_CODE']=='SUCCESS' && $arr['RETURN_CODE']=='SUCCESS'){
			//转出成功  处理业务
		}else{
			//转出失败  处理业务
		}
	}

	//获取xml
	function xml($xml){
		$p = xml_parser_create();
		xml_parse_into_struct($p, $xml, $vals, $index);
		xml_parser_free($p);
		$data = "";
		foreach ($index as $key=>$value) {
			if($key == 'xml' || $key == 'XML') continue;
			$tag = $vals[$value[0]]['tag'];
			$value = $vals[$value[0]]['value'];
			$data[$tag] = $value;
		}
		return $data;
	}
}
