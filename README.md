# Ultimate Package Installer

![status:beta](http://img.shields.io/badge/status-beta-yellow.svg)
![license:mit](http://img.shields.io/packagist/l/doctrine/orm.svg)

This package provides a cli interface for fast and easy package install (any package, not only laravel-specific).

Example: type `php artisan package:install illuminage` and hit `enter` twice will install latest stable version of [anahkiasen/illuminage](https://github.com/Anahkiasen/illuminage), add ServiceProviders and Aliases.
```sh
$ php artisan package:install illuminage
Found 1 packages:
[1] anahkiasen/illuminage (Wrapper for the Imagine library to hook into the Laravel framework)
Select package by number [1]:
Your choice: anahkiasen/illuminage
Package to install: anahkiasen/illuminage (Wrapper for the Imagine library to hook into the Laravel framework)
Available versions:
[1] dev-develop (2014-04-10 16:39:55)
[2] dev-master (2014-04-09 18:57:37)
[3] 1.2.1 (2014-04-09 18:57:37)
[4] 1.2.0 (2014-01-14 12:08:52)
[5] 1.1.0 (2013-11-06 01:00:03)
[6] 1.0.0 (2013-03-27 11:34:38)
Select version by number [3]:
Your choice: 1.2.1
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
Nothing to install or update
Generating autoload files
```

## Why?
How do you install a package?

You go to github or packagist or google, search for desired package, see it's full name and available versions, then require it via composer's cli or manually patch `composer.json`, then you search in readme what service providers and facades this package provide and manually copypast them to config. **That's annoying!** Machine must do this.

And here is the solution.

## Installation
1. Require package:
```
composer require terion/package-installer:dev-master
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
This will install `yaap/theme` package. If there is no such package it will fallback to search.

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
Installer will now update `composer.json`, install the package, search for ServiceProviders and Facades and patch `app/config/app.php`. It also respects [Ryan's](https://github.com/rtablada) [package installer](https://github.com/rtablada/package-installer) `provides.json` but still will make the work without it.
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

## TODO:
* Fully automatic (silent) mode
* Passthrough parameters to composer cli
* Test with most popular packages
* Deal with environment-specific packages
* Unittesting