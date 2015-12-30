<?php


if(!class_exists('LivecodingAuth')) {

  class LivecodingAuth {

    private $client_id;
    private $client_secret;
    private $state;
    private $scope;
    private $redirect_url;
    private $access_token;
    private $token_type;
    private $refresh_token;
    private $expires_in; //Expiration of the token, datetime format

    function __construct($client_id, $client_secret, $redirect_url, $scope = 'read') {
      $this->client_id = $client_id;
      $this->client_secret = $client_secret;
      $this->state = uniqid();
      $this->scope = $scope;
      $this->redirect_url = $redirect_url;
    } // __construct

    /**
    * Load token datas with an object
    **/
    public function setTokens($tokens) {
      $this->access_token = $tokens->access_token;
      $this->token_type = $tokens->token_type;
      $this->expires_in = $tokens->expires_in;
      $this->refresh_token = $tokens->refresh_token;
      $this->scope = $tokens->scope;
    }

    public function getTokens() {
      $tokens = new StdClass();
      $tokens->access_token = $this->access_token;
      $tokens->token_type = $this->token_type;
      $tokens->expires_in = $this->expires_in;
      $tokens->refresh_token = $this->refresh_token;
      $tokens->scope = $this->scope;
      return $tokens;
    } // getTokens

    /**
     * Wrapper to make a get request
     */
    private function get_url_contents($url, $custom_header = []){
        $crl = curl_init();
        $timeout = 5;
        curl_setopt($crl, CURLOPT_HTTPHEADER, $custom_header);
        curl_setopt ($crl, CURLOPT_URL,$url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);
        return $ret;
    } // get_url_contents

    /**
     * Wrapper to make a post request
     */
    private function post_url_contents($url, $fields, $custom_header = []) {

        foreach($fields as $key=>$value) { $fields_string .= $key.'='.urlencode($value).'&'; }
        rtrim($fields_string, '&');

        $crl = curl_init();
        $timeout = 5;

        curl_setopt($crl, CURLOPT_HTTPHEADER, $custom_header);

        curl_setopt($crl, CURLOPT_URL,$url);
        curl_setopt($crl,CURLOPT_POST, count($fields));
        curl_setopt($crl,CURLOPT_POSTFIELDS, $fields_string);

        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);
        return $ret;
    } // post_url_contents

    public function checkCode($code) {

      $res = $this->post_url_contents("https://www.livecoding.tv/o/token/", [
        "code" => $code,
        "grant_type" => "authorization_code",
        "redirect_uri" => $this->redirect_url
      ], [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret),
      ]);

      $res = json_decode($res);

      if(isset($res->error))
        return false;
      else {
        $this->access_token = $res->access_token;
        $this->token_type = $res->token_type;
        $this->refresh_token = $res->refresh_token;
        $this->expires_in = date('Y-m-d H:i:s', (time() + $res->expires_in));
        return $res;
      }
    } // checkCode

    public function refreshToken() {
      $res = $this->post_url_contents("https://www.livecoding.tv/o/token/", [
        "refresh_token" => $this->$refresh_token,
        "grant_type" => "refresh_token",
      ], [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret),
      ]);

      $res = json_decode($res);

      if(isset($res->error))
        return false;
      else {
        $this->access_token = $res->access_token;
        $this->token_type = $res->token_type;
        $this->refresh_token = $res->refresh_token;
        $this->expires_in = date('Y-m-d H:i:s', (time() + $res->expires_in));
        return $res;
      }
    } // refreshToken

    public function request($request) {
      $url = 'https://www.livecoding.tv:443/api/'.$request;

      $res = $this->get_url_contents($url, [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: '.$this->token_type.' '.$this->access_token,
      ]);

      $res = json_decode($res);

      if(isset($res->error))
        return false;
      else
        return $res;
    }

    public function getAuthLink() {
      return 'https://www.livecoding.tv/o/authorize/?'.
              'scope='.$this->scope.'&'.
              'state='.$this->state.'&'.
              'redirect_uri='.$this->redirect_url.'&'.
              'response_type=code&'.
              'client_id='.$this->client_id;
    } // getAuthLink

    public function getState() {
      return $this->state;
    } // getState

    public function getAccessToken() {
      return $this->access_token;
    }

    public function setAccessToken($access_token) {
      $this->access_token = $access_token;
    }

    public function getTokenType() {
      return $this->token_type;
    }

    public function setTokenType($token_type) {
      $this->token_type = $token_type;
    }

    public function getRefreshToken() {
      return $this->refresh_token;
    }

    public function setRefreshToken($refresh_token) {
      $this->refresh_token = $refresh_token;
    }

    public function getExpiration() {
      return $this->expires_in;
    }

    public function setExpiration($expires_in) {
      $this->expires_in = $expires_in;
    }

  } // class LivecodingAuth
} // if(!class_exists)

?>
