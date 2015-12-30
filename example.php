<?php
/****
* This is an example file to help understanding how the api work
**/

session_start();

require('livecodingAuth.php');

$LivecodingAuth = new LivecodingAuth(
  'INSERT YOUR CLIENT_ID',
  'INSERT YOUR CLIENT_SECRET',
  'http://localhost/example.php'
);

if(isset($_SESSION['tokens'])) {
  $LivecodingAuth->setTokens($_SESSION['tokens']);
}

//Checking data after approuved auth
if(isset($_GET['state'])) {
  if($_GET['state'] == $_SESSION['state']) {
    if($LivecodingAuth->checkCode($_GET['code'])) {
      // Now we got the access token
      $_SESSION['tokens'] = $LivecodingAuth->getTokens();
    }
  }
}

if(!isset($_SESSION['tokens'])) {
  //Save the state before displaying the link
  $_SESSION['state'] = $LivecodingAuth->getState();
  // Display the link to get credentials from the user
  ?>
  <a href="<?php echo $LivecodingAuth->getAuthLink(); ?>">Connect my account</a>
  <?php
}
else {
    // Doing some stuff

    // Retrieve user data :
    var_dump($LivecodingAuth->request('user/'));

    //Refresh the token (which expire 10 hours after creation)
    $LivecodingAuth->refreshToken();
    //Save new tokens
    $_SESSION['tokens'] = $LivecodingAuth->getTokens();
}
