<?
require("autoload.php");

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;
use Facebook\GraphUser;
use Facebook\Entities\AccessToken;
use Facebook\HttpClients\FacebookCurlHttpClient;
use Facebook\HttpClients\FacebookHttpable;

//Facebook conifugration
session_start();






class FacebookSocialFetcher {
  private $url;
  private $helper;
  private $items = array();
  private $tokenOptionsKey = "social-fetcher-fb-access-token";
  private $fbIdOptionsKey = "social-fetcher-fb-user-id";
  private $globalMessageOptionsKey = "social-fetcher-global-message-warning";

  function __construct() {
    FacebookSession::setDefaultApplication('979326508760258', 'bb0df924a97fceeff431414965a31888');
    // FacebookSession::setDefaultApplication('135647529897166', 'd9bbd5df5449e66437d2a3933e5bad39');
    // $this->helper = new FacebookRedirectLoginHelper("http://localhost/wp/wp-admin/options-general.php?page=social-fetcher-options");
    $this->helper = new FacebookRedirectLoginHelper("http://rodon.bravomotorcompany.com.ar/wp-admin/options-general.php?page=social-fetcher-options");
    $this->checkIfNewSession();

  }


  private function checkIfNewSession() {

    try {
      $session = $this->helper->getSessionFromRedirect();
    }
    catch( FacebookRequestException $ex ) {
      echo $ex;
    }
    catch( Exception $ex ) {
      // When validation fails or other local issues
      echo $ex;
    }
     

    if(isset($session))
    {
      $user_profile = (new FacebookRequest(
        $session, 'GET', '/me'
      ))->execute()->getGraphObject(GraphUser::className());
      $token = $session->getToken();
      update_option($this->globalMessageOptionsKey, "");
      update_option($this->tokenOptionsKey, $token);
      update_option($this->fbIdOptionsKey, $user_profile->getId());
    }
  }


  public function getLoginUrl() {
    return $this->helper->getLoginUrl(array("read_stream"));
  }


  private function getSession() {

        $token = get_option( $this->tokenOptionsKey, "" );
        $session = new FacebookSession($token);
        // To validate the session:
        try {
            $session->validate();
            return($session);
        } catch (FacebookRequestException $ex) {
          // Session not valid, Graph API returned an exception with the reason.
          echo $ex->getMessage();
          return null;
        } catch (\Exception $ex) {
          // Graph API returned info, but it may mismatch the current app or have expired.
          echo $ex->getMessage();
          return null;
        }
  }


    function fetchData() {
      $session = $this->getSession();
      if (empty($session)) {
        echo "YOU SHOULD UPDATE YOUR AUTHENTICATION. LOG IN AGAIN IN FACEBOOK.";
      } else {
        $this->getData($session);
      }
    }



    private function getData($session, $page) {
         echo "<br> LOADING FACEBOOK LINKS";
        $request = new FacebookRequest(
            $session,
            'GET',
            '/'.get_option( $this->fbIdOptionsKey, "" ).'/links'
          );
         $response = $request->execute();
        $graphObject = $response->getGraphObject()->asArray();
        $this->saveData($graphObject[data]);
      
         echo "<br><br> LOADING FACEBOOK STATUSES";
        $request = new FacebookRequest(
          $session,
          'GET',
          '/'.get_option( $this->fbIdOptionsKey, "" ).'/statuses'
        );
         $response = $request->execute();
          $graphObject = $response->getGraphObject()->asArray();
        $this->saveData($graphObject[data]);

    }


  function saveData($items) {

    $complete = true;
    foreach ($items as $item) {
      $result = mysql_query("SELECT * FROM wp_postmeta WHERE meta_key = 'fb-id' AND meta_value = '".$item->id."'") or die(mysql_error());
      if (mysql_num_rows($result)) {
        $complete = false;
        echo "<br> DONE: post already loaded ".$item->id;
        break;
      } else {
        echo "<br> Importing post ". $item->id."";
        $date = str_replace("T", " ", $item->created_time);
        $date = str_replace("+0000", '', $date);
        $my_post_test = array(
          'post_title'    =>  $item->name ,
          'post_content'  => empty($item->message) ? $item->description : $item->message,
          'post_status'   => 'publish',
          'post_author'   => 1,
          'post_type' => "fb-post",
          'post_date' => $date
          //'post_category' => array( 8,39 )
        );
        $post_id = wp_insert_post( $my_post_test, $wp_error );
        add_post_meta($post_id, 'fb-id', $item->id);
        if (!empty($item->picture)) {
          add_post_meta($post_id, 'small-pic', $item->picture);
        }
        if (!empty($item->link)) {
          add_post_meta($post_id, 'link', $item->link);
        }
      }
    }

    if (!empty($token) && $complete) {
      $this->fetchData($token);
    } else {
      echo "<br> FACEBOOK JOB COMPLETED";
    }
  }

}
















  

?>