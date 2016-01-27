<?php

defined('_DEXEC') or die();

/**
 *
 *  Drupal7 Provider
 *
 */

  class wbSiteManager_Provider_Drupal7 {

    /**
     * [$caller description]
     * @var null
     */
    private $caller = null;

    /**
     * [__construct description]
     * @param [type] $caller [description]
     */
    public function __construct( $caller ){

      // Set Called
        $this->caller = $caller;

      // Require
        require_once DRUPAL_ROOT . '/modules/update/update.authorize.inc';
        require_once DRUPAL_ROOT . '/modules/update/update.compare.inc';
        require_once DRUPAL_ROOT . '/modules/update/update.fetch.inc';
        require_once DRUPAL_ROOT . '/modules/update/update.manager.inc';

    }

    /**
     * [doPurgeUpdatesCache description]
     * @return [type] [description]
     */
    public function doPurgeUpdatesCache(){

      // Delete Cache
        db_delete('cache_update')
          ->condition(
            db_or()
            ->condition('cid', 'fetch_task::%', 'LIKE')
            ->condition('cid', 'available_releases::%', 'LIKE')
            )
          ->execute();

    }

    /**
     * [doFetchUpdates description]
     * @return [type] [description]
     */
    public function doFetchUpdates(){

      // Pull Projects
        $projects = update_get_projects();

      // Fetch Updates
        foreach( $projects AS $project ){
          $res = _update_process_fetch_task( $project );
        }

    }

    /**
     * [getUpdateRows description]
     * @return [type] [description]
     */
    public function getUpdateRows(){

      // Return
        $updates = array();

      // Pull Projects
        $projects = update_get_projects();

      // Pull Available Releases
        $packages =
          db_select('cache_update', 'cu')
          ->fields('cu')
          ->condition('cid', 'available_releases::%', 'LIKE')
          ->execute()
          ->fetchAllAssoc('cid');

      // Process Rows
        foreach( $packages AS $package ){

          // Unserialize Store
            $package->data  = unserialize( $package->data );
            $project        = isset($projects[$package->data['short_name']]) ? $projects[$package->data['short_name']] : null;
            $package_update = reset($package->data['releases']);

          // Push Return
            switch( $project['project_type'] ){
              case 'core':
                /*
                $updates[] = $this->caller->_newUpdateRow(array(
                  'update_id'         => $project['project_type'].':'.$package_update['version'],
                  'extension_id'      => 'drupal',
                  'name'              => 'Drupal',
                  'element'           => 'drupal',
                  'type'              => $project['project_type'],
                  'status'            => 'manual',
                  'version'           => $project['info']['version'],
                  'detailsurl'        => $package_update['download_link'],
                  'infourl'           => $package_update['release_link'],
                  'installed_version' => $project['info']['version']
                  ));
                break;
                */
              case 'module':
              case 'theme':
                $updates[] = $this->caller->_newUpdateRow(array(
                  'update_id'         => $project['project_type'].':'.$project['name'].':'.$package_update['version'],
                  'extension_id'      => $project['name'],
                  'name'              => $project['info']['name'],
                  'description'       => $project['info']['package'],
                  'element'           => $project['info']['project'],
                  'type'              => $project['project_type'],
                  'status'            => 'available',
                  'version'           => $package_update['version'],
                  'detailsurl'        => $package_update['download_link'],
                  'infourl'           => $package_update['release_link'],
                  'installed_version' => $project['info']['version']
                  ));
                break;
              default:
                break;
            }

        }

      // Return
        return $updates;

    }

    public function doInstallUpdate( $update_row ){

      // Pull Projects
        $projects = update_get_projects();

      // Pull Available Releases
        $packages =
          db_select('cache_update', 'cu')
          ->fields('cu')
          ->condition('cid', 'available_releases::%', 'LIKE')
          ->execute()
          ->fetchAllAssoc('cid');

      // Find & Process Package
        foreach( $packages AS $package ){

          // Unserialize Store
            $package->data  = unserialize( $package->data );
            $project        = isset($projects[$package->data['short_name']]) ? $projects[$package->data['short_name']] : null;
            $package_update = reset($package->data['releases']);
            $package_uid    = $project['project_type'].':'.$project['name'].':'.$package_update['version'];

          // Match Package
            if( $update_row->update_id == $package_uid ){

              // Type Switch
                switch( $update_row->type ){

                  case 'core':
                    $this->caller->out(' - Error: Core Updates Not Supported');
                    break;

                  case 'module':
                  case 'theme':

                    // Download Package
                      if( !($local_cache = update_manager_file_get($package_update['download_link'])) ){
                        $this->caller->out(' - Error: Failed to download '. $project['name'] .' from '. $package_update['download_link']);
                        return false;
                      }

                    // Extract it.
                      $extract_directory = _update_manager_extract_directory();
                      try {
                        update_manager_archive_extract($local_cache, $extract_directory);
                      }
                      catch (Exception $e) {
                        $this->caller->out(' - Error: ' . $e->getMessage());
                        return false;
                      }

                    // Verify it.
                      $archive_errors = update_manager_archive_verify($project['name'], $local_cache, $extract_directory);
                      if (!empty($archive_errors)) {
                        foreach ($archive_errors as $key => $error) {
                          $this->caller->out(' - Error: ' . $error);
                        }
                        return false;
                      }

                    // Load Updater
                      $project_folder = $extract_directory . '/' . $project['name'];
                      try {
                        $updater = Updater::factory($project_folder);
                      }
                      catch (Exception $e) {
                        $this->caller->out(' - Error: ' . $e->getMessage());
                        return false;
                      }
                      $context = array(
                        'results' => array()
                        );

                    // Run Updater
                      update_authorize_batch_copy_project( $project['name'], get_class($updater), drupal_realpath($project_folder), new FileTransferLocal(DRUPAL_ROOT), $context );

                    // Verify
                      if( empty($context['finished']) ){
                        $message = isset($context['results']['log'][ $project['name'] ]) ? reset($context['results']['log'][ $project['name'] ])['message'] : 'Unknown Installer Error';
                        $this->caller->out(' - Error: ' . $message);
                        return false;
                      }
                      else {
                        $this->caller->out(' - ' . ucfirst($update_row->type) . ' installed successfully');
                      }

                    break;

                }

            }

        }

      // Return
        return true;

    }

  }