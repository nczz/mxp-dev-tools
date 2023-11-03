<?php
if (!defined('WPINC')) {
    die;
}
$wp_constants = array(
    'general'                 => array(
        'title'     => 'General',
        'constants' => array(
            array(
                'name'        => 'AUTOSAVE_INTERVAL',
                'description' => 'Defines an interval, in which WordPress should do an autosave.',
                'value'       => 'Time in seconds (Default: 60)',
            ),
            array(
                'name'        => 'CORE_UPGRADE_SKIP_NEW_BUNDLED',
                'description' => 'Allows you to skip new bundles files like plugins and/or themes on upgrades.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'DISABLE_WP_CRON',
                'description' => 'Deactivates the cron function of WordPress.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'EMPTY_TRASH_DAYS',
                'description' => 'Controls the number of days before WordPress permanently deletes posts, pages, attachments, and comments, from the trash bin.',
                'value'       => 'time in days (Default: 30)',
            ),
            array(
                'name'        => 'IMAGE_EDIT_OVERWRITE',
                'description' => 'Allows WordPress to override an image after editing or to save the image as a copy.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'MEDIA_TRASH',
                'description' => '(De)activates the trash bin function for media.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WPLANG',
                'description' => 'Defines the language which WordPress should use.',
                'value'       => 'e.g. en_US | de_DE',
            ),
            array(
                'name'        => 'WP_DEFAULT_THEME',
                'description' => 'Defines a default theme for new sites, also used as fallback for a broken theme.',
                'value'       => 'template name (Default: twentyeleven)',
            ),
            array(
                'name'        => 'WP_CRON_LOCK_TIMEOUT',
                'description' => 'Defines a period of time in which only one cronjob will be fired. Since WordPress 3.3.',
                'value'       => 'time in seconds (Default: 60)',
            ),
            array(
                'name'        => 'WP_MAIL_INTERVAL',
                'description' => 'Defines a period of time in which only one mail request can be done.',
                'value'       => 'time in seconds (Default: 300)',
            ),
            array(
                'name'        => 'WP_POST_REVISIONS',
                'description' => '(De)activates the revision function for posts. A number greater than 0 defines the number of revisions for one post.',
                'value'       => 'true|false|number (Default: true)',
            ),
            array(
                'name'        => 'WP_MAX_MEMORY_LIMIT',
                'description' => 'Allows you to change the maximum memory limit for some WordPress functions.',
                'value'       => '(Default: 256M)',
            ),
            array(
                'name'        => 'WP_MEMORY_LIMIT',
                'description' => 'Defines the memory limit for WordPress.',
                'value'       => '(Default: 32M, for Multisite 64M)',
            ),
            array(
                'name'        => 'WP_AUTO_UPDATE_CORE',
                'description' => 'Manages core auto-updates.',
                'value'       => 'true | false | minor',
            ),
            array(
                'name'        => 'AUTOMATIC_UPDATER_DISABLED',
                'description' => 'Disables the auto-update engine introduced in version 3.7.',
                'value'       => 'true | valse',
            ),
            array(
                'name'        => 'REST_API_VERSION',
                'description' => 'Version of REST API in WordPress core.',
                'value'       => '',
            ),
        ),
    ),

    'status'                  => array(
        'title'     => 'Status',
        'constants' => array(
            array(
                'name'        => 'APP_REQUEST',
                'description' => 'Will be defined if it’s an Atom Publishing Protocol request.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'COMMENTS_TEMPLATE',
                'description' => 'Will be defined if the comments template is loaded.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'DOING_AJAX',
                'description' => 'Will be defined if it’s an AJAX request.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'DOING_AUTOSAVE',
                'description' => 'Will be defined if WordPress is doing an autosave for posts.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'DOING_CRON',
                'description' => 'Will be defined if WordPress is doing a cronjob.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'IFRAME_REQUEST',
                'description' => 'Will be defined if it’s an inlineframe request.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'IS_PROFILE_PAGE',
                'description' => 'Will be defined if a user change his profile settings.',
                'value'       => 'tre',
            ),
            array(
                'name'        => 'SHORTINIT',
                'description' => 'Can be defined to load only the half of WordPress.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_ADMIN',
                'description' => 'Will be defined if it’s a request in backend of WordPress.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_BLOG_ADMIN',
                'description' => 'Will be defined if it’s a request in /wp-admin/.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_IMPORTING',
                'description' => 'Will be defined if WordPress is importing data.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_INSTALLING',
                'description' => 'Will be defined on an new installation or on an upgrade.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_INSTALLING_NETWORK',
                'description' => 'Will be defined if it’s a request in network admin or on installing a network. Since WordPress 3.3, previous WP_NETWORK_ADMIN_PAGE.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_LOAD_IMPORTERS',
                'description' => 'Will be defined if you visit the importer overview (Tools → Importer).',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_NETWORK_ADMIN',
                'description' => 'Will be defined if it’s a request in /wp-admin/network/.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_REPAIRING',
                'description' => 'Will be defined if it’s a request to /wp-admin/maint/repair.php.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_SETUP_CONFIG',
                'description' => 'Will be defined if WordPress will be installed or configured.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_UNINSTALL_PLUGIN',
                'description' => 'Will be defined if a plugin wil be uninstalled (for uninstall.php).',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_USER_ADMIN',
                'description' => 'Will be defined if it’s a request in /wp-admin/user/.',
                'value'       => 'values',
            ),
            array(
                'name'        => 'XMLRPC_REQUEST',
                'description' => 'Will be defined if it’s a request over the XML-RPC API.',
                'value'       => 'true',
            ),
        ),
    ),

    'database'                => array(
        'title'     => 'Database',
        'constants' => array(
            array(
                'name'        => 'DB_CHARSET',
                'description' => 'Defines the database charset.',
                'value'       => 'See MySQL docs (Default: utf8)',
            ),
            array(
                'name'        => 'DB_COLLATE',
                'description' => 'Defines the database collation.',
                'value'       => 'See MySQL docs (Default: utf8_general_ci)',
            ),
            array(
                'name'        => 'DB_HOST',
                'description' => 'Defines the database host.',
                'value'       => 'IP address, domain and/or port (Default: localhost)',
            ),
            array(
                'name'        => 'DB_NAME',
                'description' => 'Defines the database name.',
                'value'       => '',
            ),
            array(
                'name'        => 'DB_USER',
                'description' => 'Defines the database user.',
                'value'       => '',
            ),
            array(
                'name'        => 'DB_PASSWORD',
                'description' => 'Defines the database password.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_ALLOW_REPAIR',
                'description' => 'Allows you to automatically repair and optimize the database tables via /wp-admin/maint/repair.php.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'CUSTOM_USER_TABLE',
                'description' => 'Allows you to define a custom user table.',
                'value'       => 'table name',
            ),
            array(
                'name'        => 'CUSTOM_USER_META_TABLE',
                'description' => 'Allows you to define a custom user meta table.',
                'value'       => 'table name',
            ),
        ),
    ),

    'paths_dirs_links'        => array(
        'title'     => 'Paths, Directories & Links',
        'constants' => array(
            array(
                'name'        => 'ABSPATH',
                'description' => 'Absolute path to the WordPress root dir.',
                'value'       => 'path to wp-load.php',
            ),
            array(
                'name'        => 'WPINC',
                'description' => 'Relative path to the /wp-includes/. You can’t change it.',
                'value'       => 'wp-includes',
            ),
            array(
                'name'        => 'WP_LANG_DIR',
                'description' => 'Absolute path to the folder with language files.',
                'value'       => 'WP_CONTENT_DIR /languages or WP_CONTENT_DIR WPINC /languages',
            ),
            array(
                'name'        => 'WP_PLUGIN_DIR',
                'description' => 'Absolute path to the plugins dir.',
                'value'       => 'WP_CONTENT_DIR /plugins',
            ),
            array(
                'name'        => 'WP_PLUGIN_URL',
                'description' => 'URL to the plugins dir.',
                'value'       => 'WP_CONTENT_URL /plugins',
            ),
            array(
                'name'        => 'WP_CONTENT_DIR',
                'description' => 'Absolute path to thewp-content dir.',
                'value'       => 'ABSPATH /wp-content',
            ),
            array(
                'name'        => 'WP_CONTENT_URL',
                'description' => 'URL to the wp-content dir.',
                'value'       => '{Site URL}/wp-content',
            ),
            array(
                'name'        => 'WP_HOME',
                'description' => 'Home URL of your WordPress.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_SITEURL',
                'description' => 'URL to the WordPress root dir.',
                'value'       => 'values',
            ),
            array(
                'name'        => 'WP_TEMP_DIR',
                'description' => 'Absolute path to a dir, where temporary files can be saved.',
                'value'       => '',
            ),
            array(
                'name'        => 'WPMU_PLUGIN_DIR',
                'description' => 'Absolute path to the must use plugin dir.',
                'value'       => 'WP_CONTENT_DIR /mu-plugins',
            ),
            array(
                'name'        => 'WPMU_PLUGIN_URL',
                'description' => 'URL to the must use plugin dir.',
                'value'       => 'WP_CONTENT_URL /mu-plugins',
            ),
            array(
                'name'        => 'PLUGINDIR',
                'description' => 'Allows for the plugins directory to be moved from the default location.',
                'value'       => 'wp-content/plugins ',
            ),
            array(
                'name'        => 'MUPLUGINDIR',
                'description' => 'Allows for the mu-plugins directory to be moved from the default location.',
                'value'       => 'wp-content/mu-plugins',
            ),
            array(
                'name'        => 'DIRECTORY_SEPARATOR',
                'description' => 'A predefined constant that contains either a forward slash or backslash depending on the OS your web server is on',
                'value'       => '/ or \\',
            ),
        ),
    ),

    'file_system_connections' => array(
        'title'     => 'File System & Connections',
        'constants' => array(
            array(
                'name'        => 'FS_CHMOD_DIR',
                'description' => 'Defines the read and write permissions for directories.',
                'value'       => 'See PHP handbook (Default: 0755)',
            ),
            array(
                'name'        => 'FS_CHMOD_FILE',
                'description' => 'Defines the read and write permissions for files.',
                'value'       => 'ee PHP handbook (Default: 0644)',
            ),
            array(
                'name'        => 'FS_CONNECT_TIMEOUT',
                'description' => 'Defines a timeout for building a connection.',
                'value'       => 'time in seconds (Default: 30)',
            ),
            array(
                'name'        => 'FS_METHOD',
                'description' => 'Defines the method to connect to the filesystem.',
                'value'       => 'direct|ssh|ftpext|ftpsockets',
            ),
            array(
                'name'        => 'FS_TIMEOUT',
                'description' => 'Defines a timeout after a connection has been lost.',
                'value'       => 'time in seconds (Default: 30)',
            ),
            array(
                'name'        => 'FTP_BASE',
                'description' => 'Path to the WordPress root dir.',
                'value'       => 'ABSPATH',
            ),
            array(
                'name'        => 'FTP_CONTENT_DIR',
                'description' => 'Path to the /wp-content/ dir.',
                'value'       => 'WP_CONTENT_DIR',
            ),
            array(
                'name'        => 'FTP_HOST',
                'description' => 'Defines the FTP host.',
                'value'       => 'IP Adresse, Domain und/oder Port',
            ),
            array(
                'name'        => 'FTP_LANG_DIR',
                'description' => 'Path to the folder with language files.',
                'value'       => 'WP_LANG_DIR',
            ),
            array(
                'name'        => 'FTP_PASS',
                'description' => 'Defines the FTP password.',
                'value'       => '',
            ),
            array(
                'name'        => 'FTP_PLUGIN_DIR',
                'description' => 'Path to the plugin dir.',
                'value'       => 'WP_PLUGIN_DIR',
            ),
            array(
                'name'        => 'FTP_PRIKEY',
                'description' => 'Defines a private key for SSH.',
                'value'       => '',
            ),
            array(
                'name'        => 'FTP_PUBKEY',
                'description' => 'Defines a public key for SSH.',
                'value'       => '',
            ),
            array(
                'name'        => 'FTP_SSH',
                'description' => '(De)activates SSH.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'FTP_SSL',
                'description' => '(De)activates SSL.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'FTP_USER',
                'description' => 'Defines the FTP username.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_PROXY_BYPASS_HOSTS',
                'description' => 'Allows you to define some adresses which shouldn’t be passed through a proxy.',
                'value'       => 'www.example.com, *.example.org',
            ),
            array(
                'name'        => 'WP_PROXY_HOST',
                'description' => 'Defines the proxy address.',
                'value'       => 'IP address or domain',
            ),
            array(
                'name'        => 'WP_PROXY_PASSWORD',
                'description' => 'Defines the proxy password.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_PROXY_PORT',
                'description' => 'Defines the proxy port.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_PROXY_USERNAME',
                'description' => 'Defines the proxy username.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_HTTP_BLOCK_EXTERNAL',
                'description' => 'Allows you to block external request.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'WP_ACCESSIBLE_HOSTS',
                'description' => 'If WP_HTTP_BLOCK_EXTERNAL is defined you can add hosts which shouldn’t be blocked.',
                'value'       => 'www.example.com, *.example.org',
            ),
        ),
    ),

    'multisite'               => array(
        'title'     => 'WordPress Multisite',
        'constants' => array(
            array(
                'name'        => 'ALLOW_SUBDIRECTORY_INSTALL',
                'description' => 'Allows you to install Multisite in a subdirectory.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'BLOGUPLOADDIR',
                'description' => 'Absolute path to the site specific upload dir.',
                'value'       => 'WP_CONTENT_DIR /blogs.dir/{Blog ID}/files/',
            ),
            array(
                'name'        => 'BLOG_ID_CURRENT_SITE',
                'description' => 'Blog ID of the main site.',
                'value'       => '1',
            ),
            array(
                'name'        => 'DOMAIN_CURRENT_SITE',
                'description' => 'Domain of the main site.',
                'value'       => 'domain',
            ),
            array(
                'name'        => 'DIEONDBERROR',
                'description' => 'When defined database errors will be displayed on screen.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'ERRORLOGFILE',
                'description' => 'When defined database erros will be logged into a file.',
                'value'       => 'absolute path to a writeable file',
            ),
            array(
                'name'        => 'MULTISITE',
                'description' => 'Will be defined if Multisite is used.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'NOBLOGREDIRECT',
                'description' => 'Defines an URL of a site on which WordPress should redirect, if registration is closed or a site doesn’t exists.',
                'value'       => '%siteurl% for mainsite or custom URL',
            ),
            array(
                'name'        => 'PATH_CURRENT_SITE',
                'description' => 'Path to the main site.',
                'value'       => '',
            ),
            array(
                'name'        => 'UPLOADBLOGSDIR',
                'description' => 'Path to the upload base dir, relative to ABSPATH.',
                'value'       => 'wp-content/blogs.dir',
            ),
            array(
                'name'        => 'SITE_ID_CURRENT_SITE',
                'description' => 'Network ID of the main site.',
                'value'       => '1',
            ),
            array(
                'name'        => 'SUBDOMAIN_INSTALL',
                'description' => 'Defines if it’s a subdomain install or not.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'SUNRISE',
                'description' => 'When defined WordPres will load the /wp-content/sunrise.php file.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'UPLOADS',
                'description' => 'Path to site specific upload dir, relative to ABSPATH.',
                'value'       => 'UPLOADBLOGSDIR /{blogid}/files/',
            ),
            array(
                'name'        => 'WPMU_ACCEL_REDIRECT',
                'description' => '(De)activates support for X-Sendfile Header.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WPMU_SENDFILE',
                'description' => '(De)activates support for X-Accel-Redirect Header.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WP_ALLOW_MULTISITE',
                'description' => 'When defined the multisite function will be accessible (Tools → Network Setup).',
                'value'       => 'true',
            ),
        ),
    ),

    'cache_compression'       => array(
        'title'     => 'Cache & Compression',
        'constants' => array(
            array(
                'name'        => 'WP_CACHE',
                'description' => 'When defined WordPres will load the /wp-content/advanced-cache.php file.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WP_CACHE_KEY_SALT',
                'description' => 'Secret key.',
                'value'       => '',
            ),
            array(
                'name'        => 'COMPRESS_CSS',
                'description' => '(De)activates the compressing of stylesheets.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'COMPRESS_SCRIPTS',
                'description' => '(De)activates the compressing of Javascript files.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'CONCATENATE_SCRIPTS',
                'description' => '(De)activates the consolidation of Javascript or CSS files before compressing.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'ENFORCE_GZIP',
                'description' => '(De)activates gzip output.',
                'value'       => 'true|false',
            ),
        ),
    ),

    'themes'                  => array(
        'title'     => 'Theme',
        'constants' => array(
            array(
                'name'        => 'BACKGROUND_IMAGE',
                'description' => 'Defines the default background image.',
                'value'       => '',
            ),
            array(
                'name'        => 'HEADER_IMAGE',
                'description' => 'Defines the default header image.',
                'value'       => '',
            ),
            array(
                'name'        => 'HEADER_IMAGE_HEIGHT',
                'description' => 'Specifies the height of the header image.',
                'value'       => '',
            ),
            array(
                'name'        => 'HEADER_IMAGE_WIDTH',
                'description' => 'Defines the width of the header image.',
                'value'       => '',
            ),
            array(
                'name'        => 'HEADER_TEXTCOLOR',
                'description' => 'Determines the color of the header text.',
                'value'       => '',
            ),
            array(
                'name'        => 'NO_HEADER_TEXT',
                'description' => 'Enables or disables support for header text.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'STYLESHEETPATH',
                'description' => 'Specifies the absolute path to the theme folder, which is the folder where the current parent or child theme\'s stylesheet file is located. It does not contain a trailing slash. See also. get_stylesheet_directory().',
                'value'       => '',
            ),
            array(
                'name'        => 'TEMPLATEPATH',
                'description' => 'Specifies an absolute path from the root of the site to the current theme (parent, not child). Does not contain a slash at the end. See "Theme Loading". get_template_directory().',
                'value'       => 'values',
            ),
            array(
                'name'        => 'WP_USE_THEMES',
                'description' => 'Enables or disables theme loading.',
                'value'       => 'true|false',
            ),
        ),
    ),

    'blocks'                  => array(
        'title'     => 'Blocks',
        'constants' => array(
            array(
                'name'        => 'WP_TEMPLATE_PART_AREA_HEADER',
                'description' => 'Constant for supported wp_template_part_area taxonomy (related to blocks)',
                'value'       => 'header',
            ),
            array(
                'name'        => 'WP_TEMPLATE_PART_AREA_FOOTER',
                'description' => 'Constant for supported wp_template_part_area taxonomy (related to blocks)',
                'value'       => 'footer',
            ),
            array(
                'name'        => 'WP_TEMPLATE_PART_AREA_SIDEBAR',
                'description' => 'Constant for supported wp_template_part_area taxonomy (related to blocks)',
                'value'       => 'sidebar',
            ),
            array(
                'name'        => 'WP_TEMPLATE_PART_AREA_UNCATEGORIZED',
                'description' => 'Constant for supported wp_template_part_area taxonomy (related to blocks)',
                'value'       => 'uncategorized',
            ),
        ),
    ),

    'debug'                   => array(
        'title'     => 'Debug',
        'constants' => array(
            array(
                'name'        => 'SAVEQUERIES',
                'description' => '(De)activates the saving of database queries in an array ($wpdb->queries).',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'SCRIPT_DEBUG',
                'description' => '(De)activates the loading of compressed Javascript and CSS files.',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'WP_DEBUG',
                'description' => '(De)activates the debug mode in WordPress.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WP_DEBUG_DISPLAY',
                'description' => '(De)activates the display of errors on the screen.',
                'value'       => 'true|false|null (Default: true)',
            ),
            array(
                'name'        => 'WP_DEBUG_LOG',
                'description' => '(De)activates the writing of errors to the /wp-content/debug.log file.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'WP_LOCAL_DEV',
                'description' => 'The default constant is not used anywhere in the core, but is intended as a general standard to enable, for example, some additional functionality when this constant is defined.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'WP_START_TIMESTAMP',
                'description' => 'WP code start time stamp - set as microtime( true ) at the moment of early file connection wp-includes/default-constants.php. Introduced in WP 5.2.',
                'value'       => '',
            ),
            array(
                'name'        => 'WP_TESTS_CONFIG_FILE_PATH',
                'description' => 'Location of the wp-tests-config.php file which is used for PHPUnit tests.',
                'value'       => '',
            ),
        ),
    ),

    'security_cookies'        => array(
        'title'     => 'Security & Cookies',
        'constants' => array(
            array(
                'name'        => 'ADMIN_COOKIE_PATH',
                'description' => 'Path to directory /wp-admin/.',
                'value'       => 'SITECOOKIEPATH wp-admin Or for Multisite subdirectory ``SITECOOKIEPATH```',
            ),
            array(
                'name'        => 'ALLOW_UNFILTERED_UPLOADS',
                'description' => 'Allows unfiltered uploads by admins.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'AUTH_COOKIE',
                'description' => 'Cookie name for the authentication.',
                'value'       => 'wordpress_ COOKIEHASH',
            ),
            array(
                'name'        => 'AUTH_KEY',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'AUTH_SALT',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'COOKIEHASH',
                'description' => 'Hash for generating cookie names.',
                'value'       => '',
            ),
            array(
                'name'        => 'COOKIEPATH',
                'description' => 'Path to WordPress root dir.',
                'value'       => 'Home URL without http(s)://',
            ),
            array(
                'name'        => 'COOKIE_DOMAIN',
                'description' => 'Domain of the WordPress installation.',
                'value'       => 'false or for Multisite with subdomains .domain of the main site',
            ),
            array(
                'name'        => 'CUSTOM_TAGS',
                'description' => 'Allows you to override the list of secure HTML tags. See /wp-includes/kses.php.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'DISALLOW_FILE_EDIT',
                'description' => 'Allows you to disallow theme and plugin edits via WordPress editor.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'DISALLOW_FILE_MODS',
                'description' => 'Allows you to disallow the editing, updating, installing and deleting of plugins, themes and core files via WordPress Backend.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'DISALLOW_UNFILTERED_HTML',
                'description' => 'Allows you to disallow unfiltered HTML for every user, admins too.',
                'value'       => 'true',
            ),
            array(
                'name'        => 'FORCE_SSL_ADMIN',
                'description' => 'Activates SSL for logins and in the backend.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'FORCE_SSL_LOGIN',
                'description' => 'Activates SSL for logins.',
                'value'       => 'true|false (Default: false)',
            ),
            array(
                'name'        => 'LOGGED_IN_COOKIE',
                'description' => 'Cookie name for logins.',
                'value'       => 'wordpress_logged_in_ COOKIEHASH',
            ),
            array(
                'name'        => 'LOGGED_IN_KEY',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'LOGGED_IN_SALT',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'NONCE_KEY',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'NONCE_SALT',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'PASS_COOKIE',
                'description' => 'Cookie name for the password.',
                'value'       => 'wordpresspass_ COOKIEHASH',
            ),
            array(
                'name'        => 'PLUGINS_COOKIE_PATH',
                'description' => 'Path to the plugins dir.',
                'value'       => 'WP_PLUGIN_URL without http(s)://',
            ),
            array(
                'name'        => 'SECURE_AUTH_COOKIE',
                'description' => 'Cookie name for the SSL authentication.',
                'value'       => 'wordpress_sec_ COOKIEHASH',
            ),
            array(
                'name'        => 'SECURE_AUTH_KEY',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'SECURE_AUTH_SALT',
                'description' => 'Secret key.',
                'value'       => 'See <a href="https://api.wordpress.org/secret-key/1.1/salt" target="blank">generator</a>',
            ),
            array(
                'name'        => 'SITECOOKIEPATH',
                'description' => 'Path of you site.',
                'value'       => 'Site URL without http(s)://',
            ),
            array(
                'name'        => 'TEST_COOKIE',
                'description' => 'Cookie name for the test cookie.',
                'value'       => 'wordpress_test_cookie',
            ),
            array(
                'name'        => 'USER_COOKIE',
                'description' => 'Cookie name for users.',
                'value'       => 'wordpressuser_ COOKIEHASH',
            ),
            array(
                'name'        => 'WP_FEATURE_BETTER_PASSWORDS',
                'description' => '',
                'value'       => 'true|false',
            ),
            array(
                'name'        => 'RECOVERY_MODE_COOKIE',
                'description' => '',
                'value'       => '',
            ),
        ),
    ),

    'time'                    => array(
        'title'     => 'Time',
        'constants' => array(
            array(
                'name'        => 'MINUTE_IN_SECONDS',
                'description' => 'Minute in seconds',
                'value'       => '60',
            ),
            array(
                'name'        => 'HOUR_IN_SECONDS',
                'description' => 'Hour in seconds',
                'value'       => '60 * MINUTE_IN_SECONDS',
            ),
            array(
                'name'        => 'DAY_IN_SECONDS',
                'description' => 'Day (day) in seconds',
                'value'       => '24 * HOUR_IN_SECONDS',
            ),
            array(
                'name'        => 'WEEK_IN_SECONDS',
                'description' => 'Week in seconds',
                'value'       => '7 * DAY_IN_SECONDS',
            ),
            array(
                'name'        => 'MONTH_IN_SECONDS',
                'description' => 'Month in seconds',
                'value'       => '30 * DAY_IN_SECONDS',
            ),
            array(
                'name'        => 'YEAR_IN_SECONDS',
                'description' => 'Year in seconds ',
                'value'       => '365 * DAY_IN_SECONDS',
            ),
        ),
    ),

    'filesize'                => array(
        'title'     => 'File Size',
        'constants' => array(
            array(
                'name'        => 'KB_IN_BYTES',
                'description' => 'KiloByte in Bytes',
                'value'       => '1024',
            ),
            array(
                'name'        => 'MB_IN_BYTES',
                'description' => 'MegaByte in Bytes',
                'value'       => '1048576',
            ),
            array(
                'name'        => 'GB_IN_BYTES',
                'description' => 'GigaByte in Bytes',
                'value'       => '1073741824',
            ),
            array(
                'name'        => 'TB_IN_BYTES',
                'description' => 'TeraByte in Bytes',
                'value'       => '1099511627776',
            ),
        ),
    ),

);
