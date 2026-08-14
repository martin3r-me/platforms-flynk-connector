<?php

namespace Platform\FlynkConnector\Completeness;

use Platform\FlynkConnector\Enums\FlynkTier;

/**
 * Das FLYNK-Anforderungsprofil (Datenset-Doku BHG) als deklarative Spezifikation.
 *
 * Ein Eintrag pro Ziel-Feld. Aus dieser einen Liste speisen sich drei Dinge:
 *   1. die Completeness-Engine (Füllgrad + Tore),
 *   2. die Cockpit-UI (Checkliste: was fehlt, warum, wo),
 *   3. der Vertrag für die Quell-Provider (welche Roh-Slice sie liefern müssen).
 *
 * Ändert FLYNK sein Profil, wird NUR diese Datei angefasst.
 *
 * Die `probe`-Pfade zeigen in den Roh-Envelope aus FlynkContainerService::assembleContext():
 *   - 'project.*'   → Container-Stammdaten (vom Connector injiziert)
 *   - 'brand.*'     → BrandsFlynkContextProvider   (vorhanden)
 *   - 'seo.*'       → SeoFlynkContextProvider      (vorhanden)
 *   - 'crm.*'       → CrmFlynkContextProvider      (NOCH ZU BAUEN — misst bis dahin als fehlend)
 *   - 'commerce.*'  → CommerceFlynkContextProvider (NOCH ZU BAUEN)
 */
class FlynkDatenset
{
    /** @return FieldSpec[] */
    public function fields(): array
    {
        return [
            // ── Pflicht — ohne das gibt es kein Projekt ──────────────────
            new FieldSpec('name', FlynkTier::REQUIRED, 'Projektname',
                'Ohne Namen kein Projekt — daraus entstehen Domain, Repo und DB.',
                'connector', 'self', 'project.name'),
            new FieldSpec('external_reference', FlynkTier::REQUIRED, 'Container-Referenz',
                'Ohne Referenz keine Verknüpfung und kein Wiederholschutz.',
                'connector', 'self', 'project.external_reference'),

            // ── Pflicht für Veröffentlichung — die Rechtsangaben ─────────
            // Fehlen sie, bleibt das Impressum leer → Seite kann nicht online gehen.
            ...$this->legal(),
            new FieldSpec('primary_contact', FlynkTier::PUBLISHABLE, 'Kontakt-Empfänger',
                'Anfragen gehen an niemanden — das Formular funktioniert, die Nachricht kommt nicht an.',
                'crm', 'customer', 'crm.contact.primary_email', 'email'),

            // ── Entwurf-Mindestset — hiervon hängt ab, ob eine Sektion entsteht ─
            new FieldSpec('description', FlynkTier::DRAFT, 'Beschreibung',
                'Kein Einstieg — Hero und Meta-Beschreibung bleiben leer.',
                'brand', 'self', 'brand.description'),
            new FieldSpec('usp', FlynkTier::DRAFT, 'USP / Kernbotschaften',
                'Hero ohne Aussage.',
                'brand', 'self', 'brand.identity.core_messages', 'list_min:1'),
            new FieldSpec('primary_color', FlynkTier::DRAFT, 'Markenfarbe',
                'Die Seite bleibt grau und zeigt die Marke nicht.',
                'brand', 'self', 'brand.visuals.colors.primary', 'hex'),
            new FieldSpec('services', FlynkTier::DRAFT, 'Leistungen',
                'Keine Leistungen-Sektion (FLYNK baut sie erst ab zwei Einträgen).',
                'commerce', 'customer', 'commerce.services', 'list_min:2'),
            new FieldSpec('target_audience', FlynkTier::DRAFT, 'Zielgruppe',
                'Keine Für-wen-Sektion.',
                'brand', 'self', 'brand.audience.personas', 'list_min:1'),
            new FieldSpec('pain_point', FlynkTier::DRAFT, 'Problem / Pain Point',
                'Keine Problem-Sektion.',
                'brand', 'self', 'brand.audience.personas', 'list_min:1'),

            // ── Ideal — macht die Seite besser, blockt aber nichts ───────
            new FieldSpec('secondary_color', FlynkTier::IDEAL, 'Zweitfarbe',
                'Der zweite Akzent bleibt neutral.',
                'brand', 'self', 'brand.visuals.colors.secondary', 'hex'),
            // neutral_color: bewusst NICHT im Draft-Tor — FLYNK leitet es aus
            // primary_color ab, und Brand liefert heute kein neutral_color.
            new FieldSpec('neutral_color', FlynkTier::IDEAL, 'Neutralton',
                'Graustufen werden aus primary_color abgeleitet (bei warmen Marken schlechter).',
                'brand', 'self', 'brand.visuals.colors.neutral', 'hex'),
            new FieldSpec('positioning', FlynkTier::IDEAL, 'Positionierung',
                'Roter Faden fehlt.',
                'brand', 'self', 'brand.identity.elevator_pitch'),
            new FieldSpec('font_headline', FlynkTier::IDEAL, 'Überschriften-Schrift',
                'FLYNK schlägt eine vor und lässt sie bestätigen.',
                'brand', 'self', fn ($c) => $this->typographyRole($c, ['headline', 'h1', 'display'])),
            new FieldSpec('font_body', FlynkTier::IDEAL, 'Fließtext-Schrift',
                'Fehlt heute im Envelope (nur headline+logo) — FLYNK schlägt eine vor.',
                'brand', 'self', fn ($c) => $this->typographyRole($c, ['body', 'text', 'paragraph'])),
            new FieldSpec('tone_register', FlynkTier::IDEAL, 'Tonalität (Register)',
                'FLYNK wählt es aus Branche und Positionierung.',
                'brand', 'self', 'brand.voice.dimensions', 'list_min:1'),
            new FieldSpec('tone_personality', FlynkTier::IDEAL, 'Tonalität (Stimme)',
                'FLYNK wählt es aus Branche und Positionierung.',
                'brand', 'self', 'brand.voice.dimensions', 'list_min:1'),
            new FieldSpec('tone_avoid', FlynkTier::IDEAL, 'Zu vermeiden',
                'FLYNK weiß nicht, was ihr vermeiden wollt.',
                'brand', 'self', 'brand.voice.donts', 'list_min:1'),
            new FieldSpec('industry', FlynkTier::IDEAL, 'Branche',
                'Fehlt in jeder Ableitung.',
                'crm', 'customer', 'crm.company.industry'),
            new FieldSpec('location', FlynkTier::IDEAL, 'Ort / Region',
                'Keine lokalen Signale.',
                'crm', 'customer', 'crm.company.location'),
            new FieldSpec('language', FlynkTier::IDEAL, 'Sprache',
                'FLYNK nimmt Deutsch an.',
                'seo', 'self', 'seo.language'),
            new FieldSpec('reply_email', FlynkTier::IDEAL, 'Antwort-Adresse',
                'Antworten landen beim technischen Absender (meist ein noreply).',
                'crm', 'customer', 'crm.contact.reply_email', 'email'),
            new FieldSpec('client_name', FlynkTier::IDEAL, 'Kundenanrede',
                'Die Aufbau-Seite spricht den Kunden an Tag 1 nicht an.',
                'crm', 'customer', 'crm.contact.client_name'),

            // Belege / Einwände / Transformation — heute im Ökosystem ohne Quelle.
            new FieldSpec('objections', FlynkTier::IDEAL, 'Einwände / FAQ',
                'Keine Einwände/FAQ-Sektion.',
                '—', '—', 'content.objections', 'list_min:1'),
            new FieldSpec('social_proof', FlynkTier::IDEAL, 'Belege / Social Proof',
                'Keine Belege-Sektion — und Belege tragen das Vertrauen.',
                '—', '—', 'content.social_proof', 'list_min:1'),
            new FieldSpec('transformation', FlynkTier::IDEAL, 'Transformation',
                'Keine Ablauf- oder Ergebnis-Sektion.',
                '—', '—', 'content.transformation'),

            // ── Kanäle & Erreichbarkeit (Ideal) ──────────────────────────
            new FieldSpec('social_instagram', FlynkTier::IDEAL, 'Instagram',
                'Kanal fehlt.', 'crm', 'customer', 'crm.channels.instagram', 'url'),
            new FieldSpec('social_facebook', FlynkTier::IDEAL, 'Facebook',
                'Kanal fehlt.', 'crm', 'customer', 'crm.channels.facebook', 'url'),
            new FieldSpec('social_linkedin', FlynkTier::IDEAL, 'LinkedIn',
                'Kanal fehlt.', 'crm', 'customer', 'crm.channels.linkedin', 'url'),
            new FieldSpec('social_youtube', FlynkTier::IDEAL, 'YouTube',
                'Kanal fehlt.', 'crm', 'customer', 'crm.channels.youtube', 'url'),
            new FieldSpec('social_google_business', FlynkTier::IDEAL, 'Google Business',
                'Ohne den langen Maps-Link bleibt die Standort-Auswertung (Rankings, Bewertungen) leer.',
                'crm', 'customer', 'crm.channels.google_business', 'maps_link'),
            new FieldSpec('opening_hours', FlynkTier::IDEAL, 'Öffnungszeiten',
                'Keine Öffnungszeiten-Angabe.',
                'crm', 'customer', 'crm.channels.opening_hours', 'map_non_empty'),
        ];
    }

