<?php

defined('IN_PHPFRAME') or exit('No permission resources.');

pc_base::load_app_class('RestAction');
error_reporting(0);
class wx_xcx extends RestAction 
{
	private $model = null;
  protected $mchid = null;
  protected $appid = null;
  protected $appKey = null;
  protected $apiKey = null;
  protected $notify_url = null;
	public $data = null;
	protected $userinfo = null;

	public function __construct() {
		$this->mchid='';//商户号
		$this->appid='';//appid
		$this->appKey='';//secret
		$this->apiKey='';//商户秘钥
		$this->notify_url='';//回调地址
    $model = D('Rest');
    $this->model = $model;
  }

 //用户登录和状态判断
	public function jiemi(){
		$model = $this->model;
		if($_SESSION['user_id']){
		  returnJson('200','已登录',array('user_id'=>$_SESSION['user_id']));
		}else{
			$code = getgpc("code");
			$appid = $this->appid;
			$secret = $this->appKey;
			if(!$code)
			{
				returnJson('500','缺少code');
			}
			$url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";
			$ret = wx_curl($url);
			$userinfo = json_decode($ret, true);
			if(!$userinfo['openid']){
				returnJson('500','openid获取失败');
			}
		  $openid = $userinfo['openid'];
		  $user = $model->queryone("SELECT * FROM `llex`.`llex_user` WHERE openid = '$openid'");
		  if($user){
				$_SESSION['user_id'] = $user['id'];
				returnJson('200','登陆成功',array('user_id'=>$_SESSION['user_id'],'openid'=>$openid,'appid'=>$appid,'account'=>$user['account']));
		  }else{
				$head_img = getgpc('head_img');
				$nickname = getgpc('nickname');
				$res = $model->querySql("INSERT INTO `llex`.`llex_user`(`openid`, `ctime`, `head_img`, `nickname`) VALUES ('".$openid."','".date('Y-m-d H:i:s')."','".$head_img."','".$nickname."')");
				$user['id'] = mysql_insert_id();
				if($res){
					$_SESSION['user_id'] = $user['id'];
					returnJson('200','注册成功',array('user_id'=>$user['id'],'openid'=>$openid,'appid'=>$appid,'account'=>0));
				}else{
					returnJson('500','操作失败');
				}
		  }
		}
	}

	//统一下单
  public function pay() {
		require_once "./service/wx/wxapp/JsApiPay.php";
		$jsapi = new JsApiPay($this->mchid , $this->appid , $this->appKey , $this->apiKey , $this->notify_url);
		$model = D('Rest');
		//①、获取用户openid
		$openId = '';  //用户openid
		if(!$openId) exit('获取openid失败');
		//②、统一下单
		$outTradeNo = 'vip' . date('mdHis') . rand(1000, 9999);
		$attach = $openId."VIP".floatval(getgpc('price'))."Seller".$_SESSION['zbk_userinfo']['user_id']."Id";  //你自己的商品订单号
		$payAmount = getgpc('price');   //付款金额，单位:元
		$payAmount = 0.01;   //付款金额，单位:元
		$orderName = '支付测试'; //订单标题
		$jsApiParameters = $jsapi->createJsBizPackage($openId,$payAmount,$outTradeNo,$orderName,$attach);
		if($jsApiParameters['appId']){
			returnJson('200','请求成功',$jsApiParameters);
		}else{
			returnJson('500',$jsApiParameters);
		}
  }

	//转出到用户微信号
	public function paytouser(){
		$openid = getgpc('openid');
		$amount = intval(getgpc('amount'));
		$amount = $amount*100;
		require_once "./service/wx/wxapp/WxPayUser.php";
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