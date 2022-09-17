<?php

namespace Lordjoo\Laramodular\Traits;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;

trait FilamentModuleTrait
{

    public array $filamentResources = [];
    public array $filamentPages = [];
    public array $filamentWidgets = [];
    public array $livewireComponents = [];

    public function registerFilamentFiles()
    {

        $resourcesPath = $this->module_config['filament']['resourcePath'] ?? $this->module_path . '/Filament/Resources';
        $pagesPath = $this->module_config['filament']['pagePath'] ?? $this->module_path . '/Filament/Pages';
        $widgetsPath = $this->module_config['filament']['widgetPath'] ?? $this->module_path . '/Filament/Widgets';
        $directory = $this->module_config['filament']['path'] ?? $this->module_path.'/Filament';
        $namespace = $this->module_config['filament']['namespace'] ?? (new \ReflectionClass($this))->getNamespaceName(). '\\Filament';

        // load files
        $filesystem = app(Filesystem::class);
        if (! $filesystem->isDirectory($directory)) {
            return;
        }

        foreach ($filesystem->allFiles($directory) as $file) {
            $fileClass = (string) Str::of($namespace)
                ->append('\\', $file->getRelativePathname())
                ->replace(['/', '.php'], ['\\', '']);
            
            if (! class_exists($fileClass)) {
                continue;
            }

            if ((new ReflectionClass($fileClass))->isAbstract()) {
                continue;
            }

            $filePath = Str::of($directory . '/' . $file->getRelativePathname());

            if ($filePath->startsWith($resourcesPath) && is_subclass_of($fileClass, Resource::class)) {
                $this->filamentResources[] = $fileClass;

                continue;
            }

            if ($filePath->startsWith($pagesPath) && is_subclass_of($fileClass, Page::class)) {
                $this->filamentPages[] = $fileClass;
            }

            if ($filePath->startsWith($widgetsPath) && is_subclass_of($fileClass, Widget::class)) {
                $this->filamentWidgets[] = $fileClass;
                continue;
            }

            if (is_subclass_of($fileClass, RelationManager::class)) {
                continue;
            }

            if (! is_subclass_of($fileClass, Component::class)) {
                continue;
            }

            $livewireAlias = Str::of($fileClass)
                ->after($namespace . '\\')
                ->replace(['/', '\\'], '.')
                ->prepend('filament.')
                ->explode('.')
                ->map([Str::class, 'kebab'])
                ->implode('.');
            $this->livewireComponents[$livewireAlias] = $fileClass;
        }

        $this->app->resolving('filament', function () {
            Filament::registerPages($this->getPages());
            Filament::registerResources($this->getResources());
            Filament::registerWidgets($this->getWidgets());
        });
        foreach ($this->livewireComponents as $alias => $component) {
            Livewire::component($alias, $component);
        }
    }

    public function getPages()
    {
        return array_merge(
            $this->filamentPages,
            $this->module_config['filament']['pages'] ?? []
        );
    }

    public function getResources()
    {
        return array_merge(
            $this->filamentResources,
            $this->module_config['filament']['resources'] ?? []
        );
    }

    public function getWidgets()
    {
        return array_merge(
            $this->filamentWidgets,
            $this->module_config['filament']['widgets'] ?? []
        );
    }

}
