<?php

namespace App\Service;

/**
 * Correspondance langue → voix Polly.
 *
 * Toutes les langues du back-office n'ont pas de voix : Polly ne propose ni
 * breton, ni occitan, ni corse, ni basque. Pour celles-là, l'application
 * publique se rabat sur la synthèse du navigateur — moins bonne, mais
 * existante. D'où un null explicite plutôt qu'une voix approchante.
 */
final class PollyVoices
{
    /** @var array<string, array{voice: string, engine: string, lang: string}> */
    private const array VOICES = [
        'fr' => ['voice' => 'Lea',    'engine' => 'neural', 'lang' => 'fr-FR'],
        'en' => ['voice' => 'Amy',    'engine' => 'neural', 'lang' => 'en-GB'],
        'de' => ['voice' => 'Vicki',  'engine' => 'neural', 'lang' => 'de-DE'],
        'es' => ['voice' => 'Lucia',  'engine' => 'neural', 'lang' => 'es-ES'],
        'it' => ['voice' => 'Bianca', 'engine' => 'neural', 'lang' => 'it-IT'],
        'nl' => ['voice' => 'Laura',  'engine' => 'neural', 'lang' => 'nl-NL'],
        'pt' => ['voice' => 'Ines',   'engine' => 'neural', 'lang' => 'pt-PT'],
        'ca' => ['voice' => 'Arlet',  'engine' => 'neural', 'lang' => 'ca-ES'],
    ];

    /** @return array{voice: string, engine: string, lang: string}|null */
    public static function forLocale(string $locale): ?array
    {
        return self::VOICES[strtolower($locale)] ?? null;
    }

    /** @return list<string> */
    public static function supportedLocales(): array
    {
        return array_keys(self::VOICES);
    }
}
