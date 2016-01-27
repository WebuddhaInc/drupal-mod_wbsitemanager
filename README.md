# wbSiteManager System Plugin for Drupal

The Webuddha Site Manager is provides Command Line and Remote management of
the Core, Plugin, and Theme extensions in a Drupal installation.

The included connect.php request handler wraps calls for the CLI autoupdate 
script. Remote call security is handled by either IP filter, User Auth filter, 
or both. Remote calls are disabled by default until an IP or User filter is 
defined. User authentication values are passed to the script via HTTP Auth 
headers. Request parameters are single letter CLI operation flags or flag pairs.

The connect.php script is found in the plugin folder.

    //website.com/sites/all/modules/wbsitemanager/connect.php?xu&i=1

This workhorse for the plugin is the incluedd CLI script that is used to 
examine the extension of a local Joomla! installation, fetch available updates, 
download and install update packages.

    php /sites/all/modules/wbsitemanager/autoupdate.php --help

## CLI Usage

    Drupal CLI Autoupdate by Webuddha
    This script can be used to examine the extension of a local Drupal
    installation, fetch available updates, download and install update packages.

    Operations
      -f, --fetch                 Run Fetch
      -u, --update                Run Update
      -l, --list                  List Updates
      -p, --purge                 Purge Updates
      -P, --package-archive URL   [TODO] Install from Package Archive
      -B, --build-xml URL         [TODO] Install from Package Build XML

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
      -v, --verbose               [TODO] Verbose
