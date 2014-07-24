<?php

namespace Terion\PackageInstaller;

use Packagist\Api\Result\Package;
use Packagist\Api\Result\Package\Version;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Class SpecialsResolver
 *
 * @package  Terion\PackageInstaller
 * @author   Volodymyr Kornilov <mail@terion.name>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     http://terion.name
 */
class SpecialsResolver
{

    /**
     * @var Finder
     */
    private $finder;
    /**
     * @var bool
     */
    private $providesJsonDetected = false;

    /**
     * @param Finder $finder
     */
    public function __construct(Finder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * @param Package|string $package
     * @param Version        $version
     *
     * @return array|bool
     */
    public function specials($package, Version $version)
    {
        $specials = $this->searchProvides($package);
        if ($specials) {
            $this->providesJsonDetected = true;
        } else {
            $this->providesJsonDetected = false;
            $specials = $this->searchReflect($package, $version);
        }
        if (count($specials['providers']) === 0 && count($specials['aliases']) === 0) {
            return false;
        }
        return $specials;
    }

    /**
     * @param Package|string $package
     *
     * @return array|bool
     */
    protected function searchProvides($package)
    {
        $packageName = $package instanceof Package ? $package->getName() : $package;
        $providesJsonLocation = base_path("vendor/{$packageName}/provides.json");
        if (file_exists($providesJsonLocation)) {
            $provides = json_decode(str_replace('\\', '\\\\', file_get_contents($providesJsonLocation)), true);
            $return = array('providers' => array(), 'aliases' => array());
            if (isset($provides['providers']) && is_array($provides['providers'])) {
                $return['providers'] = $provides['providers'];
            }
            if (isset($provides['aliases']) && is_array($provides['aliases'])) {
                $return['aliases'] = $provides['aliases'];
            }
            return $return;
        }
        return false;
    }

    /**
     * @param Package|string $package
     * @param Version        $version
     *
     * @return array
     */
    protected function searchReflect($package, Version $version)
    {
        $namespaces = $this->getNamespaces($package, $version);
        $this->loadPackageClasses($package, $version);
        $classes = $this->listPackageClasses($namespaces);

        $providers = array();
        $aliases = array();
        foreach ($classes as $class) {
            $reflect = new ReflectionClass($class);
            if ($reflect->isInstantiable()) {
                if ($reflect->isSubclassOf('Illuminate\Support\ServiceProvider')) {
                    $providers[] = $class;
                } elseif ($reflect->isSubclassOf('Illuminate\Support\Facades\Facade')) {
                    $aliases[] = array(
                        'alias' => class_basename($class),
                        'facade' => $class
                    );
                }
            }
        }
        return array('providers' => $providers, 'aliases' => $aliases);
    }

    /**
     * @param Package|string $package
     * @param Version        $version
     *
     * @return array
     */
    protected function getNamespaces($package, Version $version)
    {
        $autoload = $version->getAutoload();
        $namespaces = array();
        foreach ($autoload as $type => $map) {
            if ($type === 'psr-0' or $type === 'psr-4') {
                foreach ($map as $ns => $rules) {
                    $namespace = str_replace('\\\\', '\\', $ns);
                    $namespace = rtrim($namespace, '\\') . '\\';
                    $namespaces[] = $namespace;
                }
            }
        }
        return $namespaces;
    }

    /**
     * @param Package|string $package
     * @param Version        $version
     */
    protected function loadPackageClasses($package, Version $version)
    {
        $autoload = $version->getAutoload();
        $this->finder->files()->name('*.php');
        $pathsToLoad = $this->getLoadPackagePathes($package, $version);
        foreach ($pathsToLoad['directories'] as $path) {
            $this->finder->in($path);
        }
        foreach ($this->finder as $file) {
            include_once $file->getRealpath();
        }
        foreach ($pathsToLoad['files'] as $file) {
            include_once $file;
        }
    }

    /**
     * @param Package|string $package
     * @param Version        $version
     *
     * @return array
     */
    protected function getLoadPackagePathes($package, Version $version)
    {
        $packageName = $package instanceof Package ? $package->getName() : $package;
        $basePath = 'vendor' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, explode('/', $packageName));
        $paths = array('directories' => array(), 'files' => array());
        foreach ($version->getAutoload() as $type => $map) {
            switch ($type) {
                case 'classmap':
                    $paths['directories'] = array_merge($paths['directories'], (array)$map);
                    break;
                case 'psr-0':
                    foreach ($map as $ns => $src) {
                        $nsPath = implode(DIRECTORY_SEPARATOR, explode('\\', str_replace('\\\\', '\\', $ns)));
                        foreach ((array)$src as $path) {
                            $paths['directories'][] = rtrim($path, '/') . DIRECTORY_SEPARATOR . $nsPath;
                        }
                    }
                    break;
                case 'psr-4':
                    foreach ($map as $ns => $src) {
                        foreach ((array)$src as $path) {
                            $paths['directories'][] = implode(DIRECTORY_SEPARATOR, explode('/', rtrim($path, '/')));
                        }
                    }
                    break;
                case 'files':
                    $paths['files'] = array_merge($paths['files'], (array)$map);
            }
        }

        $pathsToExclude = $this->excludeFilesInPath($basePath);
        $pathsToExclude = array_map(function ($p) use ($basePath) {
            return realpath($basePath . DIRECTORY_SEPARATOR . $p);
        }, $pathsToExclude);
        $pathsToExclude = array_filter($pathsToExclude, function ($p) {
            return $p !== false;
        });

        foreach ($paths as &$maps) {
            $maps = array_unique(array_values($maps));
            $maps = array_map(function ($p) use ($basePath) {
                return realpath($basePath . DIRECTORY_SEPARATOR . $p);
            }, $maps);
            foreach ($pathsToExclude as $p) {
                foreach ($maps as $index => $map) {
                    if (strpos($map, $p) === 0) {
                        unset($maps[$index]);
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @param $basePath
     *
     * @return array
     */
    protected function excludeFilesInPath($basePath)
    {
        $paths = ['tests' . DIRECTORY_SEPARATOR, 'test' . DIRECTORY_SEPARATOR];
        $phpUnit = $basePath . DIRECTORY_SEPARATOR . 'phpunit.xml';
        if (!file_exists($phpUnit)) {
            $phpUnit = $basePath . DIRECTORY_SEPARATOR . 'phpunit.xml.dist';
        }

        if (file_exists($phpUnit)) {
            $xml = simplexml_load_file($phpUnit);
            $suites = $xml->testsuites->testsuite;
            if ($suites) {
                foreach ($suites as $ts) {
                    $paths[] = $ts->directory;
                }
            }
        }
        return array_unique($paths);
    }

    /**
     * @param array $namespaces
     *
     * @return array
     */
    protected function listPackageClasses(array $namespaces)
    {
        return array_filter(get_declared_classes(), function ($class) use ($namespaces) {
            $valid = false;
            foreach ($namespaces as $ns) {
                $valid = (strpos($class, $ns) === 0) ? true : $valid;
            }
            return $valid;
        });
    }

    /**
     * @return boolean
     */
    public function isProvidesJsonDetected()
    {
        return $this->providesJsonDetected;
    }

} 