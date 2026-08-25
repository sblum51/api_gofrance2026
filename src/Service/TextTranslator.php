<?php

namespace App\Service;

use Aws\Translate\TranslateClient;
use Psr\Log\LoggerInterface;

/**
 * Traduction depuis le français via Amazon Translate.
 *
 * Le service n'est jamais exposé tel quel : les contrôleurs ne lui passent que
 * du texte déjà stocké, appartenant à l'utilisateur connecté. Aucun endpoint
 * n'accepte de texte libre, faute de quoi l'API deviendrait un service de
 * traduction gratuit pour n'importe qui.
 */
final class TextTranslator
{
    public function __construct(
        private readonly TranslateClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Traduit un dictionnaire { langue → texte } vers les langues demandées.
     * Les traductions déjà présentes ne sont jamais écrasées : un texte relu à
     * la main ne doit pas être remplacé par une version automatique.
     *
     * @param array<string, string> $textes
     * @param list<string>          $cibles
     *
     * @return array<string, string> le dictionnaire complété
     */
    public function completeTranslations(array $textes, array $cibles, string $source = 'fr'): array
    {
        $texteSource = trim($textes[$source] ?? '');
        if ('' === $texteSource) {
            return $textes;
        }

        foreach ($cibles as $cible) {
            if ($cible === $source || '' !== trim($textes[$cible] ?? '')) {
                continue;
            }

            try {
                $resultat = $this->client->translateText([
                    'SourceLanguageCode' => $source,
                    'TargetLanguageCode' => $cible,
                    'Text' => $texteSource,
                ]);
                $textes[$cible] = (string) $resultat['TranslatedText'];
            } catch (\Throwable $e) {
                // Une langue non prise en charge ne doit pas faire échouer les
                // autres : on la laisse vide, l'application se repliera sur le
                // français.
                $this->logger->warning('Traduction impossible vers {cible} : {message}', [
                    'cible' => $cible,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $textes;
    }
}
