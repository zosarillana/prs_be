<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeHelper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan make:helper MapPurchaseReport
     */
    protected $signature = 'make:helper {name}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new global helper function';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // Path for the helpers file
        $path = app_path('Helpers/' . $name . '.php');

        if (File::exists($path)) {
            $this->error("Helper {$name} already exists!");
            return;
        }

        // Extract function name (class basename style)
        $functionName = lcfirst($name);

        // Stub (template for the helper)
        $stub = <<<PHP
<?php

if (!function_exists('{$functionName}')) {
    function {$functionName}(...\$args) {
        // TODO: implement helper logic
        return null;
    }
}
PHP;

        // Ensure the folder exists
        File::ensureDirectoryExists(dirname($path));

        // Create the file
        File::put($path, $stub);

        $this->info("Helper {$name} created successfully at {$path}.");
    }
}
