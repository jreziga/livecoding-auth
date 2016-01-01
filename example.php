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
define('INVALID_CHANNEL_MSG', 'You must specify a channel name like: online-status.php?channel=my-channel .');

if (isset($DEBUG)) {
  echo "isset(_SESSION['channel'])=" . (isset($_SESSION['channel'])) ? "yes" : "no") . "<br/>";
  echo "lctv_user=$lctv_user<br/>";
  var_dump($_SESSION);
}

function presentOnlineStatus($data) {
  $is_online = $data->is_live;

  echo CHANNEL_NAME . " is " . (($is_online) ? 'online' : 'offline') ;
}

function presentAuthLink($auth_link) {
  // Display a link for the user to authorize the app with this script as the redirect URL
  echo "This app is not yet authorized. Use the link or URL below to authorize it.<br/>";
  echo "<a href=\"$auth_link\">Connect my account</a><br/>" ;
  echo "$auth_link<br/>";
}


// Validate channel name param
if (empty(constant('CHANNEL_NAME'))) {
  die(INVALID_CHANNEL_MSG);
}
else {
  $_SESSION['channel'] = CHANNEL_NAME;
}

// Instantiate auth helper
try {
  $LivecodingAuth = new LivecodingAuth($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URL);
}
catch(Exception $ex) {
  die($ex->getMessage());
}

if (!$LivecodingAuth->getIsAuthorized()) {
  // Present link for user manual auth
  presentAuthLink($LivecodingAuth->getAuthLink());

  // Here we wait for the user to click the authorization link
  //   which will result in another request for this page
  //   and $LivecodingAuth->getIsAuthorized() should then be true.
} else {

  // Fetch some data
  $data = $LivecodingAuth->fetchData($LivecodingAuth, CHANNEL_STATUS_DATA_PATH);

  // Present the data
  presentOnlineStatus($data);
}

?>
