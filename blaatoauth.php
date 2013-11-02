<?php
/*
Plugin Name: BlaatSchaap OAuth Plugin
Plugin URI: http://code.blaatschaap.be
Description: Log in with an OAuth Provider
Version: 0.1
Author: André van Schoubroeck
Author URI: http://andre.blaatschaap.be
License: 3 Clause BSD
*/


session_start();
ob_start();


load_plugin_textdomain('blaat_auth', false, basename( dirname( __FILE__ ) ) . '/languages' );

function blaat_register_pageoptions(){
  register_setting( 'blaat_auth_pages', 'login_page' );
  register_setting( 'blaat_auth_pages', 'register_page' );
  register_setting( 'blaat_auth_pages', 'link_page' );
}

if (!function_exists("blaat_page_select")) {
  function blaat_page_select($item){
    $pages = get_pages();
    $blaat = "<select id='$item' name='$item'>";
    foreach ( $pages as $page ) {
      $pagename = $page->post_name;
      $selected = (get_option($item)==$pagename) ? "selected='selected'" : "";
      $option = "<option value='$pagename' $selected>";
      $option .= $page->post_title;
      $option .= "</option>";
      $blaat .= $option;
    }
    $blaat .= "</select>";
    return $blaat;  
  }
}

if (!function_exists("blaat_plugins_page")) {
  function blaat_plugins_page(){
    echo "BlaatSchaap Plugins";
  }
}

if (!function_exists("blaat_plugins_auth_page")) {
  function blaat_plugins_auth_page(){
    //echo "Thank you for using the BlaatSchaap Coding Projects WordPress Authentication Plugins";
    echo '<div class="wrap">';
    
    echo '<h2>BlaatSchaap WordPress Authentication Plugins</h2>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'blaat_auth_pages' ); 
    //do_settings( 'blaat_auth_pages' );  undefined? it was mentioned

    echo '<table class="form-table">';

    echo '<tr><td>Login page</td><td>';
    echo blaat_page_select("login_page");
    echo '</td></tr>';
    
    echo '<tr><td>Register page</td><td>';
    echo blaat_page_select("register_page");
    echo '</td></tr>';

    echo '<tr><td>Link page</td><td>';
    echo blaat_page_select("link_page");
    echo '</td></tr>';

    echo '</table><input name="Submit" type="submit" value="';
    echo  esc_attr_e('Save Changes') ;
    echo '" ></form></div>';

  }
}



require_once("oauth/oauth_client.php");
require_once("oauth/http.php");
//require_once("bs_wp_oauth.php");

require_once("bs_oauth_config.php");

