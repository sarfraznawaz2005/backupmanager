<?php

Route::group(
    [
        'namespace' => 'Sarfraznawaz2005\BackupManager\Http\Controllers',
        'prefix' => config('backupmanager.route', 'backupmanager')
    ],
    function () {
        // list backups
        Route::get('/', 'BackupManagerController@index')->name('backupmanager');

        // create backups
        Route::post('create', 'BackupManagerController@createBackup')->name('backupmanager_create');

        // restore/delete backups
        Route::post('restore_delete',
            'BackupManagerController@restoreOrDeleteBackups')->name('backupmanager_restore_delete');

        // download backup
        Route::get('download/{file}', 'BackupManagerController@download')->name('backupmanager_download');
    }
);
