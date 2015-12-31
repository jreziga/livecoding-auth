<?php
/**
* This is an example file to help understanding how the api work.
* This script assumes you have already created an app on the LCTV API website
*   and you have selected the "confidential/authorization-grant" app type.
* The constants $CLIENT_ID, $CLIENT_SECRET, and $REDIRECT_URL below
*   must match your app configuration on the LCTV API website.
* Use this script like http://your-site.net/online-status.php?channel=my-channel.
**/
require('livecodingAuth.php');


$CLIENT_ID     = getenv('LCTV_CLIENT_ID');
$CLIENT_SECRET = getenv('LCTV_CLIENT_SECRET');
$REDIRECT_URL  = $_SERVER['REQUEST_URI'];
$lctv_user     = htmlspecialchars($_GET['channel']);


function fetchData($LivecodingAuth) {

  // reload tokens from storage
  $LivecodingAuth->setTokens($_SESSION['tokens']);

  // Retrieve some data:
  $data = $LivecodingAuth->request("v1/livestreams/$lctv_user/");

var_dump($data) ;

  $is_online = $data->is_live;

echo "$channel is " . (($is_online) ? 'online' : 'offline') ;

  // Refresh the token (which expire 10 hours after creation)
  $LivecodingAuth->refreshToken();

  // Save new tokens
  $_SESSION['tokens'] = $LivecodingAuth->getTokens();

}


session_start();

// validate channel name param
$INVALID_CHANNEL_MSG = "You must specify a channel name like online-status.php?channel=my-channel" ;
if (empty($lctv_user)) { echo $INVALID_CHANNEL_MSG; exit ; }
else $_SESSION['channel'] = $lctv_user;

// instantiate auth helper
$LivecodingAuth = new LivecodingAuth($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URL);

// Check the session hash for existing tokens
if (isset($_SESSION['tokens'])) { // Here we are fully authorized from a previous request

  fetchData($LivecodingAuth);

}

else if (isset($_GET['state'])                   &&
         $_GET['state'] == $_SESSION['state']    &&
         $LivecodingAuth->checkCode($_GET['code'])) { // Here we are returning from user auth approval link

  // Load new access token (this should only need to happen once)
  $_SESSION['tokens'] = $LivecodingAuth->getTokens();

  fetchData($LivecodingAuth);
}

else { // Here we are not yet authorized (first visit)

  //Save the state before displaying the link
  $_SESSION['state'] = $LivecodingAuth->getState();

  // Display a link for the user to authorize the app with this script as the redirect URL
  $authLink = $LivecodingAuth->getAuthLink();
  echo "This app is not yet authorized. Use the link or URL below to authorize it.<br/>";
  echo "<a href=\"$authLink\">Connect my account</a><br/>" ;
  echo "$authLink<br/>";

  // Here we die and wait for the user to click the link above
  //   which will result in another request for this script.
}

?>
