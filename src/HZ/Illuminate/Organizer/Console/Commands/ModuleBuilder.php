<?php
namespace HZ\Illuminate\Organizer\Console\Commands;

use File;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use HZ\Illuminate\Organizer\Helpers\Mongez;
use HZ\Illuminate\Organizer\Traits\Console\EngezTrait;

class ModuleBuilder extends Command
{
    use EngezTrait;
    /**
     * Controller types
     * 
     * @const array
     */
    const CONTROLLER_TYPES = ['site', 'admin', 'all'];

    /**
     * Module directory path
     * 
     * @var string
     */
    protected $root;

    /**
     * The module name
     * 
     * @var string
     */
    protected $module;

    /**
     * The module name in studly case
     * 
     * @var string
     */
    protected $moduleName;

    /**
     * Current database name
     * 
     * @var string
     */
    protected $databaseName;

    /**
     * Module info
     */
    protected $info = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module 
                                       {moduleName}
                                       {--controller=}
                                       {--type=all}
                                       {--model=}
                                       {--data=}
                                       {--uploads=}
                                       {--resource=}
                                       {--repository=}
                                       {--path=}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Module builder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->module = $this->argument('moduleName');

        $this->moduleName = Str::studly($this->module);
        
        $this->info['moduleName'] = $this->moduleName;

