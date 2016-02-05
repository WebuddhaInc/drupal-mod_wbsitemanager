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

      // Get update information from cache and refreshes it when necessary.
        $available = update_get_available();

      // Pull Projects
        module_load_include('inc', 'update', 'update.compare');
        $project_data = update_calculate_project_data($available);

      // Process Rows
        foreach ($project_data as $name => $project) {
          // Filter out projects which are up to date already.
          if ($project['status'] == UPDATE_CURRENT) {
            continue;
          }

          // The project name to display can vary based on the info we have.
          if (!empty($project['title']))
            $project_name = check_plain($project['title']);
          elseif (!empty($project['info']['name']))
            $project_name = check_plain($project['info']['name']);
          else
            $project_name = check_plain($name);

          if ($project['project_type'] == 'theme' || $project['project_type'] == 'theme-disabled') {
            $project_name .= ' ' . '(Theme)';
          }

          if (empty($project['recommended'])) {
            // If we don't know what to recommend they upgrade to, we should skip
            // the project entirely.
            continue;
          }

          $recommended_release = $project['releases'][$project['recommended']];

          switch ($project['status']) {
            case UPDATE_NOT_SECURE:
            case UPDATE_REVOKED:
              $project_name .= ' ' . '(Security update)';
              break;

            case UPDATE_NOT_SUPPORTED:
              $project_name .= ' ' . '(Unsupported)';
              break;

            case UPDATE_UNKNOWN:
            case UPDATE_NOT_FETCHED:
            case UPDATE_NOT_CHECKED:
            case UPDATE_NOT_CURRENT:
              break;

            default:
              // Jump out of the switch and onto the next project in foreach.
              continue 2;
          }

          // Push Return
            switch( $project['project_type'] ){
              case 'core':
                $updates[] = $this->caller->_newUpdateRow(array(
                  'update_id'         => $project['project_type'].':'.$project['name'].':'.$recommended_release['version'],
                  'extension_id'      => $project['name'],
                  'name'              => $project_name,
                  'description'       => $project['info']['package'],
                  'element'           => $project['info']['project'],
                  'type'              => $project['project_type'],
                  'version'           => $recommended_release['version'],
                  'detailsurl'        => $project['link'],
                  'infourl'           => $recommended_release['release_link'],
                  'downloadurl'       => $recommended_release['download_link'],
                  'installed_version' => $project['info']['version'],
                  'status'            => 'manual'
                  ));
                break;
              case 'module':
              case 'theme':
                $updates[] = $this->caller->_newUpdateRow(array(
                  'update_id'         => $project['project_type'].':'.$project['name'].':'.$recommended_release['version'],
                  'extension_id'      => $project['name'],
                  'name'              => $project_name,
                  'description'       => $project['info']['package'],
                  'element'           => $project['info']['project'],
                  'type'              => $project['project_type'],
                  'version'           => $recommended_release['version'],
                  'detailsurl'        => $project['link'],
                  'infourl'           => $recommended_release['release_link'],
                  'downloadurl'       => $recommended_release['download_link'],
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

      module_load_include('inc', 'update', 'update.manager');

      /*
       * TODO / Validate
      if (!$this->updateAccessAllowed()) {
        $this->caller->out(' - Error: Permission denied to process update.');
        return false;
      }
      */

      $this->caller->out('Processing Update ID: '. $update_row->update_id);

      // Type Switch
        switch( $update_row->type ){

          case 'core':
            $this->caller->out(' - Error: Core Updates Not Supported');
            break;

          case 'module':
          case 'theme':

            // STEP 1 : Download & Extract New Package.
              if ($this->doFileTransferUpdate($update_row)) {
                // STEP 2 : Update database.
                  /*
                  if ($this->doDatabaseUpdate($project)) {

                  }
                  */
              }

            break;

        }

      // Return
        return true;

    }

    public function doFileTransferUpdate($update_row) {

      $project = $update_row->extension_id;

      // Actually try to download the file.
      if (!($local_cache = update_manager_file_get($update_row->downloadurl))) {
        $this->caller->out(' - Error: Failed to download ' . $project . ' from ' . $update_row->downloadurl);
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

      $archive_errors = update_manager_archive_verify($project, $local_cache, $extract_directory);
      if (!empty($archive_errors)) {
        // We just need to make sure our array keys don't collide, so use the
        // numeric keys from the $archive_errors array.
        foreach ($archive_errors as $key => $error) {
          $this->caller->out(' - Error: ' . $key . ": " . $error);
        }
        return false;
      }

      // Store maintenance_mode setting so we can restore it when done.
      $maintenance_mode = variable_get('maintenance_mode', FALSE);
      if ($maintenance_mode == FALSE) {
        variable_set('maintenance_mode', TRUE);
      }

      // Make sure the Updater registry is loaded.
      drupal_get_updaters();

      $updates = array();
      $directory = _update_manager_extract_directory();

      $project_location = $directory . '/' . $project;
      $updater = Updater::factory($project_location);
      $project_real_location = drupal_realpath($project_location);
      $updater_name = get_class($updater);
      $local_url = $project_real_location;

      // If the owner of the last directory we extracted is the same as the
      // owner of our configuration directory (e.g. sites/default) where we're
      // trying to install the code, there's no need to prompt for FTP/SSH
      // credentials. Instead, we instantiate a FileTransferLocal and invoke
      // update_authorize_run_update() directly.
      //
      // THIS PLUGIN WILL ONLY WORK IF IT IS THE SAME USER / LOCAL.
      if (fileowner($project_real_location) == fileowner(conf_path())) {
        module_load_include('inc', 'update', 'update.authorize');
        $filetransfer = new FileTransferLocal(DRUPAL_ROOT);

        // Modified version of update_authorize_run_update() without the batch process.
        unset($filetransfer->connection);

        $updater = new $updater_name($local_url);

        try {
          if ($updater->isInstalled()) {
            // This is an update.
            $tasks = $updater->update($filetransfer);
          }
        }
        catch (UpdaterException $e) {
          $this->caller->out(' - Error: ' . $e->getMessage());
          return false;
        }

        $this->caller->out(' - ' . ucfirst($project) . ' installed successfully');

        $offline = variable_get('maintenance_mode', FALSE);

        // Now that the update completed, we need to clear the cache of available
        // update data and recompute our status, so prevent show bogus results.
        _update_authorize_clear_update_status();

        // Take the site out of maintenance mode if it was previously that way.
        if ($offline && $maintenance_mode == FALSE) {
          variable_set('maintenance_mode', FALSE);
          $this->caller->out(' - Your site has been taken out of maintenance mode.');
        }

        // File Update Completed, return True to process Database Update.
        return true;
      }
      else {
        $this->caller->out(" - Error: User doesn't have access to file transfers. FTP credentials are required.");
        return false;
      }
    }

    /**
     * Copy of update_access_allowed() in update.php.
     * @return bool
     */
    private function updateAccessAllowed() {
      global $update_free_access, $user;

      // Allow the global variable in settings.php to override the access check.
      if (!empty($update_free_access)) {
        return TRUE;
      }
      // Calls to user_access() might fail during the Drupal 6 to 7 update process,
      // so we fall back on requiring that the user be logged in as user #1.
      try {
        require_once DRUPAL_ROOT . '/' . drupal_get_path('module', 'user') . '/user.module';
        return user_access('administer software updates');
      }
      catch (Exception $e) {
        return ($user->uid == 1);
      }
    }

  }