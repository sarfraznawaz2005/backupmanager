<?php

return [

    #-------------------------------------------------------------------
    # Route where BackupManager will be available in your app.
    'route' => 'backupmanager',
    #-------------------------------------------------------------------

    #-------------------------------------------------------------------
    # If "true", the BackupManager page can be viewed by any user who provides
    # correct login information (eg all app users).
    'http_authentication' => false,
    #-------------------------------------------------------------------

    #-------------------------------------------------------------------
    # define binary paths
    'paths' => [
        'mysql' => 'mysql',
        'mysqldump' => 'mysqldump',
        'tar' => 'tar',
        'zcat' => 'zcat',
    ],
    #-------------------------------------------------------------------

    #-------------------------------------------------------------------
    # define backup options
    'backups' => [

        'database' => [
            // enalble or disable database backup
            'enable' => true,
            // include tables that need to be backed up. LEAVE EMPTY FOR ALL TABLES
            'tables' => [

            ],
        ],

        'files' => [
            // enalble or disable files backup
            'enable' => true,
            // include folders that need to be backed up
            'folders' => [
                base_path('app'),
                base_path('bootstrap'),
                base_path('config'),
                base_path('database'),
                base_path('public'),
                base_path('resources'),
                //base_path('storage'),
                base_path('tests'),
                //base_path('vendor'),
            ],
        ],

        // define disk options
        'disk' => 'local', // any disk from config/filesystems.php like local, ftp, s3, etc
        'backup_path' => 'backups',

        // backup files name suffix
        'backup_file_date_suffix' => date('M-d-Y-h:m:s'),

        // define number of days old backup files will be deleted before new backup
        'delete_old_backup_days' => 10
    ],

];
