<?php
/**
* This is an example file to help understanding how the api works.
* This script assumes that you have already created an app on the LCTV API website
*   and that you have selected the "confidential/authorization-grant" app type.
* The constants $CLIENT_ID, $CLIENT_SECRET, and $REDIRECT_URL below
*   must match your app configuration on the LCTV API website.
* Use this script like http://your-site.net/online-status.php?channel=my-lctv-channel.
**/

require('livecodingAuth.php');


session_start();

define("CLIENT_ID", getenv('LCTV_CLIENT_ID'));
define("CLIENT_SECRET", getenv('LCTV_CLIENT_SECRET'));
define("REDIRECT_URL", getenv('LCTV_REDIRECT_URL'));
define("CHANNEL_NAME", (isset($_SESSION['channel']))
                       ? $_SESSION['channel']
                       : (isset($_GET['channel']))
                         ? htmlspecialchars($_GET['channel'])
                         : null);
define('CHANNEL_DATA_PATH', 'livestreams/' . CHANNEL_NAME . '/');
define('INVALID_CHANNEL_MSG', 'You must specify a channel name like: example.php?channel=my-channel .');


// Validate channel name param
$CHANNEL_NAME = CHANNEL_NAME;
if (empty( $CHANNEL_NAME ))
  die(INVALID_CHANNEL_MSG);
else
  $_SESSION['channel'] = CHANNEL_NAME;
unset($CHANNEL_NAME);

// Instantiate auth helper
try {
  $LivecodingAuth = new LivecodingAuth(CLIENT_ID, CLIENT_SECRET, REDIRECT_URL);
}
catch(Exception $ex) {
  die($ex->getMessage());
}

// Check for previous authorization
if (!$LivecodingAuth->getIsAuthorized()) {

  // Here we have not yet been authorized

  // Display a link for the user to authorize the app with this script as the redirect URL
  $auth_link = $LivecodingAuth->getAuthLink();
  echo "This app is not yet authorized. Use the link or URL below to authorize it.<br/>";
  echo "<a href=\"$auth_link\">Connect my account</a><br/>" ;

  // Here we wait for the user to click the authorization link
  //   which will result in another request for this page
  //   with $LivecodingAuth->getIsAuthorized() then returning true.

} else {

  // Here we are authorized from a previous request

  // Fetch some data from the API
  $data = $LivecodingAuth->fetchData('livestreams/'.$_SESSION['channel'].'/', CHANNEL_STATUS_DATA_PATH);

  // Present the data
  $is_online = $data->is_live;
  echo CHANNEL_NAME . " is " . (($is_online) ? 'online' : 'offline') ;

}

?>
