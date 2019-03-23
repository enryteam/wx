<?php 
header("Content-type:text/html;charset=utf-8");
date_default_timezone_set('Asia/Shanghai'); 
		//$model=D('Rest');
		$wxpay_ret = file_get_contents('php://input');
		$arr = xml($wxpay_ret);
		file_put_contents('gzh1.txt',$wxpay_ret);
		file_put_contents('gzh2.txt',var_export($arr,true).PHP_EOL,FILE_APPEND);
		die;//上生产后删除


		//下面写回调处理



		if($arr['FEE_TYPE']=='CNY' && $arr['RESULT_CODE']=='SUCCESS' && $arr['RETURN_CODE']=='SUCCESS'){
			//支付成功的处理
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

