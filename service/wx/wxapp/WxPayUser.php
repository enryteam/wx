<?php
class PayToUser
{
    /**
     * API 参数
     * @var array
     * 'mch_appid'         # 公众号APPID
     * 'mchid'             # 商户号
     * 'device_info'       # 设备号
     * 'nonce_str'         # 随机字符串
     * 'partner_trade_no'  # 商户订单号
     * 'openid'            # 收款用户openid
     * 'check_name'        # 校验用户姓名选项 针对实名认证的用户
     * 're_user_name'      # 收款用户姓名
     * 'amount'            # 付款金额
     * 'desc'              # 企业付款描述信息
     * 'spbill_create_ip'  # Ip地址
     * 'sign'              # 签名
     */
    public $parameters = [];
    public $SSLROOTCA_PATH='';
    public $SSLCERT_PATH='';
    public $SSLKEY_PATH='';
    public $appid='';
    public $secret='';
    public $mchid='';
    public $key='';//商户密钥

    public function __construct()
    {

        $this->url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $this->curl_timeout = 10;
      //$this->SSLROOTCA_PATH='/htdocs/wxpaycert/rootca.pem';
        $this->SSLCERT_PATH='/htdocs/wxpaycert/apiclient_cert.pem';
        $this->SSLKEY_PATH='/htdocs/wxpaycert/apiclient_key.pem';
    }

    public function setParameter($key,$value){
        $this->parameters[$key]=$value;
    }

    public function setappid($value){
        $this->appid=$value;
    }

    public function setmchid($value){
        $this->mchid=$value;
    }

    public function setkey($value){
        $this->key=$value;
    }

    function arrayToXml($arr,$dom=0,$item=0){
        if (!$dom){
            $dom = new DOMDocument("1.0");
        }
        if(!$item){
            $item = $dom->createElement("xml");
            $dom->appendChild($item);
        }
        foreach ($arr as $key=>$val){
            $itemx = $dom->createElement(is_string($key)?$key:"item");
            $item->appendChild($itemx);
            if (!is_array($val)){
                $text = $dom->createTextNode($val);
                $itemx->appendChild($text);

            }else {
                $this->arrayToXml($val,$dom,$itemx);
            }
        }
        $dom->encoding = 'UTF-8'; // insert proper
        return $dom->saveXML();
    }

    public function getSign($paramArr){//print_r($paramArr);
        ksort($paramArr);
        $paramStr = http_build_query($paramArr);
        $paramStr=urldecode($paramStr);
        $param_temp=$paramStr.'&key='.$this->key;//echo $param_temp.'<br>';
        $signValue=strtoupper(md5($param_temp));//echo $signValue.'<br>';
        return $signValue;

    }

    /**
     * 生成请求xml数据
     * @return string
     */
    public function createXml()
    {
        $this->parameters['mch_appid'] = $this->appid;
        $this->parameters['mchid']     = $this->mchid;
       // $this->parameters['nonce_str'] = md5(time());
        $this->parameters['nonce_str'] = $this->getNonceStr();
        $this->parameters['sign']      = $this->getSign($this->parameters);
        $a= $this->arrayToXml($this->parameters);
        //echo $a;
        return $a;
    }

    public function pay(){
        $xml=$this->createXml();
        $url=$this->url;
        return $this->postXmlSSLCurl($xml,$url,$second=30);
    }

    /**
     *     作用：使用证书，以post方式提交xml到对应的接口url
     */
    function postXmlSSLCurl($xml,$url,$second=30)
    {

        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置header
        curl_setopt($ch,CURLOPT_HEADER,FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        //设置证书
        //curl_setopt($ch,CURLOPT_CAINFO, $this->SSLROOTCA_PATH);
        //使用证书：cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, $this->SSLCERT_PATH);
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, $this->SSLKEY_PATH);

        //post提交方式
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data = curl_exec($ch);
				//file_put_contents('paytouser.log',$url."\r\n\r\n".$xml."\r\n\r\n",FILE_APPEND);
        //返回结果
        if(!$data){
            $error = curl_errno($ch);
            $data = "curl出错，错误码:$error"."<br>";
        }
				curl_close($ch);
				return $data;
        
    }

	public static function getNonceStr($length = 16) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		$str = 'abcdefghijklmnopqr';
		return $str;
	}
}
