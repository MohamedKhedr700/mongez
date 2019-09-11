<?php
namespace HZ\Illuminate\Organizer\Console\Commands;

use File;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use HZ\Illuminate\Organizer\Helpers\Mongez;
use HZ\Illuminate\Organizer\Traits\Console\EngezTrait;
use HZ\Illuminate\Organizer\Contracts\Console\EngezInterface;

class EngezModel extends Command implements EngezInterface
{
    use EngezTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engez:model {model} {--module=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make new model to specific module';

    /**
     * The database name 
     * 
     * @var string
     */
    protected $databaseName;
    
    /**
     * The module name
     *
     * @var array
     */
    protected $availableModules = [];
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->init();
        $this->validateArguments();   
        $this->create();
        $this->info('Model created successfully');
    }
    
    /**
     * Validate The module name
     *
     * @return void
     */
    public function validateArguments()
    {
        $availableModules = Mongez::getStored('modules');
        
        if (! $this->option('module')) {
            return $this->info('the module name is required');
        }

        if (! in_array($this->info['moduleName'], $availableModules)) {
            return $this->info('This module does not exits');
        }
    }

    /**
     * Set controller info
     * 
     * @return void
     */
    public function init()
    {
        $this->root = Mongez::packagePath();

        $this->databaseName = config('database.default');

        $this->info['modelName'] = Str::studly($this->argument('model'));
        $this->info['moduleName'] = Str::studly($this->option('module'));    
    }
    
    /**
     * Create Model 
     *
     * @return void
     */
    public function create()
    {
        $model = $this->info['modelName'];

        $modelName = basename(str_replace('\\', '/', $model));

        // make it singular 
        
        $modelName = Str::singular($modelName);

        $this->info['modelName'] = $modelName;
        
        $modelPath = dirname($model);

        $modelPath = array_map(function ($segment) {
            return Str::singular($segment);
        }, explode('\\', $modelPath));

        $modelPath = implode('\\', $modelPath);

        $content = File::get($this->path("Models/model.php"));

        // replace model name
        $content = str_ireplace("ModelName", "{$modelName}", $content);

        // replace database name 
        $content = str_replace('DatabaseName', $this->databaseName, $content);

        // replace module name
        $content = str_ireplace("ModuleName", $this->info['moduleName'], $content);
        
        $modelDirectory = $this->modulePath("Models/");

        $this->checkDirectory($modelDirectory);

        $this->info['modelPath'] = $modelPath . '\\' . $modelName;
        // create the file
        $this->createFile("$modelDirectory/{$modelName}.php", $content, 'Model');

    }    
}
