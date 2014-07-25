<?php namespace Terion\PackageInstaller;

use Carbon\Carbon;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Illuminate\Console\Command;
use Packagist\Api\Client;
use Packagist\Api\Result\Package;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

/**
 * Class PackageInstallCommand
 *
 * @package  Terion\PackageInstaller
 * @author   Volodymyr Kornilov <mail@terion.name>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     http://terion.name
 */
class PackageInstallCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'package:install';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs a package';

    /**
     * Packagist Api Client object.
     *
     * @var Client
     */
    private $packagist;
    /**
     * Object that searches for ServiceProviders and Facades.
     *
     * @var SpecialsResolver
     */
    private $resolver;
    /**
     * Object that patches configs.
     *
     * @var ConfigUpdater
     */
    private $config;

    /**
     * @param Client           $packagist
     * @param SpecialsResolver $resolver
     * @param ConfigUpdater    $config
     */
    public function __construct(Client $packagist, SpecialsResolver $resolver, ConfigUpdater $config)
    {
        parent::__construct();
        $this->packagist = $packagist;
        $this->resolver = $resolver;
        $this->config = $config;
        gc_enable();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $packageName = $this->argument('packageName');
        if (strpos($packageName, '/') !== false) {
            $this->getPackage($packageName);
        } else {
            $this->searchPackage($packageName);
        }
    }

    /**
     * Try to get a package by name. If not present — search it.
     *
     * @param $packageName
     */
    protected function getPackage($packageName)
    {
        try {
            $package = $this->packagist->get($packageName);
            $this->getOutput()->writeln(sprintf(
                'Package to install: <info>%s</info> (%s)',
                $package->getName(),
                $package->getDescription()
            ));
            $this->requirePackage($package);
        } catch (ClientErrorResponseException $e) {
            $this->searchPackage($packageName);
        }
    }

    /**
     * Installs package.
     *
     * @param Package $package
     */
    protected function requirePackage(Package $package)
    {
        $version = $this->chooseVersion($package);
        passthru(sprintf(
            'composer require %s:%s',
            $package->getName(),
            $version
        ));
        $this->comment('Package ' . $package->getName() . ' installed');
        $versions = $package->getVersions();
        $v = array_get($versions, $version);

        // Run post-process in separate command to load it with new autoloader
        passthru(sprintf(
            'php artisan package:process %s %s',
            $package->getName(),
            base64_encode(serialize($v))
        ));
    }

    /**
     * Ask for package version to install (select from list).
     *
     * @param Package $package
     *
     * @return mixed
     */
    protected function chooseVersion(Package $package)
    {
        $versions = $package->getVersions();

        if (count($versions) === 1) {
            $v = current($versions)->getVersion();
            $this->comment('Version to install: ' . $v);
            return $v;
        }

        $default = 1;
        $latest = $this->getLatestStable($package);
        $i = 1;

        $this->comment('Available versions:');
        foreach ($versions as $version) {
            $v = $version->getVersion();
            $versionsAvailable[$i] = $v;
            if ($v === $latest) {
                $default = $i;
            }

            $this->getOutput()->writeln(sprintf(
                '[%d] <info>%s</info> (%s)',
                $i,
                $v,
                Carbon::parse($version->getTime())->format('Y-m-d H:i:s')
            ));

            ++$i;
        }

        $choose = $this->ask('Select version by number [' . $default . ']:', $default);

        if (!is_numeric($choose) || !isset($versionsAvailable[$choose])) {
            $this->error('Incorrect value given!');
            return $this->chooseVersion($package);
        } else {
            $this->comment('Your choice: ' . $versionsAvailable[$choose]);
            return $versionsAvailable[$choose];
        }
    }

    /**
     * Detect latest stable version of chosen package.
     *
     * @param Package $package
     *
     * @return mixed|string
     */
    protected function getLatestStable(Package $package)
    {
        $version = 'dev-master';
        $stableVersions = array();
        foreach ($package->getVersions() as $v) {
            if (!str_contains(strtolower($v->getVersion()), ['dev', 'beta', 'alpha', 'b', 'a'])) {
                $stableVersions[] = $v->getVersion();
            }
        }
        usort($stableVersions, function ($a, $b) {
            $a = intval(preg_replace('/[^0-9]/', '', $a));
            $b = intval(preg_replace('/[^0-9]/', '', $b));
            return ($a < $b) ? 1 : -1;
        });
        if (count($stableVersions) > 0) {
            $version = array_shift($stableVersions);
        }

        return $version;
    }

    /**
     * Perform search on packagist and ask package select.
     *
     * @param $packageName
     */
    protected function searchPackage($packageName)
    {
        $this->comment('Searching for package...');
        $packages = $this->packagist->search($packageName);
        $total = count($packages);

        if ($total === 0) {
            $this->comment('No packages found');
            exit;
        }
        $this->comment('Found ' . $total . ' packages:');

        foreach ($packages as $i => $package) {
            $this->getOutput()->writeln(sprintf(
                '[%d] <info>%s</info> (%s)',
                $i + 1,
                $package->getName(),
                $package->getDescription()
            ));
        }

        $this->choosePackage($packages);
    }

    /**
     * Ask package select from a list.
     *
     * @param $packages
     */
    protected function choosePackage($packages)
    {
        $choose = $this->ask('Select package by number [1]:', '1');
        if (!is_numeric($choose) or !isset($packages[$choose - 1])) {
            $this->error('Incorrect value given!');
            $this->choosePackage($packages);
        } else {
            $index = $choose - 1;
            $result = $packages[$index];
            $this->comment('Your choice: ' . $result->getName());
            $package = $this->getPackage($result->getName());
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('packageName', InputArgument::REQUIRED, 'Name of the composer package to be installed.'),
        );
    }

}