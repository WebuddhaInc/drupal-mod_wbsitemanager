<?php

/**
 *
 * This is a CLI Script only
 *   /usr/bin/php /path/to/site/cli/autoupdate.php
 *
 * For Help
 *   php autoupdate.php -h
 *
 */

// Set Version
  const _DrupalCliAutoUpdateVersion = '0.1.0';

// CLI or Valid Include
  if( php_sapi_name() != 'cli' && !defined('_DEXEC') )
    die('Invalid Access');

// Definitions
  defined('_DEXEC') || define('_DEXEC', true);
  defined('DS') || define('DS', DIRECTORY_SEPARATOR);
  defined('DRUPAL_ROOT') || define('DRUPAL_ROOT', realpath(implode(DS, array_slice(explode(DS, $_SERVER['PWD'] . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_FILENAME']), 0, -5))));
  defined('WBSITEMANAGER_ROOT') || define('WBSITEMANAGER_ROOT', __DIR__);
  defined('STDIN') || define('STDIN', fopen('php://input', 'r'));
  defined('STDOUT') || define('STDOUT', fopen('php://output', 'w'));

// Change to Root
  chdir( DRUPAL_ROOT );

// Drupal Initialization
  if( !defined('VERSION') ){
    if( is_readable(DRUPAL_ROOT . '/includes/bootstrap.inc') ){
      require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
      drupal_override_server_variables();
      drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    }
    else {
      die('Drupal Failed to Initialize');
    }
  }

// Module Assets
  require_once WBSITEMANAGER_ROOT . '/classes/params.class.php';

/**
 * This script will download and install all available updates.
 */

  class DrupalCliAutoUpdate {

    /**
     * [$__outputBuffer description]
     * @var null
     */
    public $__outputBuffer = null;
    public $db             = null;
    public $updater        = null;
    public $installer      = null;
    public $config         = null;

    /**
     * [__construct description]
     */
    public function __construct(){

      // Input
        $this->input = new wbSiteManager_Params( array_slice($_SERVER['argv'], 1), 'cli' );

      // Provider
        if( DRUPAL_CORE_COMPATIBILITY == '7.x' ){
          require_once WBSITEMANAGER_ROOT . '/classes/provider/drupal7.class.php';
          $this->provider = new wbSiteManager_Provider_Drupal7( $this );
        }
        else {
          echo DRUPAL_CORE_COMPATIBILITY;
          die(__LINE__.': '.__FILE__);
        }

      // Execute
        if( $this->input->get('x', $this->input->get('export')) ){
          $this->startOutputBuffer();
        }
        if( $this->input->get('p', $this->input->get('purge')) ){
          $this->doPurgeUpdatesCache();
        }
        if( $this->input->get('f', $this->input->get('fetch')) ){
          $this->doFetchUpdates();
        }
        if(
          $this->input->get('l', $this->input->get('list'))
          ||
          $this->input->get('u', $this->input->get('update'))
          ){
          $this->doIterateUpdates();
        }
        if( $this->input->get('h', $this->input->get('help')) ){
          $this->doEchoHelp();
        }
        if( $this->input->get('x', $this->input->get('export')) ){
          $this->dumpOutputBuffer();
        }

    }

    /**
     * [doPurgeUpdatesCache description]
     * @return [type] [description]
     */
    public function doPurgeUpdatesCache(){

      // Call Provider
        $this->provider->doPurgeUpdatesCache();

    }

    /**
     * [doFetchUpdates description]
     * @return [type] [description]
     */
    public function doFetchUpdates(){

      // Call Provider
        $this->provider->doFetchUpdates();

    }

    /**
     * [getUpdateRows description]
     * @param  [type] $lookup [description]
     * @param  [type] $start  [description]
     * @param  [type] $limit  [description]
     * @return [type]         [description]
     */
    public function getUpdateRows( $lookup = null, $start = null, $limit = null ){

      // Call Provider
        $updates = $this->provider->getUpdateRows();

      // Filter Lookup
        $start = $start ? (int)$start : 0;
        $count = $limit ? $start + $limit : count($updates);
        $count = $count > count($updates) ? count($updates) : $count;
        $final_updates = array();
        for( $i = $start; $i < $count; $i++ ){
          $passed = true;
          if( $lookup && is_array($lookup) ){
            foreach($lookup AS $key => $val){
              switch( $key ){
                case 'update_id':
                  $update_rule = explode(':', $val);
                  $match_rule  = explode(':', $updates[$i]->{$key});
                  for( $r=0; $r < count($match_rule); $r++ ){
                    if( !isset($update_rule[$r]) || ($update_rule[$r] != '*' && $update_rule[$r] != $match_rule[$r]) ){
                      $passed = false;
                      break 2;
                    }
                  }
                  break;
                default:
                  if( $updates[$i]->{$key} != $val ){
                    $passed = false;
                    break;
                  }
                  break;
              }
            }
            if( !$passed ){
              continue;
            }
          }
          $final_updates[] = $updates[$i];
        }

      // Return
        return $final_updates;

    }

    /**
     * [doInstallUpdate description]
     * @param  [type] $update_id   [description]
     * @param  [type] $build_url   [description]
     * @param  [type] $package_url [description]
     * @return [type]              [description]
     */
    public function doInstallUpdate( $update_row ){
      global $wp_filesystem;

      // Switch Type
        $this->out('Processing Update ID: '. $update_row->update_id);
        $this->provider->doInstallUpdate( $update_row );

      // Complete
        $this->out(' - Update Complete');
        return true;

    }

    /**
     * [doIterateUpdates description]
     * @return [type] [description]
     */
    public function doIterateUpdates(){

      // Build Update Filter
        $update_lookup = array();

      // All Items
        if( $this->input->get('a', $this->input->get('all')) ){
        }

      // Core Items
        if( $this->input->get('c', $this->input->get('core')) ){
          $lookup = array(
            'type'    => 'file',
            'element' => 'wordpress'
            );
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Extension Lookup
        if( $extension_lookup = $this->input->get('e', $this->input->get('extension')) ){
          if( is_numeric($extension_lookup) ){
            $lookup = array(
              'extension_id' => (int)$extension_lookup
              );
          }
          else {
            $lookup = array(
              'element' => (string)$extension_lookup
              );
          }
          if( $type = $this->input->get('t', $this->input->get('type')) ){
            $lookup['type'] = $type;
          }
          if( $version = $this->input->get('v', $this->input->get('version')) ){
            $lookup['version'] = $version;
          }
          $update_lookup[] = $lookup;
        }

      // Update ID
        if( $update_id = $this->input->get('i', $this->input->get('id')) ){
          $update_lookup[] = array(
            'update_id' => $update_id
            );
        }

      // List / Export / Process Updates
        $update_rows = $this->getUpdateRows( array_shift($update_lookup) );
        if( $update_rows ){
          $do_list     = $this->input->get('l', $this->input->get('list'));
          $do_export   = $this->input->get('x', $this->input->get('export'));
          $do_update   = $this->input->get('u', $this->input->get('update'));
          $export_data = null;
          if( $do_export ){
            $export_data = array(
              'updates' => array()
              );
          }
          else if( $do_list ){
            $this->out(implode('',array(
              $this->cli_str('element', 14),
              $this->cli_str('type', 8),
              $this->cli_str('version', 10),
              $this->cli_str('installed', 10),
              $this->cli_str('eid', 16),
              $this->cli_str('uid', 22)
              )));
          }
          $run_update_rows = array();
          do {
            foreach( $update_rows AS $update_row ){
              if( $do_export ){
                $export_data['updates'][] = $update_row;
              }
              else if( $do_list ){
                $this->out(implode('',array(
                  $this->cli_str($update_row->element, 14),
                  $this->cli_str($update_row->type, 8),
                  $this->cli_str($update_row->version, 10),
                  $this->cli_str($update_row->installed_version, 10),
                  $this->cli_str($update_row->extension_id, 16),
                  $this->cli_str($update_row->update_id, 22, false)
                  )));
              }
            }
            if( $do_update ){
              $run_update_rows += $update_rows;
            }
          } while(
            count($update_lookup)
            && $update_rows = $this->getUpdateRows( array_shift($update_lookup) )
            );
          if( count($run_update_rows) ){
            foreach( $run_update_rows AS $update_row ){
              if( !$this->doInstallUpdate( $update_row ) ){
                return false;
              }
            }
            $this->out('Update processing complete');
          }
          if( isset($export_data) ){
            $this->out( $export_data );
          }
        }
        else {
          $this->out('No updates found');
        }

    }

    /**
     * [_newUpdateRow description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function _newUpdateRow( $data ){
      return new wbSiteManager_Params(array_merge( array(
        'update_id'         => null,
        'update_site_id'    => null,
        'extension_id'      => null,
        'name'              => null,
        'description'       => null,
        'element'           => null,
        'type'              => null,
        'locale'            => null,
        'folder'            => null,
        'client_id'         => null,
        'version'           => null,
        'data'              => null,
        'detailsurl'        => null,
        'infourl'           => null,
        'extra_query'       => null,
        'installed_version' => null
        ), $data ));
    }

    /**
     * [startOutputBuffer description]
     * @return [type] [description]
     */
    public function startOutputBuffer(){
      $this->__outputBuffer = array(
        'log'    => array(),
        'data'   => array()
        );
    }

    /**
     * [dumpOutputBuffer description]
     * @return [type] [description]
     */
    public function dumpOutputBuffer(){
      fwrite(STDOUT, json_encode($this->__outputBuffer) );
    }

    /**
     * [out description]
     * @param  string  $text [description]
     * @param  boolean $nl   [description]
     * @return [type]        [description]
     */
    public function out( $text = '', $nl = true ){
      if( isset($this->__outputBuffer) ){
        if( is_string($text) ){
          $this->__outputBuffer['log'][] = $text;
        }
        else {
          $this->__outputBuffer['data'] = array_merge( $this->__outputBuffer['data'], $text );
        }
        return $this;
      }
      fwrite(STDOUT, $text . ($nl ? "\n" : ''));
    }

    /**
     * [cli_str description]
     * @param  [type] $str [description]
     * @param  [type] $len [description]
     * @return [type]      [description]
     */
    function cli_str( $str, $len, $crop = true ){
      return str_pad(($crop ? substr($str, 0, $len - 1) : $str), $len, ' ', STR_PAD_RIGHT);
    }

    /**
     * [doEchoHelp description]
     * @return [type] [description]
     */
    public function doEchoHelp(){
      $version = __DrupalCliAutoUpdateVersion;
      echo <<<EOHELP
Wordpress CLI Autoupdate by Webuddha v{$version}
This script can be used to examine the extension of a local Joomla!
installation, fetch available updates, download and install update packages.

Operations
  -f, --fetch                 Run Fetch
  -u, --update                Run Update
  -l, --list                  List Updates
  -p, --purge                 Purge Updates
  -P, --package-archive URL   Install from Package Archive
  -B, --build-xml URL         Install from Package Build XML

Update Filters
  -i, --id ID                 Update ID
  -a, --all                   All Packages
  -V, --version VER           Version Filter
  -c, --core                  Joomla! Core Packages
  -e, --extension LOOKUP      Extension by ID/NAME
  -t, --type VAL              Type

Additional Flags
  -x, --export                Output in JSON format
  -h, --help                  Help
  -v, --verbose               Verbose

EOHELP;
    }

  }

/**
 * Inspector
 */
  if( !function_exists('inspect') ){
    function inspect(){
      print_r( func_get_args() );
    }
  }

/**
 * Trigger Execution
 */

  new DrupalCliAutoUpdate();