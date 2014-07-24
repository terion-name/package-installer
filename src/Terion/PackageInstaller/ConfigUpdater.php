<?php namespace Terion\PackageInstaller;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Symfony\Component\Finder\Finder;

/**
 * Class ConfigUpdater
 *
 * @package  Terion\PackageInstaller
 * @author   Volodymyr Kornilov <mail@terion.name>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     http://terion.name
 */
class ConfigUpdater
{

    /**
     * Path to config file.
     *
     * @var string
     */
    protected $configFile;
    /**
     * Working environment.
     *
     * @var string
     */
    protected $env;
    /**
     * Filesystem object.
     *
     * @var Filesystem
     */
    protected $file;
    /**
     * Default quote type.
     *
     * @var string
     */
    protected $defaultQuoteType = "'";
    /**
     * Default separator between config items.
     *
     * @var string
     */
    protected $defaultSeparator = ",\r\n		";
    /**
     * @var Application
     */
    private $app;

    /**
     * @param Filesystem  $filesystem
     * @param Application $app
     */
    public function __construct(Filesystem $filesystem, Application $app)
    {
        $this->app = $app;
        $this->env = $this->app->environment();
        $this->file = $filesystem;
        $this->configFile = $this->app['path'] . '/config/app.php';

        /*
        Adding environment-specific packages has some nuances
        See:
        https://github.com/laravel/framework/issues/1603
        https://github.com/barryvdh/laravel-debugbar/issues/86
        https://github.com/laravel/framework/issues/3327
        So at the moment this is at stage of TODO
        if ($this->env === 'production') {
            $this->configFile = $this->app['path'] . '/config/app.php';
        }
        else {
            $this->configFile = $this->app['path'] . '/config/' . $this->env . '/app.php';
            if (!$this->file->exists($this->configFile)) {
                $this->file->copy(__DIR__ . '/app.config.stub', $this->configFile);
            }
        }
        */
    }

    /**
     * List installed service providers.
     *
     * @return mixed
     */
    public function getServiceProviders()
    {
        $cfg = $this->file->getRequire($this->configFile);
        return array_get($cfg, 'providers');
    }

    /**
     * List installed facades.
     *
     * @return mixed
     */
    public function getAliases()
    {
        $cfg = $this->file->getRequire($this->configFile);
        return array_get($cfg, 'aliases');
    }

    /**
     * Add specified provider.
     *
     * @param $provider
     */
    public function addProvider($provider)
    {
        if (!in_array($provider, $this->getServiceProviders())) {
            $quote = $this->getQuoteType('providers');
            $separator = $this->getArrayItemsSeparator('providers');
            $anchor = $this->getInsertPoint('providers');

            $insert = $separator . $quote . $provider . $quote . ',';

            $this->write($insert, $anchor);
        }
    }

    /**
     * Detect quote type used in selected config item (providers, aliases).
     *
     * @param $item
     *
     * @return string
     */
    protected function getQuoteType($item)
    {
        $bounds = $this->getConfigItemBounds($item);

        if (!$bounds[0] or !$bounds[1]) {
            return $this->defaultQuoteType;
        }

        $file = $this->getFileContents();
        $substr = substr($file, $bounds[0], $bounds[1] - $bounds[0] + 1);
        return substr_count($substr, '"') > substr_count($substr, "'") ? '"' : "'";
    }

    /**
     * Get bite bounds of selected config item (providers, aliases) in file.
     *
     * @param $item
     *
     * @return array
     */
    protected function getConfigItemBounds($item)
    {
        $file = $this->getFileContents();

        if (!$file) {
            return [null, null];
        }

        $searchStart = '/[\'"]' . $item . '[\'"]/';
        preg_match($searchStart, $file, $matchStart, PREG_OFFSET_CAPTURE);
        $start = array_get(reset($matchStart), 1);
        $end = $start + 1;

        // search for array closing that is not commented
        $match = [')', ']'];
        for ($i = $start; $i <= strlen($file); ++$i) {
            $char = $file[$i];
            if (in_array($char, $match)) {
                if (!$this->isCharInComment($file, $i)) {
                    $end = $i;
                    break;
                }
            }
        }

        return [$start, $end];
    }

    /**
     * Detect config items separator used in selected config item (providers, aliases).
     *
     * @param $item
     *
     * @return string
     */
    protected function getArrayItemsSeparator($item)
    {
        $cfg = $this->file->getRequire($this->configFile);

        if (!$cfg) {
            return $this->defaultSeparator;
        }

        $file = $this->getFileContents();

        $arr = array_get($cfg, $item);

        $lastItem = end($arr);
        $preLastItem = prev($arr);

        if (!$lastItem or !$preLastItem) {
            return $this->defaultSeparator;
        }

        preg_match('/\,/', $file, $matchStart, PREG_OFFSET_CAPTURE, strpos($file, $preLastItem));
        $start = array_get(reset($matchStart), 1);

        $searchEnd = preg_match('/[\'"]/', $file, $matchEnd, PREG_OFFSET_CAPTURE, $start);
        $end = array_get(reset($matchEnd), 1);

        $separator = substr($file, $start, $end - $start);

        // remove comments
        $separator = preg_replace('/\/\/.*/ui', '', $separator);
        $separator = preg_replace('/#.*/ui', '', $separator);
        $separator = preg_replace('/\/\*(.*)\*\//ui', '', $separator);

        return $separator;
    }

