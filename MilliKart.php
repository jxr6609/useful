<?php
/******************
https://github.com/jxr6609
/******************
Make payment
$payment = new Millikart();
$payment->Amount = '23.50'; //Set payment amount
$payment->OrderId = '123456'; //Set order identificator
$payment->Language = 'en'; //Set payment gateway page language
$result = $payment->makePayment();
$result returns false if any error (can be checked by getOrderStatus() method) or returns object with 'url' key contains link to payment gateway page

Check payment/order status
$payment = new Millikart();
$payment->OrderId = '123456'; //Set order identificator
$result = $payment->getOrderStatus();
$result returns object with keys 'status' and 'params'
$result->status - 0 (payment unsuccessful) | 1 (payment successful)
$result->params - transaction details provided by payment gateway
******************/

class Millikart {

/* Test environment */
    static $host = 'https://test.millikart.az:7444/gateway/payment'; 
    static $Merchant = 'store1'; //Merchant ID provided by operator
    static $SecretKEY = '123456'; //Auth key provided by operator
    static $ReturnUrl = 'http://site.com/returnurl'; //Return url for site
/* Test environment */

/* Production environment */
    //static $host = 'https://pay.millikart.az/gateway/payment/';
    //static $Merchant = 'store1'; //Merchant ID provided by operator
    //static $SecretKEY = '123456'; //Auth key provided by operator
    //static $ReturnUrl = 'http://site.com/returnurl'; //Return url for site
/* Production environment */

    static $OrderType = 'Purchase'; //Operation type
    static $Currency = 944; //Currency code

    // DO NOT EDIT
    public $Amount;
    public $OrderId;
    public $Language;

    public function verifyLang($lang) {
        if (in_array($lang, array('ru', 'en', 'az'))) return $lang; else return 'en';
    }

    public function makePayment() {
        $amount = intval($this->Amount*100);
        $description = 'Purchase';
        $language = $this->verifyLang($this->Language);
        $signature = Strtoupper(md5(strlen(self::$Merchant).self::$Merchant.strlen($amount).$amount.strlen(self::$Currency).self::$Currency.(!empty($description)?strlen($description).$description :"0").strlen($this->OrderId).$this->OrderId.strlen($language).$language.self::$SecretKEY));
        $request = self::$host.'/register?mid='.self::$Merchant.'&amount='.$amount.'&currency='.self::$Currency.'&description='.$description.'&reference='.$this->OrderId.'&language='.$language.'&signature='.$signature;
        $response = self::getURL($request);
        if ($response && $response != 'error') {
            $response = simplexml_load_string($response);
            $json = json_encode($response);
            $data = json_decode($json);
            if ($data->code == 0 && isset($data->redirect)) {
                $out = new stdClass();
                $out->url = $data->redirect;
                $out->params = array();
                return $out;
            }
        }
        return false;
    }

    public function getOrderStatus() {
        $request = self::$host.'/status?mid='.self::$Merchant.'&reference='.$this->OrderId;
        $response = self::getURL($request);
        if ($response && $response != 'error') {
            $response = simplexml_load_string($response);
            $json = json_encode($response);
            $data = json_decode($json);
            if ($data && isset($data->code)) {
                $status = 0;
                $params = array(
                    'transaction' => '',
                    'session' => $data->xid,
                    'rrn' => $data->rrn,
                    'pan' => '',
                    'status' => 0,
                    'resp_code' => $data->code,
                    'resp_text' => $data->description,
                    'trn_time' => date('Y-m-d H:i:s', strtotime($data->timestamp))
                );
                if ($data->code == 0) {
                    $params['transaction'] = $data->approval;
                    $params['pan'] = $data->pan;
                    if (isset($data->RC)) {
                        $params['resp_code'] = $data->RC;
                        $params['resp_text'] = self::statusCode($data->RC);
                        if ($data->RC == '000') {
                            $status = 1;
                            $params['status'] = 1;
                        }
                    }
                }
                $out = new stdClass();
                $out->status = $status;
                $out->params = $params;
                return $out;
            }
        }
        return false;
    }

    private static function statusCode($code) {
        $codes = array(
            '-1' => 'Unknown', //Uğursuz əməliyyat
            '0' => 'OK',		//Uğurlu əməliyyat
            '1' => 'Failed', 		//Uğursuz əməliyyat
            '2' => 'Created',		//Əməliyyat sona çatdırılmayıb
            '3' => 'Pending',		//Əməliyyat sona çatdırılmayıb
            '4' => 'Declined', 		//Uğursuz əməliyyat
            '5' => 'Reversed',		//Əməliyyat geri qaytarılıb (Merçant tərəfindən əməliyyat ləğv edilib)
            '7' => 'Timeout',		//Uğursuz əməliyyat (əməliyyat sona çatdırılmayıb)
            '9' => 'Cancelled',		//Uğursuz əməliyyat (Müştəri tərəfindən əməliyyat ləğv edilib)
            '10' => 'Returned',		//Əməliyyat geri qaytarılıb (Merçant tərəfindən əməliyyat ləğv edilib)
            '11' => 'Active',		//Əməliyyat sona çatdırılmayıb
            '12' => 'Attempt',		//Əməliyyat sona çatdırılmayıb (3DS yoxlanışı attempt kimi keçmişdir)
            '13' => 'Pending3DS',	//Əməliyyat sona çatdırılmayıb (OTP kod daxil edilməyib)
            '000' => 'Successful transaction',	//Uğurlu əməliyyat
            '101' => 'Decline, expired card',	//Uğursuz əməliyyat
            '119' => 'Decline, transaction not permitted to cardholder',	//Uğursuz əməliyyat
            '100' => 'Decline (general, no comments)',	//Uğursuz əməliyyat
        );
        if (array_key_exists($code, $codes)) return $codes[$code];
        return 'Unknown code';
    }

    private static function getURL($url, $ssl=0) {
        $curl = curl_init() ;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        if ($ssl == 0) curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $out = curl_exec($curl);
        if(curl_errno($curl)) {
            $out = 'error';
        }
        curl_close($curl);
        return $out;
    }

}
