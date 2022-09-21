<?php

namespace Lordjoo\Laramodular;

use Filament\Support\Commands\Concerns;
use Filament\Commands\MakeResourceCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

class MakeFilamentResourceCommand extends MakeResourceCommand
{
    use \Filament\Commands\Concerns\CanGenerateResources;
    use Concerns\CanIndentStrings;
    use Concerns\CanManipulateFiles;
    use Concerns\CanValidateInput;

    protected $description = 'Creates a Filament resource class and default page classes.';

    protected $signature = 'make:modular-resource  {name?} {--module=} {--soft-deletes} {--view} {--G|generate} {--S|simple} {--F|force}';

    public string $editResourcePageClass;
    public string $manageResourcePageClass;
    public string $createResourcePageClass;
    public string $listResourcePageClass;
    public string $viewResourcePageClass;
    public string $namespace;
    public string $module_name;


    public function handle(): int
    {

        $model = $this->getModel();

        $modelClass = (string) Str::of($model)->afterLast('\\');

        $modelNamespace = $this->getModelNamespace($model);

        $pluralModelClass = (string) Str::of($modelClass)->pluralStudly();

        $this->module_name = Str::of($this->option('module') ?? $this->ask('Module Name',$modelClass))
            ->ucfirst()
            ->camel()
            ->studly();
        // check if module exists
        if (!is_dir(config('laramodular.modules_path') . DIRECTORY_SEPARATOR . $this->module_name)) {
            $this->components->warn("Module {$this->module_name} does not exists!");
            $this->call('make:module', ['name' => $this->module_name]);
        }

        $resource = "{$model}Resource";
        $resourceClass = "{$modelClass}Resource";
        $resourceNamespace = $modelNamespace;

        $this->listResourcePageClass = "List{$pluralModelClass}";
        $this->manageResourcePageClass = "Manage{$pluralModelClass}";
        $this->createResourcePageClass = "Create{$modelClass}";
        $this->editResourcePageClass = "Edit{$modelClass}";
        $this->viewResourcePageClass = "View{$modelClass}";

        $baseResourcePath = $this->getBaseResourcePath($resource);

        $this->namespace = $this->getNamespace($resource);

//        dd($this->namespace);

        $resourcePath = "{$baseResourcePath}.php";
        $resourcePagesDirectory = "{$baseResourcePath}/Pages";
        $listResourcePagePath = "{$resourcePagesDirectory}/{$this->listResourcePageClass}.php";
        $manageResourcePagePath = "{$resourcePagesDirectory}/{$this->manageResourcePageClass}.php";
        $createResourcePagePath = "{$resourcePagesDirectory}/{$this->createResourcePageClass}.php";
        $editResourcePagePath = "{$resourcePagesDirectory}/{$this->editResourcePageClass}.php";
        $viewResourcePagePath = "{$resourcePagesDirectory}/{$this->viewResourcePageClass}.php";

        if (! $this->option('force') && $this->checkForCollision([
                $resourcePath,
                $listResourcePagePath,
                $manageResourcePagePath,
                $createResourcePagePath,
                $editResourcePagePath,
                $viewResourcePagePath,
            ])) {
            return static::INVALID;
        }

        $pages = $this->pagesCode();

        $tableActions = $this->tableActionsCode();

        $relations = $this->relationsCode();

        $tableBulkActions = $this->tableBulkActionsCode();

        $eloquentQuery = $this->eloquentQueryCode();


        $this->copyStubToApp('Resource', $resourcePath, [
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'formSchema' => $this->option('generate') ? $this->getResourceFormSchema(
                ($modelNamespace !== '' ? $modelNamespace : 'App\Models') . '\\' . $modelClass,
            ) : $this->indentString('//', 4),
            'model' => $model === 'Resource' ? 'Resource as ResourceModel' : $model,
            'modelClass' => $model === 'Resource' ? 'ResourceModel' : $modelClass,
            'namespace' => $this->namespace . ($resourceNamespace !== '' ? "\\{$resourceNamespace}" : ''),
            'pages' => $this->indentString($pages, 3),
            'relations' => $this->indentString($relations, 1),
            'resource' => $resource,
            'resourceClass' => $resourceClass,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 4),
            'tableColumns' => $this->option('generate') ? $this->getResourceTableColumns(
                ($modelNamespace !== '' ? $modelNamespace : 'App\Models') . '\\' . $modelClass
            ) : $this->indentString('//', 4),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make(),' : '//',
                4,
            ),
        ]);

        if ($this->option('simple')) {
            $this->copyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'namespace' => "{$this->namespace}\\{$resource}\\Pages",
                'resourceNamespace' => "{$this->namespace}",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $this->manageResourcePageClass,
            ]);
        } else {
            $this->copyStubToApp('ResourceListPage', $listResourcePagePath, [
                'namespace' => "{$this->namespace}\\{$resource}\\Pages",
                'resourceNamespace' => "{$this->namespace}",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $this->listResourcePageClass,
            ]);

            $this->copyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\CreateRecord',
                'baseResourcePageClass' => 'CreateRecord',
                'namespace' => "{$this->namespace}\\{$resource}\\Pages",
                'resourceNamespace' => "{$this->namespace}",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $this->createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view')) {
                $this->copyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'namespace' => "{$this->namespace}\\{$resource}\\Pages",
                    'resourceNamespace' => "{$this->namespace}",
                    'resource' => $resource,
                    'resourceClass' => $resourceClass,
                    'resourcePageClass' => $this->viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->copyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'actions' => $this->indentString($editPageActions, 3),
                'namespace' => "{$this->namespace}\\{$resource}\\Pages",
                'resourceNamespace' => "{$this->namespace}",
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $this->editResourcePageClass,
            ]);
        }

        $this->info("Successfully created {$resource}!");

        return static::SUCCESS;
    }

    public function getModel()
    {
        $model = (string) Str::of($this->argument('name') ?? $this->askRequired('Model (e.g. `BlogPost`)', 'name'))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\');


        return !blank($model) ? $model : 'Resource';
    }

    public function getModelNamespace($model)
    {
        return Str::of($model)->contains('\\') ?
            (string) Str::of($model)->beforeLast('\\') :
            '';
    }

    public function getBaseResourcePath($resource)
    {
        $module =  config('laramodular.modules_path').'\\'.$this->module_name;
        return base_path(
            (string) Str::of($resource)
                ->prepend($module.'\\Filament\\Resources\\')
                ->replace('\\', '/'),
        );
    }

    public function pagesCode()
    {
        $pages  = '';
        $pages .= '\'index\' => Pages\\' . ($this->option('simple') ? $this->manageResourcePageClass : $this->listResourcePageClass) . '::route(\'/\'),';

        if (! $this->option('simple')) {
            $pages .= PHP_EOL . "'create' => Pages\\{$this->createResourcePageClass}::route('/create'),";

            if ($this->option('view')) {
                $pages .= PHP_EOL . "'view' => Pages\\{$this->viewResourcePageClass}::route('/{record}'),";
            }

            $pages .= PHP_EOL . "'edit' => Pages\\{$this->editResourcePageClass}::route('/{record}/edit'),";
        }
        return $pages;
    }

    public function tableActionsCode()
    {
        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        if ($this->option('simple')) {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        }
        return implode(PHP_EOL, $tableActions);
    }

    public function tableBulkActionsCode()
    {
        $tableBulkActions = [];

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        if ($this->option('soft-deletes')) {
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
        }
        return implode(PHP_EOL, $tableBulkActions);
    }

    public function relationsCode()
    {
        $relations = '';

        if (!$this->option('simple')) {
            $relations .= PHP_EOL . 'public static function getRelations(): array';
            $relations .= PHP_EOL . '{';
            $relations .= PHP_EOL . '    return [';
            $relations .= PHP_EOL . '        //';
            $relations .= PHP_EOL . '    ];';
            $relations .= PHP_EOL . '}' . PHP_EOL;
        }
        return $relations;
    }

    public function eloquentQueryCode()
    {
        $eloquentQuery = '';
        if ($this->option('soft-deletes')) {
            $eloquentQuery .= PHP_EOL . PHP_EOL . 'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL . '{';
            $eloquentQuery .= PHP_EOL . '    return parent::getEloquentQuery()';
            $eloquentQuery .= PHP_EOL . '        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL . '            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL . '        ]);';
            $eloquentQuery .= PHP_EOL . '}';
        }
        return $eloquentQuery;
    }

    public function getNamespace()
    {
        $module =  config('laramodular.modules_namespace').'\\'.$this->module_name;
        return (string) Str::of($module)
            ->append('\\Filament\\Resources')
            ->replace('/', '\\');
    }

    protected function getDefaultStubPath(): string
    {
        $src_dir = __DIR__;

        $this_path = (string) Str::of($src_dir)
            ->append('/filament/stubs');
        if (file_exists($this_path)) {
            return $this_path;
        }
        return parent::getDefaultStubPath();
    }
}
