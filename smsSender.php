<?php
/*
sms sender for ATL SMS Gateway API
by agamirza.com
*/


class smsSender extends Model {

  //Configuration
    private static $smsApi = 'https://sms.atltech.az:7443/bulksms/api';
    private static $sms_user = 'USERNAME';
    private static $sms_password = 'PASSWORD';
    private static $logging = false; // true|false (creation of log files)
    private static $logpath = '/path/to/logs/';
  //Configuration

    public $destination;
    public $message;
    public $taskid;

    public function __construct() {
        parent::__construct();
        $remain = $this->getRemain();
        if ($remain && $remain<200) {
            //$this->sendRemain($remain);
        }
    }

    public function sendSMS() {
        $destination = $this->destination;
        $msg = $this->message;
        $uid = md5($destination.time());
        $request = '<?xml version="1.0" encoding="UTF-8"?><request><head><operation>submit</operation><login>'.self::$sms_user.'</login><password>'.self::$sms_password.'</password><title>METAK</title><scheduled>NOW</scheduled><isbulk>false</isbulk><controlid>'.$uid.'</controlid><unicode>false</unicode></head><body><msisdn>'.$destination.'</msisdn><message>'.$msg.'</message></body></request>';
        $response = self::postURL(self::$smsApi, $request);
        if ($response && $repsonse != 'error' && $response = simplexml_load_string($response)) {
          $json = json_encode($response);
          $out = json_decode($json);
          $out->head->responsecode == '000' ? $result = true : $result = false;
        } else {
          $json = 'POST error'
          $result = false;
        }
        //log
        if (self::$logging) {
          $logBody = $destination.' | '. $msg .' | ';
          $logfile = self::$logpath.'/sms_log-'.date('Y-m-d').'.log';
          $handle = fopen($logfile, 'a');
          $string = date('Y-m-d H:i:s').' | '. $_SERVER['REMOTE_ADDR'] ."\n". $logBody ."\n".'response: '.$json."\n\n";
          $write = fwrite($handle, $string);
          fclose($handle);
        }
        //log
        if ($out->head->responsecode == '000') return true; else return false;
    }

    public function getRemain() {
        $request = '<?xml version="1.0" encoding="UTF-8"?><request><head><operation>units</operation><login>'.self::$sms_user.'</login><password>'.self::$sms_password.'</password></head></request>';
        $response = self::postURL(self::$smsApi, $request);
        $response = simplexml_load_string($response);
        $json = json_encode($response);
        $out = json_decode($json);
        if ($out && is_object($out) && isset($out->body->units)) {
            return $out->body->units;
        }
        return false;
    }

    public function getReport() {
        $request = '<?xml version="1.0" encoding="UTF-8"?><request><head><operation>report</operation><login>'.self::$sms_user.'</login><password>'.self::$sms_password.'</password><taskid>'.$this->taskid.'</taskid></head></request>';
        $response = self::postURL(self::$smsApi, $request);
        $response = simplexml_load_string($response);
        $json = json_encode($response);
        $out = json_decode($json);
        return $out;
    }
  
    public static function postURL($url, $post, $ssl=0, $timeout=15) {
          $curl = curl_init() ;
          curl_setopt($curl, CURLOPT_URL, $url);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
          if ($ssl == 0) curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($curl, CURLOPT_POST, true);
          curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
          curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
          curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
          $out = curl_exec($curl);

          if(curl_errno($curl)) {
              //log
              if (self::$logging) {
                $logfile = self::$logpath.'/post_log-'.date('Y-m-d').'.log';
                $handle = fopen($logfile, 'a');
                $string = date('Y-m-d H:i:s').' | '. "\n".'POST Error ' .$url."\n".curl_errno($curl)."\n\n";
                $write = fwrite($handle, $string);
                fclose($handle);
              }
              //log
              //$out = curl_errno($curl);
              $out = 'error';
          }
          curl_close($curl);
          return $out;
      }

}
