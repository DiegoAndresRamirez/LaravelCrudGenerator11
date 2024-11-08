<?php

namespace Diego\LaravelGeneratorCrud\Providers;

use Illuminate\Support\ServiceProvider;
use Diego\LaravelGeneratorCrud\Commands\GenerateCrudCommand;

class LaravelGeneratorCrudServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios del paquete.
     *
     * @return void
     */
    public function register()
    {
        // Registrar el comando aquí
        $this->commands([
            GenerateCrudCommand::class, // Asegúrate de que el comando esté en el namespace correcto
        ]);
    }

    /**
     * Ejecuta el paquete durante el arranque de la aplicación.
     *
     * @return void
     */
    public function boot()
    {
        // Aquí puedes registrar otros recursos (vistas, rutas, etc.)
    }
}
