<?php

namespace Diego\LaravelGeneratorCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCrudCommand extends Command
{
    protected $signature = 'generate:crud {model}';
    protected $description = 'Generate CRUD operations for a given model';

    public function handle()
    {
        $model = $this->argument('model');

        $this->info("Searching for migration file for the model: $model");

        $migrationFile = $this->findMigrationForModel($model);
        if (!$migrationFile) {
            $this->error("Migration file for model $model not found.");
            return;
        }

        $modelName = $this->extractModelNameFromMigration($migrationFile);
        if (!$modelName) {
            $this->error("Model name not found in migration file: $migrationFile.");
            return;
        }

        $attributes = $this->extractAttributesFromMigration($migrationFile);
        if (empty($attributes)) {
            $this->error("No attributes found in the migration file for model $model.");
            return;
        }

        $this->generateController($modelName, $attributes);
        $this->generateViews($modelName);
        $this->generateRoutes($modelName);  


        $this->info('CRUD operations generated successfully!');
    }

    /**
     * Busca el archivo de migración correspondiente al modelo.
     *
     * @param string $model
     * @return string|null
     */
    protected function findMigrationForModel($model)
    {
        $migrationPath = database_path('migrations/');
        $modelSnakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $model));
        $modelPluralSnakeCase = Str::plural($modelSnakeCase);

        foreach (File::files($migrationPath) as $file) {
            if (str_contains($file->getFilename(), "create_{$modelPluralSnakeCase}_table")) {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function extractModelNameFromMigration($migrationFile)
    {
        $fileName = basename($migrationFile, '.php');
        if (preg_match('/create_(\w+)_table/', $fileName, $matches)) {
            $tableName = $matches[1];
            return Str::studly(Str::singular($tableName));
        }

        return null;
    }

    protected function extractAttributesFromMigration($migrationFile)
    {
        $fileContent = File::get($migrationFile);
        $attributes = [];

        if (preg_match_all('/\$table->(\w+)\(\'(\w+)\'/', $fileContent, $matches)) {
            foreach ($matches[1] as $index => $type) {
                $name = $matches[2][$index];

                // Excluir campos automáticos de Laravel y otros que no necesiten validación
                if (!in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'])) {
                    $attributes[] = [
                        'type' => $type,
                        'name' => $name
                    ];
                }
            }
        }

        return $attributes;
    }

    protected function generateController($model, $attributes)
    {
        $modelVariable = strtolower($model);
        $modelVariablePlural = Str::plural($modelVariable);
        $modelPlural = Str::plural($model);
    
        if (File::exists(app_path("Http/Controllers/{$model}Controller.php"))) {
            $this->info("Controller {$model}Controller already exists. Skipping creation.");
            return;
        }
    
        $controllerTemplate = file_get_contents(__DIR__ . '/../Templates/CrudController.stub');
        $validationRules = $this->generateValidationRules($attributes);
        // Ahora no pasamos $request, solo los atributos
        $modelAttributes = $this->generateModelAttributes($attributes); 
    
        $controllerTemplate = str_replace([
            '{{ modelName }}',
            '{{ modelVariable }}',
            '{{ modelVariablePlural }}',
            '{{ modelPlural }}',
            '{{ validationRules }}',
            '{{ modelAttributes }}',
        ], [
            $model,
            $modelVariable,
            $modelVariablePlural,
            $modelPlural,
            (string)$validationRules,  // Asegúrate de que esto sea una cadena
            (string)$modelAttributes,  // Asegúrate de que esto sea una cadena
        ], $controllerTemplate);
        
        File::put(app_path("Http/Controllers/{$model}Controller.php"), $controllerTemplate);
        $this->info("Controller {$model}Controller created with custom template.");
    }

    protected function generateValidationRules($attributes)
    {
        $rules = [];
    
        foreach ($attributes as $attribute) {
            // Definir la regla base
            $rule = 'nullable|string|max:255';
    
            // Si el atributo es de tipo email
            if ($attribute['type'] === 'email') {
                $rule = 'nullable|email|max:255';
            }
    
            // Si el atributo es de tipo password, es obligatorio y debe ser más seguro
            if ($attribute['type'] === 'password') {
                $rule = 'nullable|string|min:8|max:255'; // Ajustar según tus necesidades
            }
    
            // Agregar la regla para el atributo específico
            // Se asigna la clave como el nombre del atributo y la regla como el valor
            $rules[$attribute['name']] = $rule;
        }
    
        // Convertir el array a una cadena
        return implode(",\n            ", array_map(function ($key, $value) {
            return "'$key' => '$value'";
        }, array_keys($rules), $rules));
    }

    protected function generateModelAttributes($attributes)
    {
        $modelAttributes = [];
        foreach ($attributes as $attribute) {
            $modelAttributes[] = "'{$attribute['name']}' => \$request->{$attribute['name']}";
        }
    
        // Convertir el array de atributos a una cadena
        return implode(",\n            ", $modelAttributes);
    }

    /**
     * Genera las vistas Vue (Index, Create, Edit) para el modelo dado.
     *
     * @param string $model
     * @return void
     */
    protected function generateViews($model)
    {
        // La carpeta del modelo debe ser en mayúsculas, como el nombre del modelo
        $modelFolder = resource_path("js/Pages/" . $model);  // Sin strtoupper
    
        // Crear la carpeta del modelo si no existe
        if (!File::exists($modelFolder)) {
            File::makeDirectory($modelFolder, 0755, true);
        }
    
        // Plantillas para las vistas
        $indexTemplate = file_get_contents(__DIR__ . '/../Templates/IndexView.stub');
        $createTemplate = file_get_contents(__DIR__ . '/../Templates/CreateView.stub');
        $editTemplate = file_get_contents(__DIR__ . '/../Templates/EditView.stub');
        $showTemplate = file_get_contents(__DIR__ . '/../Templates/ShowView.stub');  // Para la vista Show
    
        // Reemplazar el nombre del modelo en las plantillas
        $indexTemplate = str_replace('{{ model }}', $model, $indexTemplate);
        $createTemplate = str_replace('{{ model }}', $model, $createTemplate);
        $editTemplate = str_replace('{{ model }}', $model, $editTemplate);
        $showTemplate = str_replace('{{ model }}', $model, $showTemplate);  // Reemplazo para Show
    
        // Guardar las vistas en los archivos correspondientes
        File::put($modelFolder . '/Index.vue', $indexTemplate);
        File::put($modelFolder . '/Create.vue', $createTemplate);
        File::put($modelFolder . '/Edit.vue', $editTemplate);
        File::put($modelFolder . '/Show.vue', $showTemplate);  // Guardar la vista Show
    
        $this->info("Views for {$model} generated successfully!");
    }
    

    protected function generateRoutes($model)
    {
        // Obtener el nombre del controlador
        $controllerName = "{$model}Controller";
    
        // Crear la línea de importación para el controlador
        $importStatement = "use App\\Http\\Controllers\\{$controllerName};";
    
        // Obtener la ruta del archivo de rutas
        $routesFile = base_path('routes/web.php');
    
        // Verificar si la importación ya está presente
        $fileContent = file_get_contents($routesFile);
        
        if (strpos($fileContent, $importStatement) === false) {
            // Agregar la importación en la parte superior del archivo web.php (antes de cualquier otra línea)
            $fileContent = preg_replace('/^<\?php/', "<?php\n\n{$importStatement}", $fileContent);
            File::put($routesFile, $fileContent);
            $this->info("Import statement for {$controllerName} added to web.php successfully!");
        } else {
            $this->info("Import statement for {$controllerName} already exists in web.php.");
        }
    
        // Definir la ruta para este modelo en el archivo web.php
        $route = "Route::resource('" . strtolower(Str::plural($model)) . "', {$controllerName}::class);";
    
        // Verificar si la ruta ya está presente para evitar duplicados
        if (strpos($fileContent, $route) === false) {
            // Agregar la ruta al final del archivo
            File::append($routesFile, "\n" . $route);
            $this->info("Route for {$model} added to web.php successfully!");
        } else {
            $this->info("Route for {$model} already exists in web.php.");
        }
    }
    
}
