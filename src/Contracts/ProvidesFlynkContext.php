<?php

namespace Platform\FlynkConnector\Contracts;

use Platform\Organization\Models\OrganizationEntity;

/**
 * Port für Kontext-Lieferanten. Jedes Quell-Modul (Brands zuerst, später
 * Recruiting, Events, …) implementiert dieses Interface und registriert sich
 * in der FlynkContextRegistry. Der Connector sammelt beim Push pro Knoten den
 * Kontext aller Lieferanten ein — die Module rufen FLYNK nie selbst.
 */
interface ProvidesFlynkContext
{
    /** Schlüssel im context-Block des Push-Envelopes (z.B. 'brand'). */
    public function contextKey(): string;

    /**
     * Liefert den Kontext-Ausschnitt für einen Organisations-Knoten,
     * oder null/[] wenn für diesen Knoten nichts vorliegt.
     */
    public function contextForEntity(OrganizationEntity $node): ?array;
}
