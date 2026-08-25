<?php

namespace App\Service;

use Aws\Polly\PollyClient;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * Génération des versions audio via Amazon Polly, déposées sur S3 et servies
 * par le CDN.
 *
 * Le nom du fichier porte une empreinte du texte : « audio/{id}/{langue}-{hash}.mp3 ».
 * Deux conséquences utiles — un texte inchangé n'est jamais resynthétisé (Polly
 * est facturé au caractère), et un texte corrigé produit une nouvelle URL, donc
 * aucun cache CDN ou navigateur à invalider.
 */
final class SpeechSynthesizer
{
    /** Garde-fou : Polly refuse au-delà de 3 000 caractères en synthèse directe. */
    private const int MAX_CHARS = 2900;

    public function __construct(
        private readonly PollyClient $polly,
        private readonly FilesystemOperator $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Chemin attendu pour un texte donné, sans rien générer. Sert à savoir si
     * le fichier existant est encore à jour.
     */
    public function expectedPath(string $prefixe, string $locale, string $texte): ?string
    {
        if (null === PollyVoices::forLocale($locale) || '' === trim($texte)) {
            return null;
        }

        return sprintf('%s/%s-%s.mp3', $prefixe, $locale, substr(sha1(trim($texte)), 0, 12));
    }

    /**
     * Synthétise si nécessaire et renvoie le chemin public, ou null si la
     * langue n'a pas de voix ou si Polly échoue.
     */
    public function synthesize(string $prefixe, string $locale, string $texte): ?string
    {
        $chemin = $this->expectedPath($prefixe, $locale, $texte);
        if (null === $chemin) {
            return null;
        }

        // Déjà généré pour ce texte exact : rien à refacturer.
        if ($this->storage->fileExists($chemin)) {
            return '/audio/'.$chemin;
        }

        $voix = PollyVoices::forLocale($locale);
        $texte = trim($texte);
        if (mb_strlen($texte) > self::MAX_CHARS) {
            $texte = mb_substr($texte, 0, self::MAX_CHARS);
        }

        try {
            $resultat = $this->polly->synthesizeSpeech([
                'Engine' => $voix['engine'],
                'LanguageCode' => $voix['lang'],
                'VoiceId' => $voix['voice'],
                'OutputFormat' => 'mp3',
                'Text' => $texte,
            ]);
            $this->storage->write($chemin, (string) $resultat['AudioStream']);
        } catch (\Throwable $e) {
            $this->logger->warning('Synthèse Polly impossible ({locale}) : {message}', [
                'locale' => $locale,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return '/audio/'.$chemin;
    }
}