    /** Die dreizehn Impressums-Pflichtfelder (Datenset-Doku §2). */
    private function legal(): array
    {
        // Factory: crm.legal.<feld> aus dem legal_-Präfix ableiten, gleicher Folgetext.
        $legal = fn (string $key, string $label) => new FieldSpec(
            $key, FlynkTier::PUBLISHABLE, $label,
            'Impressum unvollständig — die Seite kann nicht online gehen.',
            'crm', 'customer', 'crm.legal.'.substr($key, strlen('legal_')),
        );

        return [
            $legal('legal_company', 'Firma'),
            $legal('legal_representative', 'Vertretungsberechtigt'),
            $legal('legal_representative_role', 'Funktion des Vertreters'),
            $legal('legal_address', 'Straße / Hausnummer'),
            $legal('legal_postal_code', 'PLZ'),
            $legal('legal_city', 'Stadt'),
            $legal('legal_country', 'Land (Code)'),
            $legal('legal_country_name', 'Land (Name)'),
            new FieldSpec('legal_phone', FlynkTier::PUBLISHABLE, 'Telefon',
                'Impressum unvollständig — die Seite kann nicht online gehen.',
                'crm', 'customer', 'crm.legal.phone'),
            new FieldSpec('legal_email', FlynkTier::PUBLISHABLE, 'E-Mail',
                'Impressum unvollständig — die Seite kann nicht online gehen.',
                'crm', 'customer', 'crm.legal.email', 'email'),
            $legal('legal_register_court', 'Registergericht'),
            $legal('legal_registration', 'Handelsregisternummer'),
            $legal('legal_vat_id', 'USt-IdNr.'),
        ];
    }

    /** Erste Typografie-Rolle aus einer Kandidatenliste (headline/body …), oder null. */
    private function typographyRole(array $context, array $roles): mixed
    {
        foreach ((array) data_get($context, 'brand.visuals.typography', []) as $entry) {
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            if ($role !== null && in_array($role, $roles, true)) {
                return $entry;
            }
        }

        return null;
    }
}
