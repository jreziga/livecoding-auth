<?php

/* TODO: if it is the case that refresh_token never changes
           then the params to this->refreshToken() will also never change and could be
           set in fetchTokens() as $this->token_req_params (like $token_req_params above)
         also in this case none of $client_id, $client_secret, $state,
           $redirect_url, $scope would be needed to be kept as members
*/

define('CURL_NOT_FOUND_MSG', 'This library requires that curl be available on this server.') ;
define('INVALID_CLIENT_ID_MSG', 'You must specify a client ID.' ;
define('INVALID_CLIENT_SECRET_MSG', 'You must specify a client secret.' ;
define('INVALID_REDIRECT_URL_MSG', 'You must specify a redirect URL.' ;


if(!class_exists('LivecodingAuth')) {

  class LivecodingAuth {

    private $client_id;
    private $client_secret;
    private $state;
    private $redirect_url;
    private $scope;
    private $is_authorized;
    private $auth_link;
    private $token_req_headers;

    function __construct($client_id, $client_secret, $redirect_url, $scope = 'read') {

      // Assert curl accessibilty and validate params
      if (!function_exists('curl_version')) throw new Exception(CURL_NOT_FOUND_MSG, 1);
      else if (empty($client_id)) throw new Exception(INVALID_CLIENT_ID_MSG, 1);
      else if (empty($client_secret)) throw new Exception(INVALID_CLIENT_SECRET_MSG, 1);
      else if (empty($redirect_url)) throw new Exception(INVALID_REDIRECT_URL_MSG, 1);

      $this->client_id = $client_id;
      $this->client_secret = $client_secret;
      $this->state = uniqid();
      $this->redirect_url = $redirect_url;
      $this->scope=$scope;
      $this->is_authorized=false;
      $this->tokens = new LivecodingAuthTokens() ;
      $this->getAuthLink = 'https://www.livecoding.tv/o/authorize/?'.
        'scope='.$this->scope.'&'.
        'state='.$this->state.'&'.
        'redirect_uri='.$this->redirect_url.'&'.
        'response_type=code&'.
        'client_id='.$this->client_id;
      $this->tokenReqHeaders = [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret),
      ];

      // Check the storage for existing tokens
      if ($this->tokens->hasTokens()) {
        // Here we are fully authorized from a previous request
        $this->is_authorized=true;
      }
      else if (isset($_GET['state'])                      &&
              $_GET['state'] == $this->tokens->getState() &&
              $this->setCode($_GET['code'])) {
        // Here we are returning from user auth approval link
        $this->is_authorized=true;
      }
      else {
        // Here we are not yet authorized (first visit)
        $this->is_authorized=false;

        //Save the state before displaying auth link
        $this->tokens->setState($this->state);
      }

    } // __construct

    public function fetchData($data_path) {
      // Refresh tokens from API server if necessary
      if ($this->tokens->is_stale()) $this->tokens->setTokens($this->refreshToken());

      // Retrieve some data:
      $data = $this->request($data_path);

      // Here we return some parsed JSON data - Caller can now do something interesting
      return $data;
    } // fetchData

    public function getIsAuthorized() {
      return $this->is_authorized;
    }

    public function getAuthLink() {
      return $this->getAuthLink;
    } // getAuthLink

    /**
     * Wrapper to make a get request
     */
    private function get_url_contents($url, $custom_header = []) {
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

    private function setCode($code) {
      $this->code = $code; fetchTokens($code);
    } // setCode


    private function fetchTokens() {
      $token_req_params = [
        "grant_type" => "authorization_code",
        "code" => $this->code,
        "redirect_uri" => $this->redirect_url
      ];
      $res = $this->post_url_contents("https://www.livecoding.tv/o/token/",
        $token_req_params, $this->token_req_headers);

/* TODO: see note at the top of the file
      $this->token_req_params = [
        "grant_type" => "refresh_token",
        "refresh_token" => $res->refresh_token ,
        "code" => $this->code,
        "redirect_uri" => $this->redirect_url
      ];
*/

      // Store access tokens
      $this->tokens->setTokens($res);
    } // fetchTokens

// TODO: check this - it may be that supplying 'code' with every 'refresh_token' request
//          may avoid having to manually authorize the app repeatedly
    private function refreshToken() {
      $res = $this->post_url_contents("https://www.livecoding.tv/o/token/", [
        "grant_type" => "refresh_token",
        "refresh_token" => $this->tokens->getRefreshToken() ,
        "code" => $this->code,
        "redirect_uri" => $this->redirect_url
      ], $this->token_req_headers);

/* TODO: see note at the top of the file
      $res = $this->post_url_contents("https://www.livecoding.tv/o/token/",
        $this->token_req_params, $this->token_req_headers);
*/

      // Store access tokens
      $this->tokens->setTokens($res);
    } // refreshToken

    private function request($request) {
      $url = 'https://www.livecoding.tv:443/api/'.$request;

      $res = $this->get_url_contents($url, [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: '.$this->tokens->getTokenType() .' ' . $this->tokens->getAccessToken(),
      ]);

      $res = json_decode($res);

      if(isset($res->error))
        return false;
      else
        return $res;
    } // request

  } // class LivecodingAuth
} // if(!class_exists)


if(!class_exists('LivecodingAuthTokens')) {

  class LivecodingAuthTokens {

    /**
    * Load token datas with an object
    **/
    public function setTokens($tokens) {
      $tokens = json_decode($tokens);
      if(isset($tokens->error)) return;

      $this->setAccessToken($tokens->access_token;
      $this->setTokenType($tokens->token_type;
      $this->setRefreshToken($tokens->refresh_token;
      $this->setExpiresIn('Y-m-d H:i:s', (time() + $tokens->expires_in));
      $this->setScope($tokens->scope);

      $this->storeTokens();
    } // setTokens

    /**
    * Determine if our access token need to be refreshed
    **/
    public function is_stale() {
      return (strtotime($this->getExpiresIn()) - time()) < 7200;
    } // is_stale


    // Subclasses should override these getters and setters

    public function hasTokens() {}

    private function storeTokens() {}

    public function getState() {}

    public function setState($state) {}

    private function getAccessToken() {}

    private function setAccessToken($access_token) {}

    private function getTokenType() {}

    private function setTokenType($token_type) {}

    private function getRefreshToken() {}

    private function setRefreshToken($refresh_token) {}

    private function getExpiresIn() {}

    private function setExpiresIn($expires_in) {}

    private function getScope() {}

    private function setScope($scope) {}
  }


  class LivecodingAuthTokensSession extends LivecodingAuthTokens {

    public function hasTokens() {
      return isset($_SESSION['tokens']) ;
    }

    private function storeTokens() {
      $tokens = new StdClass();
      $tokens->access_token = $this->getAccessToken();
      $tokens->token_type = $this->getTokenType();
      $tokens->refresh_token = $this->getRefreshToken();
      $tokens->expires_in = $this->getExpiresIn();
      $tokens->scope = $this->getScope();
      $_SESSION['tokens'] = $tokens;
    } // storeTokens

    public function getState() {
      return $_SESSION['state'] ;
    }

    public function setState($state) {
      $_SESSION['state'] = $state;
    }

    private function getAccessToken() {
      return $_SESSION['access_token'];
    } // getAccessToken

    private function setAccessToken($access_token) {
      $_SESSION['access_token'] = $access_token;
    } // setAccessToken

    private function getTokenType() {
      return $_SESSION['token_type'];
    } // getTokenType

    private function setTokenType($token_type) {
      $_SESSION['token_type'] = $token_type;
    } // setTokenType

    private function getRefreshToken() {
      return $_SESSION['refresh_token'];
    } // getRefreshToken

    private function setRefreshToken($refresh_token) {
      $_SESSION['refresh_token'] = $refresh_token;
    } // setRefreshToken

    private function getExpiresIn() {
      return $_SESSION['expires_in'];
    } // getExpiresIn

    private function setExpiresIn($expires_in) {
      $_SESSION['expires_in'] = $expires_in;
    } // setExpiresIn

    private function getScope() {
      return $_SESSION['scope'];
    } // getScope

    private function setScope($scope) {
      $_SESSION['scope'] = $scope;
    } // setScope

  } // class LivecodingAuthTokens

} // if(!class_exists)

?>
