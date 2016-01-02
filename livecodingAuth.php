<?php

define('CURL_NOT_FOUND_MSG', 'This library requires that curl be available on this server.') ;
define('INVALID_CLIENT_ID_MSG', 'You must specify a client ID.' ;
define('INVALID_CLIENT_SECRET_MSG', 'You must specify a client secret.' ;
define('INVALID_REDIRECT_URL_MSG', 'You must specify a redirect URL.' ;
define('LCTV_TOKEN_URL', 'https://www.livecoding.tv/o/token/');
define('LCTV_API_URL', 'https://www.livecoding.tv:443/api/');
define("READ_SCOPE", 'read');                // Read basic public profile information
define("READVIEWER_SCOPE", 'read:viewer');   // Play live streams and videos for you
define("READUSER_SCOPE", 'read:user');       // Read your personal information
define("READCHANNEL_SCOPE", 'read:channel'); // Read private channel information
define("CHAT_SCOPE", 'chat');                // Access chat on your behalf
define("SESSION_STORE", 'session');
define("TEXT_STORE", 'flat-file');


if(!class_exists('LivecodingAuth')) {

  /**
    * @class LivecodingAuth - Negotiates and manages livecoding.tv API tokens and data requests
    */
  class LivecodingAuth {

    private $client_id;
    private $client_secret;
    private $redirect_url;
    private $scope;
    private $state;
    private $is_authorized;
    private $auth_link;
    private $token_req_headers;
    private $token_req_params;
    private $api_req_params;

    /**
     * Negotiates and manages livecoding.tv API tokens and data requests
     * @param string $client_id     - As defined in your LCTV API app configuration
     * @param string $client_secret - As defined in your LCTV API app configuration
     * @param string $redirect_url  - As defined in your LCTV API app configuration
     * @param string $scope         - One of the *_SCOPE constants (default: 'read')
     * @param string $storage       - One of the *_STORE constants (default: 'session')
     * @throws Exception            - If curl not accessible or if missing credentials
     */
    function __construct($client_id, $client_secret, $redirect_url,
                         $scope = READ_SCOPE, $storage = SESSION_STORE) {
      // Assert curl accessibilty and validate params
      if (!function_exists('curl_version')) throw new Exception(CURL_NOT_FOUND_MSG, 1);
      else if (empty($client_id)) throw new Exception(INVALID_CLIENT_ID_MSG, 1);
      else if (empty($client_secret)) throw new Exception(INVALID_CLIENT_SECRET_MSG, 1);
      else if (empty($redirect_url)) throw new Exception(INVALID_REDIRECT_URL_MSG, 1);

      // Initialize data members
      $this->client_id = $client_id;
      $this->client_secret = $client_secret;
      $this->redirect_url = $redirect_url;
      $this->scope = $scope;
      $this->state = uniqid();
      if ($storage == TEXT_STORE)
        $this->tokens = new LivecodingAuthTokensText();
      else // ($storage == SESSION_STORE)
        $this->tokens = new LivecodingAuthTokensSession();
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
      $this->token_req_params = [
        'grant_type' => '',
        'code' => $this->tokens->getCode(),
        'redirect_uri' => $this->redirect_url
      ];
      $this->api_req_params = [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: TOKEN_TYPE_DEFERRED ACCESS_TOKEN_DEFERRED'
      ];


      // Check the storage for existing tokens
      if ($this->tokens->isAuthorized()) {
        // Here we are authorized from a previous request

        // Nothing to do - yay
      }
      else if (isset($_GET['state']) && $_GET['state'] == $this->tokens->getState()) {
        // Here we are returning from user auth approval link
        $this->fetchTokens($_GET['code']) ;
      }
      else {
        // Here we have not yet been authorized

        // Save the state before displaying auth link
        $this->tokens->setState($this->state);
      }

    } // __construct

    /**
     * Request some data from the API
     * @param string $data_path - The data to get e.g. 'livestreams/channelname/'
     * @return string           - The requested data as JSON string or error message
     */
    public function fetchData($data_path) {
      // Refresh tokens from API server if necessary
      if ($this->tokens->is_stale()) $this->refreshToken();

      // Retrieve some data:
      $data = $this->sendGetRequest($data_path);

      // Here we return some parsed JSON data - Caller can now do something interesting
      return $data;
    } // fetchData

    /**
     * Check if auth tokens exist and we are prepared to make API requests
     * @return boolean - Returns TRUE if the app is ready to make requests,
     *                       or FALSE if user authorization is required
     */
    public function getIsAuthorized() {
      return $this->tokens->isAuthorized();
    }

    /**
     * Get link URL for manual user authorization
     * @return string - The URL for manual user authorization
     */
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
        curl_setopt($crl, CURLOPT_URL ,$url);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);

        return $ret;
    } // get_url_contents

    /**
     * Wrapper to make a post request
     */
    private function post_url_contents($url, $fields, $custom_header = []) {

        foreach($fields as $key=>$value)
          $fields_string .= $key.'='.urlencode($value).'&';

        rtrim($fields_string, '&');

        $crl = curl_init();
        $timeout = 5;

        curl_setopt($crl, CURLOPT_HTTPHEADER, $custom_header);

        curl_setopt($crl, CURLOPT_URL, $url);
        curl_setopt($crl, CURLOPT_POST, count($fields));
        curl_setopt($crl, CURLOPT_POSTFIELDS, $fields_string);

        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);

        return $ret;
    } // post_url_contents

    /**
     * Fetch initial tokens after manual user auth
     * @param string $code - Auth code returned by the API in redirect URL params
     */
    private function fetchTokens($code) {
      $this->tokens->setCode($code);
      $this->token_req_params['code'] = $code;
      $this->token_req_params['grant_type'] = 'authorization_code';
      $res = $this->post_url_contents(LCTV_TOKEN_URL,
        $this->token_req_params, $this->token_req_headers);

      // Store access tokens
      $this->tokens->storeTokens($res);
    } // fetchTokens

// TODO: check this - it may be that supplying 'code' with every 'refresh_token' request
//          may avoid having to manually authorize the app repeatedly (issue #4)
    /**
     * Refresh stale tokens
     */
    private function refreshToken() {
      $this->token_req_params['grant_type'] = 'refresh_token';
      $this->token_req_params['refresh_token'] = $this->tokens->getRefreshToken();
      $res = $this->post_url_contents(LCTV_TOKEN_URL,
        $this->token_req_params, $this->token_req_headers);

      // Store access tokens
      $this->tokens->storeTokens($res);
    } // refreshToken

    /**
     * Request API data
     * @param string $data_path - The data to get e.g. 'livestreams/channelname/'
     * @return string           - The requested data as JSON string or error message
     */
    private function sendGetRequest($data_path) {
      $this->api_req_params[2] = $this->tokens->makeAuthParam();
      $res = $this->get_url_contents(LCTV_API_URL.$data_path, $this->api_req_params);

      $res = json_decode($res);

      if(isset($res->error))
        return "{ error: '$res->error' }";
      else
        return $res;
    } // sendGetRequest

  } // class LivecodingAuth
} // if(!class_exists)


