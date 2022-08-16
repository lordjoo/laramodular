<?php

namespace Lordjoo\Laramodular;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/laramodular.php', 'laramodular'
        );
        // register console command
        $this->commands([
            NewModuleCommand::class,
        ]);
    }


    public function boot()
    {
        $this->modules_path = config('laramodular.modules_path');
        $this->modules_namespace = config('laramodular.modules_namespace');

        if (!is_dir($this->modules_path)) {
            mkdir($this->modules_path, 0755, true);
        }

        // Load All Modules
        $modules = array_diff(scandir($this->modules_path), ['.', '..']);

        //Load system Modules
        foreach ($modules as $module) {
            $this->loadModules($module);
        }
    }

    public function loadModules($module)
    {
        $currentDir = $this->modules_path . DIRECTORY_SEPARATOR . $module;
        if (is_dir($currentDir)) {
            // Module files structure
            $web = $currentDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'web.php';
            $api = $currentDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'api.php';
            $admin = $currentDir . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'admin.php';
            $config = $currentDir . DIRECTORY_SEPARATOR . 'config.php';
            $views = $currentDir . DIRECTORY_SEPARATOR . 'Views';
            $lang = $currentDir . DIRECTORY_SEPARATOR . 'Lang';
            $migration = $currentDir . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
            $middleware = $currentDir . DIRECTORY_SEPARATOR . 'Middleware';

            if (file_exists($config)) {
                $config = include $config;
                if (!is_array($config)) return;
                //check if module is enabled
                if (!$config['status']) return;

                //Register Module Helpers
                if (isset($config['autoload'])) {
                    foreach ($config['autoload'] as $f) {
                        include $currentDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f);
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
                $this->loadViewsFrom($views, $module);
            }

            //Register Module Lang Files
            if (is_dir($lang) && file_exists($lang)) {
                $this->loadTranslationsFrom($lang, $module);
            }

            //Register Module Middleware
            if (is_dir($middleware) && file_exists($middleware) && isset($config['middleware'])) {
                $this->registerMiddleware($this->app['router'], $config['middleware'], $module);
            }

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
        Route::prefix('api')->middleware(['api', 'throttle:3000,1'])->namespace($namespace)->group($path);
    }

    protected function registerMiddleware(Router $router, $config, $module)
    {
        foreach ($config as $name => $middleware) {
            $class = "App\\Modules\\{$module}\\Middleware\\{$middleware}";
            $router->aliasMiddleware($name, $class);
        }
    }



}
