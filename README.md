# Ultimate Package Installer

[![version](http://img.shields.io/packagist/v/terion/package-installer.svg)](https://packagist.org/packages/terion/package-installer)
![license](http://img.shields.io/packagist/l/terion/package-installer.svg)
![downloads](http://img.shields.io/packagist/dt/terion/package-installer.svg)

This package provides a cli interface for fast and easy package install (any package, not only laravel-specific).

Example:

![php artisan package:install barryvdh/laravel-dompdf](http://i.imgur.com/KJmcUyH.png)

## Why?
How do you install a package?

You go to github or packagist or google, search for desired package, see it's full name and available versions, then require it via composer's cli or manually patch `composer.json`, then you search in readme what service providers and facades this package provide and manually copypast them to config, then publish configs and assets manually... **That's annoying!** Machine must do this.

And here is the solution.

## Installation
1. Require package:
```
composer require terion/package-installer:~1
```
2. Add to `app/config/app.php` to `providers` array:
```
'Terion\PackageInstaller\PackageInstallerServiceProvider',
```

## How to use
### 1. Search for a package or install a known one:
```sh
php artisan package:install theme
```
Search for a package on composer. This will output list of found packages with numeric select. **Default selection is the fisrt package in list.**

```sh
php artisan package:install yaap/theme
```
This will install [yaap/theme](https://github.com/yaapis/Theme) package. If there is no such package it will fallback to search.

### 2. Select version
When package selected you will be promted to choose a version from list of availables by number.
```sh
Available versions:
[1] dev-master (2014-06-22 17:54:03)
[2] 1.2.6 (2014-06-22 17:54:03)
[3] 1.2.5 (2014-06-22 17:22:37)
[4] 1.2.4 (2014-05-26 11:16:15)
[5] 1.2.3 (2014-05-25 06:44:39)
[6] 1.2.2 (2014-05-24 22:39:29)
[7] 1.2.1 (2014-05-24 22:36:43)
[8] 1.2.0 (2014-05-24 22:32:40)
[9] 1.1.1 (2014-05-24 18:07:59)
[10] 1.1.0 (2014-05-24 14:28:29)
[11] 1.0.0 (2014-04-27 17:28:38)
Select version by number [2]:
```
**Default selection is the latest stable version if present.** If no stable present — then `dev-master` is selected.

### 3. PROFIT!
Installer will now update `composer.json`, install the package, search for ServiceProviders and Facades, patch `app/config/app.php` and publish package configs and assets. It also respects [Ryan's](https://github.com/rtablada) [package installer](https://github.com/rtablada/package-installer) `provides.json` but still will make the work without it.
```sh
Your choice: 1.2.6
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
  - Installing yaap/theme (1.2.6)
    Loading from cache

Writing lock file
Generating autoload files
Generating optimized class loader
Package yaap/theme installed
Processing package...
Found 1 service providers:
[1] YAAP\Theme\ThemeServiceProvider
Found 1 aliases:
[1] YAAP\Theme\Facades\Theme [Theme]
```

## What problems can you face with
The only problem that I've discovered is that some packages contain facades, that shouldn't be included in app config,
but they do and this can break application and should be manually fixed (but this is very easy).

As an example — `orchestra/support` contains about 20 facades and they collide with Laravel facades.
Package Installer handles this safely by commenting colliding aliases
so in such case you should manually remove redundant aliases and uncomment old ones. 

## TODO:
* Fully automatic (silent) mode
* Passthrough parameters to composer cli
* Deal with environment-specific packages
* Unittesting
