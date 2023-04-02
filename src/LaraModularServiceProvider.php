<?php

namespace Lordjoo\Laramodular;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Users\UsersModuleServiceProvider;

class LaraModularServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    private mixed $modules_path;
    /**
     * @var string
     */
    private mixed $modules_namespace;
    private array $modules;
    private array $commands;
    public function register()
    {
        $this->commands = $this->getCommands();
        // merge config
        $this->mergeConfigFrom(
            __DIR__ . '/laramodular.php', 'laramodular'
        );
        // register console command
        $this->commands($this->commands);
        // publish config
        $this->publishes([
            __DIR__ . '/laramodular.php' => config_path('laramodular.php'),
        ], 'config');

        $this->modules = $this->getModules();
        // register filament resources if filament is present

        $this->registerModulesServiceProvider();
    }


    public function registerModulesServiceProvider()
    {
        foreach ($this->modules as $module) {
            $serviceProviderClass = $this->modules_namespace . '\\' . $module['name'] . '\\' . $module['name'] . 'ModuleServiceProvider';
//            dd(new $serviceProviderClass);
            if (class_exists($serviceProviderClass)) {
                $this->app->register($serviceProviderClass);
            }
        }
    }

    public function getModules(): array
    {
        $this->modules_path = config('laramodular.modules_path');
        $this->modules_namespace = config('laramodular.modules_namespace');
        $modules = [];

        if (!is_dir($this->modules_path))
            mkdir($this->modules_path, 0755, true);

        $modules_dir = array_diff(scandir($this->modules_path), ['.', '..']);

        foreach ($modules_dir as $module) {
            if (!is_dir($this->modules_path . DIRECTORY_SEPARATOR . $module)) continue;

            $configFile = $this->modules_path . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'config.php';
            if (!file_exists($configFile)) continue;

            $config = require $configFile;

            if (!is_array($config)) continue;

            if (!$config['status']) continue;

            $modules[$module] = $config;
            $modules[$module]['module_path'] = $this->modules_path . DIRECTORY_SEPARATOR . $module;

        }
        return $modules;
    }


    public function boot()
    {
        $this->loadModules();
    }

    public function loadModules()
    {
        foreach ($this->modules as $module_config) {
            $moduleDir = $module_config['module_path'];
            $module = $module_config['name'];
            $web = $moduleDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'web.php';
            $api = $moduleDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'api.php';
            $admin = $moduleDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'admin.php';
            $config = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';
            $views = $moduleDir . DIRECTORY_SEPARATOR . 'Resources/views';
            $lang = $moduleDir . DIRECTORY_SEPARATOR . 'Lang';
            $migration = $moduleDir . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
            $middleware = $moduleDir . DIRECTORY_SEPARATOR . 'Middleware';

            if (file_exists($config)) {
                $config = include $config;
                if (!is_array($config)) return;
                //check if module is enabled
                if (!$config['status']) return;

                //Register Module Helpers
                if (isset($config['autoload'])) {
                    foreach ($config['autoload'] as $f) {
                        include $moduleDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f);
                    }
                }
            } else {
                return;
            }

            //Register Module Web Routes
            if (file_exists($web)) {
                $this->mapWebRoutes($this->modules_namespace . '\\' . $module . '\\Controllers\\Web', $web);
            }

            //Register Module Admin Routes
            if (file_exists($admin)) {
                $this->mapAdminRoutes($this->modules_namespace . '\\' . $module . '\\Controllers\\Admin', $admin);
            }

            //Register Module Api Routes
            if (file_exists($api)) {
                $this->mapApiRoutes($this->modules_namespace . '\\' . $module . '\\Controllers\\Api', $api);
            }

            //Register Module Views
            if (is_dir($views) && file_exists($views)) {
                $this->loadViewsFrom($views, strtolower($module));
            }

            //Register Module Lang Files
            if (is_dir($lang) && file_exists($lang)) {
                $this->loadTranslationsFrom($lang, $module);
            }

            //Register Module Middleware
//            if (is_dir($middleware) && file_exists($middleware) && isset($config['middleware'])) {
//                $this->registerMiddleware($this->app['router'], $config['middleware'], $module);
//            }

            //Register Module migration
            if (is_dir($migration)) {
                $this->loadMigrationsFrom($migration);
            }


        }
    }


    protected function mapWebRoutes($namespace, $path)
    {
        Route::middleware('web')->namespace($namespace)->group($path);
    }

    protected function mapAdminRoutes($namespace, $path)
    {
        Route::prefix('admin')->middleware('web')->namespace($namespace)->group($path);
    }

    protected function mapApiRoutes($namespace, $path)
    {
        Route::prefix('api')->middleware('api')->namespace($namespace)->group($path);
    }

    protected function registerMiddleware(Router $router, $config, $module)
    {
//        foreach ($config as $name => $middleware) {
//            $class = "App\\Modules\\{$module}\\Middleware\\{$middleware}";
//            $router->aliasMiddleware($name, $class);
//        }
    }

    public function getCommands() : array
    {
        $commands = [MakeModuleCommand::class];
        if(class_exists("Filament\Commands\MakeResourceCommand")) {
            array_push($commands, MakeFilamentResourceCommand::class);
        }
        return $commands;
    }



}
