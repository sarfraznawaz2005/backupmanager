[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

# Laravel BackupManager

Simple laravel package to backup/restore files and database.

## Screenshot

![Main Window](https://github.com/sarfraznawaz2005/backupmanager/blob/master/screen.gif?raw=true)

## Requirements

 - PHP >= 5.6
 - Laravel 5
 - `mysql` (to restore database)
 - `mysqldump` (to backup database)
 - `tar` (to backup/restore files)
 - `zcat` (to extract database archive)

## Installation

Via Composer

``` bash
$ composer require sarfraznawaz2005/backupmanager
```

For Laravel < 5.5:

Add Service Provider to `config/app.php` in `providers` section:
```php
Sarfraznawaz2005\BackupManager\ServiceProvider::class,
```

(Optional) Add Facade to `config/app.php` in `aliases` section:
```php
'BackupManager' => Sarfraznawaz2005\BackupManager\Facades\BackupManager::class,
```

---

Publish package's files by running below command:

```bash
$ php artisan vendor:publish --provider="Sarfraznawaz2005\BackupManager\ServiceProvider"
```
It should publish `config/backupmanager.php.php` config file and migration file.

Run `php artisan migrate` to create backup verifier (`verifybackup`)) table.

---

Finally setup options in `config/backupmanager.php` file and open the backup manager at url you have specified in `route` option eg `http//yourapp.com/backupmanager`, you should now see interface of BackupManager.

See `config/backupmanager.php` file for more information about backup settings.

## Setting Up Automatic Backups

To setup automatic backups, place following in `app/Console/Kernel.php` file's `$commands` array:

```php
\Sarfraznawaz2005\BackupManager\Console\BackupCommand::class,
```
and then

```php
$schedule->command('backupmanager:create')->daily();
```

Although packages provides GUI interface to manage backups, following commands are also available:

```bash
  backupmanager:create                  Creates backup of files and/or database.
  backupmanager:list                    Shows list of backups taken.
  backupmanager:restore                 Restores a backup already taken.
```


## Credits

- [Sarfraz Ahmed][link-author]
- [All Contributors][link-contributors]

## License

Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/sarfraznawaz2005/backupmanager.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/sarfraznawaz2005/backupmanager.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/sarfraznawaz2005/backupmanager
[link-downloads]: https://packagist.org/packages/sarfraznawaz2005/backupmanager
[link-author]: https://github.com/sarfraznawaz2005
[link-contributors]: https://github.com/sarfraznawaz2005/backupmanager/graphs/contributors
