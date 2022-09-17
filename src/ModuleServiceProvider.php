<?php

namespace Lordjoo\Laramodular;

use Filament\Facades\Filament;
use Illuminate\Support\ServiceProvider;
use Lordjoo\Laramodular\Traits\FilamentModuleTrait;

class ModuleServiceProvider extends ServiceProvider
{
    use FilamentModuleTrait;

    public array $module_config;

    private string $module_path;

    public function __construct($app)
    {
        parent::__construct($app);
        $reflector = new \ReflectionClass($this);
        $this->module_path = pathinfo($reflector->getFileName())[ 'dirname'];
    }

    public function register()
    {
        $this->module_config = require $this->module_path . '/config.php';
        if (class_exists('Filament\Facades\Filament'))
            $this->registerFilamentFiles();


    }

    public function boot()
    {

    }



}
