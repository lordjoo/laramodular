<?php

namespace Lordjoo\Laramodular;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name}';

    protected $description = 'Create New Module';
    private string $module_name;
    private string $module_path;

    public function handle()
    {

        $this->module_name = $this->argument('name');
        $this->module_name = Str::of($this->module_name)
            ->ucfirst()
            ->camel()
            ->studly();
        $modules_path = config('laramodular.modules_path');
        if (!is_dir($modules_path))
            mkdir($modules_path, 0777, true);

        $this->module_path = $modules_path . DIRECTORY_SEPARATOR . $this->module_name;

        if (is_dir($this->module_path)) {
            $this->error("Module {$this->module_name} already exists!");
            return;
        }

        mkdir($this->module_path, 0755, true);
        $dirs = [
            "Controllers" => [
                "Api" => "DIR",
                "Web" => "DIR",
            ],
            "Database" => [
                "Migrations" => "DIR",
                "Seeders" => "DIR",
            ],
            "Jobs" => "DIR",
//            "Lang" => "DIR",
            "Models" => "DIR",
            "Requests" => "DIR",
            "ApiResources" => "DIR",
            "Resources" => [
                'css' => "DIR",
                'js' => "DIR",
                'views' => "DIR",
            ],
            'Routes' => [
                'web.php' => "FILE",
                'api.php' => "FILE",
            ],
//            "Views" => "DIR",
            'config.php' => "FILE",
            $this->module_name. 'ModuleServiceProvider.php' => "FILE",
        ];
        foreach ($dirs as $dir => $type) {
            if (is_array($type)) {
                mkdir($this->module_path . DIRECTORY_SEPARATOR . $dir, 0755, true);
                foreach ($type as $subdir => $subtype) {
                    if ($subtype == "DIR") {
                        mkdir($this->module_path . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $subdir, 0755, true);
                        touch($this->module_path . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . '.gitingore');
                    } else {
                        file_put_contents($this->module_path . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $subdir,'<?php');
                    }
                }
            } else if ($type == "FILE") {
                touch($this->module_path . DIRECTORY_SEPARATOR . $dir);
            } else if ($type == "DIR") {
                mkdir($this->module_path . DIRECTORY_SEPARATOR . $dir, 0755, true);
                touch($this->module_path . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . '.gitingore');
            }
        }
        $this->makeConfigFile();
        $this->makeServiceProviderFile();
        $this->components->info('Module created successfully.');

    }

    private function makeConfigFile()
    {
        $stub = file_get_contents(__DIR__ . '/moduleConfig.stub');
        $stub = str_replace('{{moduleName}}', $this->module_name, $stub);
        file_put_contents($this->module_path . DIRECTORY_SEPARATOR . 'config.php', $stub);
    }

    private function makeServiceProviderFile()
    {
        $stub = file_get_contents(__DIR__ . '/moduleServiceProvider.stub');
        $stub = str_replace('{{moduleName}}', $this->module_name, $stub);
        file_put_contents($this->module_path . DIRECTORY_SEPARATOR . $this->module_name . 'ModuleServiceProvider.php', $stub);
    }

}
