<?php namespace Terion\PackageInstaller;

use Illuminate\Support\ServiceProvider;

/**
 * Class PackageInstallerServiceProvider
 *
 * @package  Terion\PackageInstaller
 * @author   Volodymyr Kornilov <mail@terion.name>
 * @license  MIT http://opensource.org/licenses/MIT
 * @link     http://terion.name
 */
class PackageInstallerServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('terion/package-installer');
        $this->bindInstallCommand();
        $this->registerCommands();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Register commands in artisan
     */
    protected function registerCommands()
    {
        $this->commands('package.install');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    /**
     * Bind command to IoC
     */
    protected function bindInstallCommand()
    {
        $this->app['package.install'] = $this->app->share(function ($app) {
            return $app->make('Terion\PackageInstaller\PackageInstallCommand');
        });
    }

}
