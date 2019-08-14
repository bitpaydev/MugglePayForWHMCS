<?php
function bitpayxForWechat_config() {
    $configarray = [
        "FriendlyName"         => ["Type" => "System", "Value"=>"微信（BitpayX）"],
        "appSecret"         => ["FriendlyName" => "AppSecret", "Type" => "text", "Size" => "32", ],
    ];
    return $configarray;
}

function bitpayxForWechat_refund($params) {
    if(!class_exists('BitpayX')) {
        include("bitpayx/class.php");
    }
    return [
        'status' => 'success',
        'rawdata' => 'Please contact for refund. 请联系客服完成退款。',
        'transid' => $params['transid'],
        'fees' => $params['amount'],
    ];
    return [
          'status' => '400',
          'error' => 'Refund is not supported.'
    ];
}

function bitpayxForWechat_link($params) {
    $systemurl = $params['systemurl'];
    if(substr($systemurl, -1) !== '/'){
        $systemurl = $systemurl . '/';
    }

    if (!stristr($_SERVER['PHP_SELF'], 'viewinvoice')) {
        return '<img style="width: 150px" src="'.$systemurl.'modules/gateways/bitpayx/wechat.png" alt="微信支付" />';
    }
    if(!class_exists('BitpayX')) {
        include("bitpayx/class.php");
    }

    $bitpayx = new BitpayX($params['appSecret']);
    $payData = [
        'merchant_order_id' => 'WHMCS_' . $params['invoiceid'],
        'price_amount' => $params['amount'],
        'price_currency' => 'CNY',
        'pay_currency' => 'WECHAT',
        'title' => '支付单号：' . $params['invoiceid'],
        'description' => '充值：' . $params['amount'] . ' 元',
        'callback_url' => $systemurl."modules/gateways/bitpayxForWechat/notify.php",
        'success_url' => $systemurl."viewinvoice.php?id=".$params['invoiceid'],
        'cancel_url' => $systemurl."viewinvoice.php?id=".$params['invoiceid'],
    ];

    $str_to_sign = $bitpayx->prepareSignId($payData['merchant_order_id']);
    $payData['token'] = $bitpayx->sign($str_to_sign);
    $result = $bitpayx->mprequest($payData);

    $code_ajax = '';
    $webpaylink = '';
    if (($result['status'] === 200 || $result['status'] === 201) && $result['invoice']) {
        $base_url = 'https://www.zhihu.com/qrcode?url=';
        $click_url = '#';
        $qrcode_url = $base_url . $result['invoice']['qrcode'];

        $qrcode_img = '<img style="width: 200px" src="' . $qrcode_url . '" />';
        $qrcode_txt = '请用微信扫码（不可相册扫码）';
        $code_ajax = $qrcode_txt . '<a href="'.$click_url.'" target="_blank" id="wechatBitpayX" class="btn btn-info btn-block">'
            . $qrcode_img .
        '</a>';

    } else if ($result['status'] === 400 && $result['error_code'] === 'ORDER_MERCHANTID_EXIST' && $result['order'] && $result['invoice']) {
        if ($result['order']['status'] === 'NEW') {
            $base_url = 'https://www.zhihu.com/qrcode?url=';
            $click_url = '#';
            $qrcode_url = $base_url . $result['invoice']['qrcode'];

            $qrcode_img = '<img style="width: 200px" src="' . $qrcode_url . '" />';
            $qrcode_txt = '请用微信扫码（不可相册扫码）';
            $code_ajax = $qrcode_txt . '<a href="'.$click_url.'" target="_blank" id="wechatBitpayX" class="btn btn-info btn-block">'
                . $qrcode_img .
            '</a>';
        } else if ($result['order']['status'] === 'PAID') {
            $webpaylink = 'https://invoice.mugglepay.com/invoices/?id=' . $result['order']['order_id'] . '&lang=zh';
            $code_ajax = '<a href="'.$webpaylink.'" target="_blank" id="wechatBitpayX" class="btn btn-info btn-block">支付成功，等待商家确认</a>';
        }
    } else {
        $code_ajax = '<a href="#" id="wechatBitpayX" class="btn btn-info btn-block">支付确认中</a>';
    }

    $code = $code . '<div class="wechat" style="max-width: 230px;margin: 0 auto">' . $code_ajax . '</div>';
    return $code.'<script>
        //设置每隔 5000 毫秒执行一次 load() 方法
        setInterval(function(){loadBitpayX()}, 5000);
        function loadBitpayX(){
            var xmlhttp;
            if (window.XMLHttpRequest){
                // code for IE7+, Firefox, Chrome, Opera, Safari
                xmlhttp=new XMLHttpRequest();
            }else{
                // code for IE6, IE5
                xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp.onreadystatechange=function(){
                if (xmlhttp.readyState==4 && xmlhttp.status==200){
                    trade_state=xmlhttp.responseText;
                    if (trade_state.indexOf("SUCCESS") >= 0) {
                        document.getElementById("wechatBitpayX").innerHTML="支付成功";
                        window.location.reload()
                    }
                }
            }
            //invoice_status.php 文件返回订单状态，通过订单状态确定支付状态
            xmlhttp.open("get","'.$systemurl.'modules/gateways/bitpayx/query.php?invoiceid='.$params['invoiceid'].'",true);
            //下面这句话必须有
            //把标签/值对添加到要发送的头文件。
            //xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
            //xmlhttp.send("out_trade_no=002111");
            xmlhttp.send();
        }
    </script>';
}
?>
