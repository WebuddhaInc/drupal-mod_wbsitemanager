<?php

// Plaintext Output
  header('Content-type: text/plain');

// Set parent system flag
  define('_JEXEC', 1);

// Define Base
  $base_path = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, dirname(getcwd())), 0, -2));
  if( !file_exists($base_path . '/sites/default/settings.php') && isset($_SERVER['SCRIPT_FILENAME']) ){
    $base_path = implode(DIRECTORY_SEPARATOR, array_slice(explode(DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']), 0, -5));
  }
  if( !file_exists($base_path . '/sites/default/settings.php') ){
    header('HTTP/1.0 500 Internal Server Error');
    die('HTTP/1.0 500 Internal Server Error');
  }

// Definitions
  define('_DEXEC', true);
  define('DS', DIRECTORY_SEPARATOR);
  define('DRUPAL_ROOT', $base_path);
  define('WBSITEMANAGER_ROOT', __DIR__);
  define('STDIN', fopen('php://input', 'r'));
  define('STDOUT', fopen('php://output', 'w'));

// Change to Root
  chdir( DRUPAL_ROOT );

// Drupal Initialization
  if( is_readable(DRUPAL_ROOT . '/includes/bootstrap.inc') ){
    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    drupal_override_server_variables();
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  }
  else {
    die('Drupal Failed to Initialize');
  }

// Import Configuration
  if( is_readable(__DIR__ . '/connect.config.php') ){
    include __DIR__ . '/connect.config.php';
  }
  else {
    /**
     * Load User Configured
     */
    /*
    $ipFilter = array_filter(explode("\n", $plugin_params->remote_ip_filter), 'strlen');
    $userFilter = array_filter(explode("\n", $plugin_params->remote_user_filter), 'strlen');
    */
  }

// Filter Required
  if( empty($ipFilter) && empty($userFilter) ){
    header('HTTP/1.0 403 Forbidden');
    die('HTTP/1.0 403 Forbidden');
  }

// Simple IP Filter
  if( !empty($ipFilter) ){
    require_once __DIR__ . '/classes/ipv4filter.class.php';
    $ipv4filter = new wbSiteManager_IPV4Filter($ipFilter);
    if( !$ipv4filter->check( $_SERVER['REMOTE_ADDR'] ) ){
      header('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
      die('HTTP/1.0 401 Unauthorized ' . $_SERVER['REMOTE_ADDR']);
    }
  }

// User Auth Filter
  if( !empty($userFilter) ){
    $headers = getallheaders();
    $authCredentials = null;
    if( !empty($headers['Authorization']) ){
      $headerAuth = explode(' ', $headers['Authorization'], 2);
      $authCredentials = array_combine(array('username', 'password'), explode(':', base64_decode(end($headerAuth)), 2));
    }
    else if( @$_SERVER['PHP_AUTH_USER'] && @$_SERVER['PHP_AUTH_PW'] ){
      $authCredentials = array('username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']);
    }
    if( $authCredentials ){
      if( is_array($userFilter) && !in_array($authCredentials['username'], $userFilter) ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
      $authResult = user_authenticate($authCredentials['username'], $authCredentials['password']);
      if( !$authResult ){
        header('HTTP/1.0 401 Unauthorized');
        die('HTTP/1.0 401 Unauthorized');
      }
    }
    else {
      header('HTTP/1.0 400 Bad Request');
      die('HTTP/1.0 400 Bad Request');
    }
  }

// Prepare CLI Requirements
  define('STDIN', fopen('php://input', 'r'));
  define('STDOUT', fopen('php://output', 'w'));
  $_SERVER['argv'] = array('autoupdate.php');
  $mQuery = array_merge($_GET, $_POST);
  foreach( $mQuery AS $k => $v ){
    $_SERVER['argv'][] = '-' . $k;
    if( strlen($v) )
      $_SERVER['argv'][] = $v;
  }

// Include / Execute CLI Class
  include DRUPAL_ROOT . '/sites/all/modules/wbsitemanager/autoupdate.php';
