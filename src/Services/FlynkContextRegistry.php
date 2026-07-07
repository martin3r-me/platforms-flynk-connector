<?php

namespace Platform\FlynkConnector\Services;

use Platform\FlynkConnector\Contracts\ProvidesFlynkContext;

/**
 * Registry der Kontext-Lieferanten (Ports & Adapters). Quell-Module registrieren
 * hier ihren Provider; der Connector iteriert sie beim Zusammenbauen des Push-Kontexts.
 */
class FlynkContextRegistry
{
    /** @var ProvidesFlynkContext[] */
    private array $providers = [];

    public function register(ProvidesFlynkContext $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return ProvidesFlynkContext[] */
    public function all(): array
    {
        return $this->providers;
    }
}
