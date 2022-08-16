# LaraModular 
Is a simple and lightweight package to create modular Laravel applications,
basically it's a modules scaffold generator with a simple and lightweigh service provider to register the modules.

## Requirements
- Laravel >= 5.5
- PHP >= 8.0

## Installation Instructions
```
composer require lordjoo/laramodular
```
> The package will register itself.

## Usage 
To generate a module just use this command 
```
php artisan new:module <module_name>
```

## Contributing
Please feel free to contribute to this project, as I want to add helper functions for the following   

- [ ] getCurrentModulePath()
- [ ] getCurrentModuleName()
- [ ] getCurrentModuleConfig()
- [ ] activateModule($module_name)
- [ ] deactivateModule($module_name)


