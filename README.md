# LaraModular 
A library to enable modular design into your Laravel application,
It's also integerate seamlessly with <a href="http://filamentphp.com/">Filament</a> for the Admin panel

## Requirements
- Laravel >= 5.5
- PHP >= 8.0

## Installation Instructions
```
composer require lordjoo/laramodular
```
> The package will register itself.

## Usage 
- To generate a module just use this command 
```
php artisan make:module <module_name>
```  

- To Create Filament Resource use 
```
php artisan make:modular-resource <resource_name>
```
We will try to detect which module you want the resource in and you can change it as well 


## Contributing
Please feel free to contribute to this project, as I want to add helper functions for the following   

- [ ] getCurrentModulePath()
- [ ] getCurrentModuleName()
- [ ] getCurrentModuleConfig()
- [ ] activateModule($module_name)
- [ ] deactivateModule($module_name)


