<?php

namespace Platform\FlynkConnector;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FlynkConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Morph-Map
        Relation::morphMap([
            'flynk_container'  => \Platform\FlynkConnector\Models\FlynkContainer::class,
            'flynk_sync_state' => \Platform\FlynkConnector\Models\FlynkSyncState::class,
        ]);

        // Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/flynk-connector.php', 'flynk-connector');

        // Modul registrieren
        if (
            config()->has('flynk-connector.routing') &&
            config()->has('flynk-connector.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'flynk-connector',
                'title'      => 'FLYNK Connector',
                'group'      => 'admin',
                'routing'    => config('flynk-connector.routing'),
                'guard'      => config('flynk-connector.guard'),
                'navigation' => config('flynk-connector.navigation'),
            ]);
        }

        // Routes laden
        if (PlatformCore::getModule('flynk-connector')) {
            ModuleRouter::group('flynk-connector', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/flynk-connector.php' => config_path('flynk-connector.php'),
        ], 'config');

        // Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'flynk-connector');
        $this->registerLivewireComponents();

        // Tools registrieren
        $this->registerTools();

        // EntityLinkProvider registrieren (loose Kopplung mit Organization-Modul)
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\FlynkConnector\Organization\FlynkContainerEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        // Error Reporter
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('flynk-connector', 'Platform\\FlynkConnector');
        } catch (\Throwable $e) {}
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            $registry->register(new \Platform\FlynkConnector\Tools\ListFlynkContainersTool());
            $registry->register(new \Platform\FlynkConnector\Tools\GetFlynkContainerTool());
            $registry->register(new \Platform\FlynkConnector\Tools\CreateFlynkContainerTool());
            $registry->register(new \Platform\FlynkConnector\Tools\LinkFlynkContainerTool());
            $registry->register(new \Platform\FlynkConnector\Tools\PushFlynkContainerTool());
            $registry->register(new \Platform\FlynkConnector\Tools\UnregisterFlynkContainerTool());
            $registry->register(new \Platform\FlynkConnector\Tools\SyncFlynkProjectMetaTool());
        } catch (\Throwable $e) {
            \Log::warning('FlynkConnector: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\FlynkConnector\\Livewire';
        $prefix = 'flynk-connector';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