if(!class_exists('LivecodingAuthTokens')) {

  /**
  * @class LivecodingAuthTokens
  * LivecodingAuthTokens is intended to be semi-abstract
  * Only its subclasses should be instantiated
  **/
  class LivecodingAuthTokens {

    /**
    * Store token data to subclass defined backend
    **/
    public function storeTokens($tokens) {
      $tokens = json_decode($tokens);
      if(!isset($tokens->error))
      {
        $this->setAccessToken($tokens->access_token;
        $this->setTokenType($tokens->token_type;
        $this->setRefreshToken($tokens->refresh_token;
        $this->setExpiresIn('Y-m-d H:i:s', (time() + $tokens->expires_in));
        $this->setScope($tokens->scope);
      }
    } // storeTokens

    /**
    * Determine if our access token needs to be refreshed
    **/
    public function is_stale() {
      return (strtotime($this->getExpiresIn()) - time()) < 7200;
    } // is_stale

    /**
    * Concatenate current auth token to param string for data request
    **/
    public function makeAuthParam() {
      return 'Authorization: '.$this->getTokenType().' '.$this->getAccessToken();
    }


    // Subclasses should override these getters and setters

    public function isAuthorized() {}

    public function getCode() {}

    public function setCode() {}

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

} // if(!class_exists('LivecodingAuthTokens'))


if(!class_exists('LivecodingAuthTokensSession')) {

  /**
  * @class LivecodingAuthTokensSession
  * A LivecodingAuthTokens subclass using session storage
  **/
  class LivecodingAuthTokensSession extends LivecodingAuthTokens {
    function __construct() {
      if (!isset($_SESSION))
        session_start();
    } // __construct

    public function isAuthorized() {
      return isset($_SESSION['code']);
    } // isAuthorized

    public function getCode() {
      return $_SESSION['code'] ;
    } // getCode

    public function setCode($code) {
      $_SESSION['code'] = $code;
    } // setState

    public function getState() {
      return $_SESSION['state'] ;
    } // getState

    public function setState($state) {
      $_SESSION['state'] = $state;
    } // setState

    private function getScope() {
      return $_SESSION['scope'];
    } // getScope

    private function setScope($scope) {
      $_SESSION['scope'] = $scope;
    } // setScope

    private function getTokenType() {
      return $_SESSION['token_type'];
    } // getTokenType

    private function setTokenType($token_type) {
      $_SESSION['token_type'] = $token_type;
    } // setTokenType

    private function getAccessToken() {
      return $_SESSION['access_token'];
    } // getAccessToken

    private function setAccessToken($access_token) {
      $_SESSION['access_token'] = $access_token;
    } // setAccessToken

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
  } // class LivecodingAuthTokens

} // if(!class_exists('LivecodingAuthTokensSession'))


if(!class_exists('LivecodingAuthTokensText')) {

  /**
  * @class LivecodingAuthTokensText
  * A LivecodingAuthTokens subclass using session storage
  **/
  class LivecodingAuthTokensText extends LivecodingAuthTokens {
    public function isAuthorized() {
      return file_exists('code') ;
    } // isAuthorized

    public function getCode() {
      return file_get_contents('code') ;
    } // getCode

    public function setCode($code) {
      file_put_contents('code', $code);
    } // setState

    public function getState() {
      return file_get_contents('state') ;
    } // getState

    public function setState($state) {
      file_put_contents('state', $state);
    } // setState

    private function getScope() {
      return file_get_contents('scope');
    } // getScope

    private function setScope($scope) {
      file_put_contents('scope', $scope);
    } // setScope

    private function getTokenType() {
      return file_get_contents('token_type');
    } // getTokenType

    private function setTokenType($token_type) {
      file_put_contents('token_type', $token_type);
    } // setTokenType

    private function getAccessToken() {
      return file_get_contents('access_token');
    } // getAccessToken

    private function setAccessToken($access_token) {
      file_put_contents('access_token', $access_token);
    } // setAccessToken

    private function getRefreshToken() {
      return file_get_contents('refresh_token');
    } // getRefreshToken

    private function setRefreshToken($refresh_token) {
      file_put_contents('refresh_token', $refresh_token);
    } // setRefreshToken

    private function getExpiresIn() {
      return file_get_contents('expires_in');
    } // getExpiresIn

    private function setExpiresIn($expires_in) {
      file_put_contents('expires_in', $expires_in);
    } // setExpiresIn
  } // class LivecodingAuthTokensText

} // if(!class_exists('LivecodingAuthTokensText'))

?>
