<?php

class coffeecms_pointback
{
    public static $verify_password='123456789';

    // Your coffee cms 'paygate_pointback' api url
    public static $listUrlPointBack=[
        'http://site.com/api/paygate_pointback'
    ];

    public static function sendPointBack($order_session)
    {
        $total=count(self::$listUrlPointBack);

        $result='';
        for ($i=0; $i < $total; $i++) { 
            if(strlen(self::$listUrlPointBack[$i]) > 5)
            {
                $url=self::$listUrlPointBack[$i]."?order_id=".$order_session['order_id']."&payment_method=".urlencode($order_session['payment_method']['title'])."&verify_password=".self::$verify_password."&ip_add=".base64_encode($_SERVER['REMOTE_ADDR']);

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, false);
                $data = curl_exec($curl);
                curl_close($curl);
            }
        }
    }

}