    /**
     * Detect point where to insert new data for selected config item (providers, aliases).
     *
     * @param $for
     *
     * @return array
     */
    protected function getInsertPoint($for)
    {
        $bound = $this->getConfigItemBounds($for);
        $file = $this->getFileContents();
        $match = ['\'', '"', ','];
        $matches = [];

        for ($i = $bound[0]; $i <= $bound[1]; ++$i) {
            $char = $file[$i];
            if (in_array($char, $match)) {
                if (!$this->isCharInComment($file, $i)) {
                    $matches[] = ['position' => $i + 1, 'symbol' => $char];
                }
            }
        }
        return end($matches);
    }

    /**
     * Detect is character at specified position is inside of a comment
     *
     * @param $haystack
     * @param $charPosition
     *
     * @return bool
     */
    protected function isCharInComment($haystack, $charPosition)
    {
        // check for line comment
        for ($c = $charPosition; $c > 0; --$c) {
            if ($haystack[$c] === PHP_EOL) {
                break;
            } elseif ($haystack[$c] === '#' or ($haystack[$c] === '/'
                    and ($haystack[$c + 1] === '/' or $haystack[$c - 1] === '/'))
            ) {
                return true;
            }
        }
        // check for block comment
        $openingsCount = 0;
        $closingsCount = 0;
        for ($c = $charPosition; $c > 0; --$c) {
            if ($haystack[$c] === '*' and $haystack[$c - 1] === '/') {
                ++$openingsCount;
            }
            if ($haystack[$c] === '/' and $haystack[$c - 1] === '*') {
                ++$closingsCount;
            }
        }
        if ($openingsCount !== $closingsCount) {
            return true;
        }
        return false;
    }

    /**
     * Write new data to config file for selected config item (providers, aliases).
     *
     * @param $text
     * @param $anchor
     */
    protected function write($text, $anchor)
    {
        $this->backup();
        $file = $this->getFileContents();
        if ($anchor['symbol'] === ',') {
            $text = ltrim($text, ',');
        }
        $file = substr_replace($file, $text, $anchor['position'], 0);
        $this->file->put($this->configFile, $file);
        $this->cleanup();
    }

    /**
     * Backup config file
     */
    protected function backup()
    {
        $from = $this->configFile;
        $pathinfo = pathinfo($from);
        $to = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '.bak.php';
        $this->file->copy($from, $to);
    }

    /**
     * Cleanup backup
     */
    protected function cleanup()
    {
        $pathinfo = pathinfo($this->configFile);
        $backup = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '.bak.php';
        $this->file->delete($backup);
    }

    /**
     * Add specified facade.
     *
     * @param $alias
     * @param $facade
     */
    public function addAlias($alias, $facade)
    {
        if ($facadeCurrent = array_get($this->getAliases(), $alias)) {
            if ($facadeCurrent === $facade) {
                return;
            }
            $this->commentOut($alias, 'aliases');
        }

        $quote = $this->getQuoteType('aliases');
        $separator = $this->getArrayItemsSeparator('aliases');
        $anchor = $this->getInsertPoint('aliases');

        $insert = $separator . $quote . $alias . $quote . ' => ' . $quote . $facade . $quote . ',';

        $this->write($insert, $anchor);
    }

    /**
     * Comment item
     *
     * @param $search
     * @param $from
     */
    protected function commentOut($search, $from)
    {
        $bounds = $this->getConfigItemBounds($from);
        $file = $this->getFileContents();
        $cutted = substr($file, 0, $bounds[1]);

        preg_match_all(
            '/[\'"]' . preg_quote($search) . '[\'"]/',
            $cutted,
            $matchFacade,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );
        foreach ($matchFacade as $match) {
            if (!$this->isCharInComment($cutted, $match[0][1])) {
                $commentFrom = $match[0][1];
                $comma = strpos($cutted, ',', $commentFrom);
                while ($this->isCharInComment($cutted, $comma)) {
                    $comma = strpos($cutted, ',', $comma);
                }
                $commentTill = $comma + 1;
                $this->write('*/', ['position' => $commentTill, 'symbol' => '']);
                $this->write('/*', ['position' => $commentFrom, 'symbol' => '']);
            }
        }
    }

    /**
     * Get contents of config file
     *
     * @return string
     */
    protected function getFileContents()
    {
        return $this->file->get($this->configFile);
    }

} 