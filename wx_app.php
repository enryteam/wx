<?php

defined('IN_PHPFRAME') or exit('No permission resources.');
header('Content-type: text/plain');
pc_base::load_app_class('RestAction');
//error_reporting(E_ALL);
class order extends RestAction {
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

//付款
  public function wxcode() {
    $model = D('Rest');
		
    $openid = getgpc('openid');
    $type = getgpc('type');
    $cate = getgpc('cate');
    $user_id = getgpc('user_id');
    $uuid = getgpc('uuid'); //转让积分的--卖家id
    $deal_num = getgpc('deal_num'); //转让积分的--积分数量
    if ($type == 1) {
      $show = 'vip';
      $remark = '购买vip会员';
    } else {
      $show = 'mx';
      $remark = '积分转让';
    }
    $order_number = $show . date('YmdHis', time()) . rand('1000,9999');

        
		require_once "./wx/wxapp/WxPay.Api.php";
		require_once "./wx/wxapp/WxPay.Data.php";

		// 商品名称
		// 获取支付金额
		$amount=getgpc('amount');
		//$amount=4;
		$total = floatval($amount);
		if(empty($total)){
			returnjson('500','缺少金额');
		}


		$total = round($total*100); // 将元转成分
		// 商品名称
		$subject = '订单支付';
		// 订单号，示例代码使用时间值作为唯一的订单ID号
		$out_trade_no = $order_number;
		$unifiedOrder = new WxPayUnifiedOrder();
		$unifiedOrder->SetBody($subject);//商品或支付单简要描述
		$unifiedOrder->SetOut_trade_no($out_trade_no);//设置订单号
		$unifiedOrder->SetTotal_fee($total);//设置金额
		$unifiedOrder->SetTrade_type("APP");//交易类型
		$unifiedOrder->SetNotify_url($this->notify_url);//回调地址
		$inputObj->SetAppid($this->appid);//公众账号ID
		$inputObj->SetMch_id($this->mchid);//商户号
		$result = WxPayApi::unifiedOrder($unifiedOrder,$this->apiKey);
		die;
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