        $this->adjustOptionsValues();
    }

    /**
     * Adjust sent options and update its value if its default
     * 
     * @return void
     */
    protected function adjustOptionsValues()
    {
        $this->init();
        $this->create();
    }

    /**
     * Init data
     * 
     * @return void
     */
    protected function init()
    {
        $this->info('Preparing data...');
        $this->initController();
        $this->initModel();
        $this->initResource();
        $this->initRepository();
        $this->initData();
    }

    /**
     * Create files
     * 
     * @return void
     */
    protected function create()
    {
        $this->setModuleToFile();
        
        $this->info('Creating controller file');
        $this->createController();

        $this->info('Creating resource file');
        $this->createResource();

        $this->info('Creating model file');
        $this->createModel();

        $this->info('Creating repository file');
        $this->createRepository();

        $this->info('Creating database files');
        $this->createDatabase();

        $this->info('Generating routes files');
        $this->createRoutes();

        $this->info('Module has been created successfully');
        
        $this->info('Updating configurations.');
        $this->updateConfig();
    }

    /**
     * Update configurations
     *
     * @return void
     */
    protected function updateConfig(): void 
    {
        if (! isset($this->info['repositoryName'])) return;

        $config = File::get($organizerPath =  base_path('config/organizer.php'));

        $replacementLine = '// Auto generated repositories here: DO NOT remove this line.';
        
        if (! Str::contains($config, $replacementLine)) return;

        $repositoryClassName = basename(str_replace('\\', '/', $this->info['repository']));

        $replacedString = "'{$this->info['repositoryName']}' => App\\Modules\\$repositoryClassName\\Repositories\\{$repositoryClassName}Repository::class,\n \t\t $replacementLine";

        $updatedConfig =str_replace($replacementLine, $replacedString, $config);

        File::put($organizerPath, $updatedConfig);
    }

    /**
     * Generate routes files
     * 
     * @return void
     */
    protected function createRoutes()
    {
        $type = $this->option('type');

        // create routes directory
        $content = File::get($this->path("Controllers/Site/controller.php"));

        $routesDirectory = $this->modulePath("routes");

        $this->checkDirectory($routesDirectory);

        // get the content of the api routes file
        $apiRoutesFileContent = File::get(base_path('routes/api.php'));

        $controller = $this->info['controller'];

        $controllerName = basename(str_replace('\\', '/', $controller));

        if (in_array($type, ['all', 'site'])) {
            // generate the site routes file
            $content = File::get($this->path("routes/site.php"));
    
            // replace controller name
            $content = str_ireplace("ControllerName", "{$controllerName}Controller", $content);

            // replace module name
            $content = str_ireplace("ModuleName", "{$this->moduleName}", $content);

            // replace route prefix
            $content = str_ireplace("route-prefix", "{$this->module}", $content);

            // create the route file
            $filePath = $routesDirectory . '/site.php';

            $this->createFile($filePath, $content, 'Site routes');

            // add the routes file to the api routes file content
            if (Str::contains($apiRoutesFileContent, '// end of site routes')) {
                $apiRoutesFileContent = str_replace('// end of site routes', 
"// {$this->moduleName} module
include base_path('app/Modules/{$this->moduleName}/routes/site.php');

// end of site routes", $apiRoutesFileContent);
            }
        }

        if (in_array($type, ['all', 'admin'])) {
            // generate the admin routes file
            $content = File::get($this->path("routes/admin.php"));

            // replace controller name
            $content = str_ireplace("ControllerName", "{$controllerName}Controller", $content);

            // replace module name
            $content = str_ireplace("ModuleName", "{$this->moduleName}", $content);

            // replace route prefix
            $content = str_ireplace("route-prefix", "{$this->module}", $content);

            // create the route file
            $filePath = $routesDirectory . '/admin.php';

            $this->createFile($filePath, $content, 'Admin routes');
            // add the routes file to the api routes file content
            if (Str::contains($apiRoutesFileContent, '// end of admin routes')) {
                $apiRoutesFileContent = str_replace('// end of admin routes', 
"// {$this->moduleName} module
    include base_path('app/Modules/{$this->moduleName}/routes/admin.php');

    // end of admin routes", $apiRoutesFileContent);
            }
        }

        // echo($apiRoutesFileContent);

        File::put(base_path('routes/api.php'), $apiRoutesFileContent);
    }

    /**
     * Some sections like repository and resource has the DATA constant
     * If the developer passed a list of data separated by comma it will be set there
     * 
     * @return void
     */
    protected function initData()
    {
        foreach (['data', 'uploads'] as $option) {
            $value = $this->option($option);
            if ($value) {
                $this->info[$option] = explode(',', $value);
            }
        }
    }

    /**
     * Handle controller file
     * 
     * @return void
     */
    protected function initController()
    {
        $this->setData('controller');

        $controller = $this->info['controller'];
        
        $controllerPath = $this->option('path'); // the parent directory inside the Api directory

        if ($controllerPath) {
            $controller = "$controllerPath\\$controller";
        }

        $controllerType = $this->option('type');

        if (!in_array($controllerType, static::CONTROLLER_TYPES)) {
            throw new Exception(sprintf('Unknown controller type %s, available types: %s', $controllerType, implode(',', static::CONTROLLER_TYPES)));
        }

        $this->info['controller'] = $controller;
    }

    /**
     * Create controller file
     * 
     * @return void
     */
    protected function createController()
    {
        Artisan::call('engez:controller',[
            'controller' => $this->info['controller'],
            '--module' => $this->moduleName,
            '--repository' => $this->info['repositoryName'],  
            'type' => $this->option('type'), 
        ]);
    }

    /**
     * Create the resource file
     * 
     * @return void
     */
    protected function createResource()
    {    
        Artisan::call('engez:resource', [
            'resource' => $this->info['resource'],
            '--module' => $this->moduleName,
            '--data'   => $this->option('data'),
        ]); 
    }
    
    /** 
     * Create the database file
     * 
     * @return void
     */
    protected function createDatabase()
    {
        $databaseFileName = strtolower(str::plural($this->moduleName));
        $path = $this->modulePath("database/migrations");
        $this->checkDirectory($path);        
        
        $databaseDriver = config('database.default');     
        
        if ($databaseDriver == 'mongodb') {
            $this->createSchema($databaseFileName, $path);
        }
        
        $this->createMigration($databaseFileName, $databaseDriver);
    }

    /**
     * Create migration file of table in mysql 
     *
     * @param string $dataFileName
     * @return void 
     */
    protected function createMigration($databaseFileName, $databaseDriver)
    {
        $path = 'app/modules/'.$this->moduleName.'/database/migrations';
        $content = File::get($this->path("Migrations/".$databaseDriver."-migration.php"));
        $content = str_ireplace("TableName", "{$databaseFileName}", $content);

        if (isset($this->info['data'])) {
            $schema = '';
            foreach ($this->info['data'] as $data){
                $schema .= "\n\n\$table->string('$data');\n\n";
            }
            $content = str_ireplace("Table-Schema", $schema, $content);
        }
                
        $databaseFileName = date('Y_m_d_His').'_'.$databaseFileName;
        $this->createFile("$path/{$databaseFileName}.php",$content, 'Migration');
    }

    /**
     * Create schema of table in mongo 
     *
     * @param string $dataFileName
     * @return void 
     */
    protected function createSchema($databaseFileName, $path)
    {
        $defaultContent = [
            '_id' => "objectId",
            'id'=>'int', 
        ];

        $customData = $this->info['data'] ?? [];

        unset($customData['id'], $customData['_id']);

        $customData = array_fill_keys($customData, 'string');

        $content = array_merge($defaultContent, $customData);
        

        $this->createFile("$path/{$databaseFileName}.json", json_encode($content, JSON_PRETTY_PRINT), 'Schema');
    }

    /**
     * Create the repository file
     * 
     * @return void
     */
    protected function createRepository()
    {
        Artisan::call('engez:repository',[
            'repository' => $this->info['repositoryName'],
            '--module' => $this->moduleName,
        ]);
    }

    /**
     * Create the model file
     * 
     * @return void
     */
    protected function createModel()
    {
        $model = $this->info['model'];

        $modelName = basename(str_replace('\\', '/', $model));

        // make it singular 
        $modelName = Str::singular($modelName);

        $this->info['modelName'] = $modelName;

        Artisan::call('engez:model',[
            'model' => $this->info['model'],
            '--module' => $this->moduleName,   
        ]);
    }

    /**
     * Create module model
     * 
     * @return void
     */
    protected function initModel()
    {
        $this->setData('model');
    }

    /**
     * Create module resource
     * 
     * @return void
     */
    protected function initResource()
    {
        $this->setData('resource');
    }

    /**
     * Create module repository
     * 
     * @return void
     */
    protected function initRepository()
    {
        $this->setData('repository');

        $this->info['repositoryName'] = Str::camel(basename(str_replace('\\', '/', $this->info['repository'])));
    }

    /**
     * Set to the data container the value of the given option
     *
     * @param  string $option
     * @return void
     */
    protected function setData($option)
    {
        // repository
        $optionValue = $this->option($option);

        $module = ucfirst($this->module);

        if (!$optionValue) {
            // get it from the module name
            $optionValue = "{$module}\\{$module}";
        }
    
        $this->info[$option] = Str::studly(str_replace('/', '\\', $optionValue));
    }

    /**
     * Set module name to config file.
     * 
     * @return void
     */
    protected function setModuleToFile() 
    {
        $content = Mongez::getStored('modules');
        $content [] = $this->moduleName;
        Mongez::setStored('modules', $content);
        Mongez::updateStorageFile();
    }
}