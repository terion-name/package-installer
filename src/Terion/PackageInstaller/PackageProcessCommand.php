<?php namespace Terion\PackageInstaller;

use Exception;
use Illuminate\Console\Command;
use Packagist\Api\Result\Package;
use Packagist\Api\Result\Package\Version;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

/**
 * Class PackageProcessCommand
 *
 * @package  Terion\PackageInstaller
 * @author   Volodymyr Kornilov <mail@terion.name>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     http://terion.name
 */
class PackageProcessCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'package:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ONLY FOR TECHNICAL PURPOSES! Postprocesses a package after install';

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
     * @param SpecialsResolver $resolver
     * @param ConfigUpdater    $config
     */
    public function __construct(SpecialsResolver $resolver, ConfigUpdater $config)
    {
        parent::__construct();
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
        $version = $this->argument('version');
        $version = unserialize(base64_decode($version));
        $this->postProcess($packageName, $version);
    }

    /**
     * Process package after install
     * e.g. search and register ServiceProviders and Facades.
     *
     * @param         $packageName
     * @param Version $version
     */
    protected function postProcess($packageName, Version $version)
    {
        $this->comment('Processing package...');

        $specials = $this->resolver->specials($packageName, $version);

        if ($specials === false) {
            $this->info("No Service Providers and Facades found. Assuming that package is not designed for Laravel");
            $this->info('Finishing');
            exit;
        }

        if ($this->resolver->isProvidesJsonDetected()) {
            $this->comment('provides.json detected');
        }

        if (count($specials['providers'])) {
            $this->comment('Found ' . count($specials['providers']) . ' service providers:');
            foreach ($specials['providers'] as $i => $p) {
                $this->info("[" . ($i + 1) . "] {$p}");
                $this->config->addProvider($p);
            }
        }
        if (count($specials['aliases'])) {
            $this->comment('Found ' . count($specials['aliases']) . ' aliases:');
            foreach ($specials['aliases'] as $i => $alias) {
                $this->info("[" . ($i + 1) . "] {$alias['facade']} [{$alias['alias']}]");
                $this->config->addAlias($alias['alias'], $alias['facade']);
            }
        }

        // publish configs and assets
        try {
            $this->call('config:publish', array('package' => $packageName));
        } catch (Exception $e) {
            $this->comment($e->getMessage());
        }
        try {
            $this->call('asset:publish', array('package' => $packageName));
        } catch (Exception $e) {
            $this->comment($e->getMessage());
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
            array('version', InputArgument::REQUIRED, 'Serialized and base64 encoded Version object'),
        );
    }

}