function  blaat_oauth_install() {
  global $wpdb;
  global $bs_oauth_plugin;

  $table_name = $wpdb->prefix . "bs_oauth_sessions";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
    $query = "CREATE TABLE $table_name (
              `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `user_id` INT NOT NULL DEFAULT 0,
              `service_id` TEXT NOT NULL ,
              `token` TEXT NOT NULL ,
              `authorized` BOOLEAN NOT NULL ,
              `expiry` DATETIME NULL DEFAULT NULL ,
              `type` TEXT NULL DEFAULT NULL ,
              `refresh` TEXT NULL DEFAULT NULL,
              `scope` TEXT NOT NULL DEFAULT ''
              ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = 'OAuth Sessions';";
    $result = $wpdb->query($query);
  }

 
  $table_name = $wpdb->prefix . "bs_oauth_services";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
    $query = "CREATE TABLE $table_name (
              `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `enabled` BOOLEAN NOT NULL DEFAULT FALSE ,
              `display_name` TEXT NOT NULL ,
              `client_name` TEXT NULL DEFAULT NULL ,
              `custom_id` INT NULL DEFAULT NULL ,
              `client_id` TEXT NOT NULL ,
              `client_secret` TEXT NOT NULL,
              `default_scope` TEXT NOT NULL DEFAULT ''
              ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = 'OAuth Services';";
    $result = $wpdb->query($query);
  }


  $table_name = $wpdb->prefix . "bs_oauth_custom";
  if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
    $query = "CREATE TABLE $table_name (
              `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `oauth_version` ENUM('1.0','1.0a','2.0') DEFAULT '2.0',
              `request_token_url` TEXT NULL DEFAULT NULL,
              `dialog_url` TEXT NOT NULL,
              `access_token_url` TEXT NOT NULL,
              `url_parameters` BOOLEAN DEFAULT FALSE,
              `authorization_header` BOOLEAN DEFAULT TRUE,
              `offline_dialog_url` TEXT NULL DEFAULT NULL,
              `append_state_to_redirect_uri` TEXT NULL DEFAULT NULL
              ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = 'OAuth Custom Services';";
    $result = $wpdb->query($query);
  }


  

}


function blaat_oauth_menu() {
  add_menu_page('BlaatSchaap', 'BlaatSchaap', 'manage_options', 'blaat_plugins', 'blaat_plugins_page');
  add_submenu_page('blaat_plugins', "" , "" , 'manage_options', 'blaat_plugins', 'blaat_plugins_page');

  add_submenu_page('blaat_plugins', "Auth Pages" , "Auth pages" , 'manage_options', 'blaat_auth_pages_plugins', 'blaat_plugins_auth_page');
  add_submenu_page('blaat_plugins' , 'OAuth Configuration', 'OAuth Configuration', 'manage_options', 'blaat_oauth_services', 'blaat_oauth_config_page' );
  add_submenu_page('blaat_plugins' , 'OAuth Add Service',   'OAuth Add', 'manage_options', 'blaat_oauth_add', 'blaat_oauth_add_page' );
  add_submenu_page('blaat_plugins' , 'OAuth Add Custom Service',   'OAuth Add Custom', 'manage_options', 'blaat_oauth_custom', 'blaat_oauth_add_custom_page' );
  add_action( 'admin_init', 'blaat_register_pageoptions' );
}

function blaat_oauth_config_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
        screen_icon();
        echo "<h2>BlaatSchaap OAuth Configuration</h2>";
	//echo '<p>Here is where the form would go if I actually had options.</p>';
	//echo '</div>';
        if ($_POST['add_service']) blaat_oauth_add_process();
        if ($_POST['add_custom_service']) blaat_oauth_add_custom_process();
        if ($_POST['delete_service']) blaat_oauth_delete_service();
        if ($_POST['update_service']) blaat_oauth_update_service();
        echo "<h2>Configured Services</h2><hr>";
        blaat_oauth_list_services();
        echo '<hr>';

}


function blaat_oauth_do_login(){
  blaat_oauth_process("blaat_oauth_process_login");
}

function blaat_oauth_process_login($client, $displayname){
  global $wpdb;
  $_SESSION['oauth_display'] = $displayname;
  if ( is_user_logged_in() ) { 
      $_SESSION['oauth_token']   = $client->access_token;
      $_SESSION['oauth_expiry']  = $client->access_token_expiry;
      $_SESSION['oauth_scope']   = $client->scope;
      //die("link");
      header("Location: ".site_url("/".get_option("link_page")));     
  } else {
    $service_id = $_SESSION['oauth_id'];
    $token = $client->access_token;
    $table_name = $wpdb->prefix . "bs_oauth_sessions";

    $query = $wpdb->prepare("SELECT `user_id` FROM $table_name WHERE `service_id` = %d AND `token` = %d",$service_id,$token);  
    $results = $wpdb->get_results($query,ARRAY_A);
    $result = $results[0];

    if ($result) {
      unset ($_SESSION['oauth_id']);
      wp_set_current_user ($result['user_id']);
      wp_set_auth_cookie($result['user_id']);
      //die("login");
      header("Location: ".site_url("/".get_option("login_page")));     
      
    } else {
      $_SESSION['oauth_signup']  = 1;
      $_SESSION['oauth_token']   = $client->access_token;
      $_SESSION['oauth_expiry']  = $client->access_token_expiry;
      $_SESSION['oauth_scope']   = $client->scope;
      //die("register");
      header("Location: ".site_url("/".get_option("register_page")));
    }
  }
}

function blaat_oauth_process($process){
   session_start();

  if ( $_REQUEST['oauth_id'] ||  $_REQUEST['code'] || $_REQUEST['oauth_token'] ) {
    if ($_REQUEST['oauth_id']) $_SESSION['oauth_id']=$_REQUEST['oauth_id'];

    global $wpdb;
    $table_name = $wpdb->prefix . "bs_oauth_services";
    $query = $wpdb->prepare("SELECT * FROM $table_name  WHERE id = %d", $_SESSION['oauth_id']);
    $results = $wpdb->get_results($query,ARRAY_A);
    $result = $results[0];
 
    $client = new oauth_client_class;
      $client->redirect_uri  = site_url("/".get_option("login_page"));
      $client->client_id     = $result['client_id'];
      $client->client_secret = $result['client_secret'];
      $client->scope         = $result['default_scope'];

    if ($result['custom_id']) {
      //echo "custom service";
      $table_name = $wpdb->prefix . "bs_oauth_custom";
      $query = $wpdb->prepare("SELECT * FROM $table_name  WHERE id = %d", $result['custom_id']);
      $customs = $wpdb->get_results($query,ARRAY_A);
      $custom = $customs[0];

      $client->oauth_version                 = $custom['oauth_version'];
      $client->request_token_url             = $custom['request_token_url'];
      $client->dialog_url                    = $custom['dialog_url'];
      $client->access_token_url              = $custom['access_token_url'];
      $client->url_parameters                = $custom['url_parameters'];
      $client->authorization_header          = $custom['authorization_header'];
      $client->offline_dialog_url            = $custom['offline_dialog_url'];
      $client->append_state_to_redirect_uri  = $custom['append_state_to_redirect_uri'];

      
    } else {
      $client->server        = $result['client_name'];
    }
  

    if(($success = $client->Initialize())){
      if(($success = $client->Process())){
        if(strlen($client->access_token)){
          call_user_func($process,$client,$result['display_name']);
          $success = $client->Finalize($success);
	      } else {
           echo( "<br>NO TOKEN</br> . $client->error");
        }
      } else {
         echo ("<br>processing error<br>". $client->error);
      }
    } else echo ("initialisation error");
  } else {
    return $user;
  }
 
}

function blaat_oauth_loginform () {
  echo "<div>";
  global $wpdb;
  global $bs_oauth_plugin;
  global $_SERVER;
  $ACTION=$_SERVER['REQUEST_URI'];// . '?' . $_SERVER['QUERY_STRING'];

  $table_name = $wpdb->prefix . "bs_oauth_services";

  $results = $wpdb->get_results("select * from $table_name where enabled=1 ",ARRAY_A);

  foreach ($results as $result){
    echo "<button name=oauth_id type=submit value='".$result['id']."'>". $result['display_name']."</button>";
  }
 
  echo "</div>";
}


function blaat_oauth_unlinkform(){
  global $wpdb;
  $table_name_sess = $wpdb->prefix . "bs_oauth_sessions";
  $table_name_serv = $wpdb->prefix . "bs_oauth_services";
  $user = wp_get_current_user();
  $user_id = $user->id;
  $query = "select ".$table_name_sess.".id , display_name from $table_name_sess " . 
                     "join $table_name_serv on ". 
                     $table_name_sess. ".service_id = ".
                     $table_name_serv. ".id ".
                     "where `user_id` =  $user_id ";
  $results = $wpdb->get_results($query, ARRAY_A);
  foreach ($results as $result){
    echo "<button name=oauth_unlink type=submit value='".$result['id']."'>". $result['display_name']."</button>";
  }
}

function blaat_oauth_linkform() {
  echo "</table>";
  echo "<h3>BlaatSchaap OAuth options</h3>";
  echo "<table class=form-table>";
  echo "<tr><th>Link your account with</td><td>";
  blaat_oauth_loginform();
  echo "</td></tr>";
  echo "<tr><th>Unlink your account from</td><td>";
  blaat_oauth_unlinkform();
  echo "</td></tr>";
}


add_filter("login_form",   blaat_oauth_loginform );
add_filter('authenticate', blaat_oauth_do_login,90  );


add_action('personal_options_update', blaat_oauth_link_update);
 
function blaat_oauth_link_update($user_id) {
  if ($_REQUEST['oauth_id']) {
    if ( current_user_can('edit_user',$user_id) ) {
      $user=wp_get_current_user();
      blaat_oauth_do_login($user);
    }
  }
  if ($_REQUEST['oauth_unlink']) {
    if ( current_user_can('edit_user',$user_id) ) {
      global $wpdb;
      $table_name =  $wpdb->prefix . "bs_oauth_sessions";
      $query = $wpdb->prepare("Delete from $table_name where id = %d" , $_REQUEST['oauth_unlink']);
      $wpdb->query($query);
    }
  }
}

add_action("admin_menu", blaat_oauth_menu);
add_action("personal_options", blaat_oauth_linkform);


function blaat_oauth_signup_message($message){
    global $wpdb;
    $table_name = $wpdb->prefix . "bs_oauth_services";
    $query = $wpdb->prepare("SELECT display_name FROM $table_name  WHERE id = %d", $_SESSION['oauth_id']);    
    $results = $wpdb->get_results($query, ARRAY_A);  
    $result = $results[0];
    $service = $result['display_name'];
    $signupmessage = "This $service account is not linked to any user. Please sign up by providing a username and email address";
    return '<p class="message register">' . $signupmessage . '</p>';
}

if ($_GET['oauth_signup']) {
  add_action('login_message', 'blaat_oauth_signup_message');
}

register_activation_hook(__FILE__, 'blaat_oauth_install');



add_filter( 'the_content', 'blaat_auth_display' );


function blaat_auth_login_display(){
  
  if (!is_user_logged_in()) blaat_oauth_do_login();

  if ( is_user_logged_in() ) {
    echo "Logged in";
  } else {
    echo "<div id='blaat_auth_local'>";
    echo "<p>" .  __("Log in with a local account","blaat_auth") . "</p>" ; 
    wp_login_form();
    echo "</div>";
    echo "<div id='blaat_auth_buttons'>";
    echo "<p>" . __("Log in with","blaat_auth") . "</p>";
    global $wpdb;
    global $bs_oauth_plugin;
    global $_SERVER;
    $ACTION=$_SERVER['REQUEST_URI'];// . '?' . $_SERVER['QUERY_STRING'];
  
    $table_name = $wpdb->prefix . "bs_oauth_services";

    $results = $wpdb->get_results("select * from $table_name where enabled=1 ",ARRAY_A);
    echo "<form action='$ACTION'>";
    foreach ($results as $result){
      $class = "btn-auth btn-".strtolower($result['client_name']);
      echo "<button class='$class' name=oauth_id type=submit value='".$result['id']."'>". $result['display_name']."</button>";
    }

    echo "</form>";
    echo "</div>";
  }
}

function blaat_auth_link_display(){
  session_start();
  global $wpdb;

  if (is_user_logged_in()) {
    $user = wp_get_current_user();
    if (isset($_SESSION['oauth_id'])     && isset($_SESSION['oauth_token']) &&
        isset($_SESSION['oauth_expiry']) && isset($_SESSION['oauth_scope']) ){

      $user_id    = $user->ID;
      $service_id = $_SESSION['oauth_id'];
      $token      = $_SESSION['oauth_token'];
      $expiry     = $_SESSION['oauth_expiry'];
      $scope      = $_SESSION['oauth_scope'];
      $service    = $_SESSION['oauth_display'];
      $table_name = $wpdb->prefix . "bs_oauth_sessions";
   
      $query = $wpdb->prepare("INSERT INTO $table_name (`user_id`, `service_id`, `token`, `expiry`, `scope` )
                                       VALUES      ( %d      ,  %d         ,  %s    , %s      , %s      )",
                                                    $user_id , $service_id , $token , $expiry , $scope  );
      $wpdb->query($query);
      unset($_SESSION['oauth_id']);
      unset($_SESSION['oauth_token']);
      unset($_SESSION['oauth_expiry']);
      unset($_SESSION['oauth_scope']);
      unset($_SESSION['oauth_display']);
      printf( __("Your %s account has been linked", "blaat_auth"), $service );
    } else {
      $table_name = $wpdb->prefix . "bs_oauth_sessions";
      $user_id    = $user->ID;
      $query = $wpdb->prepare("SELECT service_id FROM $table_name WHERE `user_id` = %d",$user_id);
      $linked_services = $wpdb->get_results($query,ARRAY_A);
       
      $table_name = $wpdb->prefix . "bs_oauth_services";
      $query = $wpdb->prepare("SELECT * FROM $table_name");
      $available_services = $wpdb->get_results($query,ARRAY_A);

      $linked = Array();
      foreach ($linked_services as $linked_service) {
        $linked[]=$linked_service['service_id'];
      }  
      foreach ($available_services as $available_service) {
        $class = "btn-auth btn-".strtolower($available_service['client_name']);
        $HTML = "<button class='$class' name='\$ACTION' type=submit value='".$available_service['id']."'>". $available_service['display_name']."</button>";

        if (in_array($available_service['id'],$linked)) {
          $unlinkHTML .= "<button class='$class' name='oauth_unlink' type=submit value='".$available_service['id']."'>". $available_service['display_name']."</button>";
        } else {
          $linkHTML .= "<button class='$class' name='oauth_link' type=submit value='".$available_service['id']."'>". $available_service['display_name']."</button>";

        }
      }
      echo "<div>Link:<br> $linkHTML</div><div>Unlink:<br> $unlinkHTML</div>";
    }      
  } else {
    // oauth user, no wp-user
    if (isset($_SESSION['oauth_id'])     && isset($_SESSION['oauth_token']) &&
        isset($_SESSION['oauth_expiry']) && isset($_SESSION['oauth_scope']) ){
        $service_id = $_SESSION['oauth_id'];
        $token      = $_SESSION['oauth_token'];
        $expiry     = $_SESSION['oauth_expiry'];
        $scope      = $_SESSION['oauth_scope'];
        $service    = $_SESSION['oauth_display'];
        echo "<div id='blaat_auth_local'>";
        printf(  "<p>" .  __("Please provide a local account to link to %s","blaat_auth") . "</p>" , $service);
        wp_login_form();
        echo "</div>";
      } else {
      printf(  "<p>" .  __("You need to be logged in to use this feature","blaat_auth") . "</p>");        
    } 
  }
}

function blaat_auth_register_display() {
  if (is_user_logged_in()) {
    _e("You cannot register a new account since you are already logged in.","blaat_auth");
  } else {

    session_start();
      if (isset($_SESSION['oauth_id'])     && isset($_SESSION['oauth_token']) &&
          isset($_SESSION['oauth_expiry']) && isset($_SESSION['oauth_scope']) ){

      $service = $_SESSION['oauth_display'];
      printf( __("You are authenticated to %s","blaat_auth") , $service );
      echo "<br>";
      _e("Please provide a username and e-mail address to complete your signup","blaat_auth");
      echo "<br>";
      printf( __("If you already have an account, please click <a href='%s'>here</a> to link it.","blaat_auth") , site_url("/".get_option("link_page")));
      echo "<br>";
    } else {
      _e("Please provice a username and password to sign up","blaat_auth");
    } 
  }
}

function blaat_auth_display($content) {
  $login_page    = get_option('login_page');
  $link_page     = get_option('link_page');
  $register_page = get_option('register_page');

  switch ($GLOBALS['post']->post_name) {
    case $login_page :
      blaat_auth_login_display();
      break;
    case $link_page :
      blaat_auth_link_display();
      break;
    case $register_page :
     blaat_auth_register_display();
      break;
    default : 
      return $content;
  }
}

wp_register_style('necolas-css3-social-signin-buttons', plugin_dir_url(__FILE__) . 'css/auth-buttons.css');
wp_enqueue_style( 'necolas-css3-social-signin-buttons');

wp_register_style("blaat_auth" , plugin_dir_url(__FILE__) . "blaat_auth.css");
wp_enqueue_style( "blaat_auth");

?>