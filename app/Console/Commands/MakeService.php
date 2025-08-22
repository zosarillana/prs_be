<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Examples:
     * php artisan make:service PaymentService
     * php artisan make:service PrService/PrService
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new service class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // Path for the new file
        $path = app_path('Service/' . $name . '.php');

        if (File::exists($path)) {
            $this->error("Service {$name} already exists!");
            return;
        }

        // Namespace handling
        $namespace = 'App\\Service';
        if (str_contains($name, '/')) {
            $namespace .= '\\' . str_replace('/', '\\', dirname($name));
        }

        // Extract just the class name
        $className = class_basename($name);

        // Stub (template for the new service class)
        $stub = <<<PHP
        <?php

        namespace {$namespace};

        class {$className}
        {
            public function __construct()
            {
                //
            }
        }
        PHP;

        // Make sure the folder exists
        File::ensureDirectoryExists(dirname($path));

        // Create the file
        File::put($path, $stub);

        $this->info("Service {$className} created successfully at {$path}.");
    }
}
