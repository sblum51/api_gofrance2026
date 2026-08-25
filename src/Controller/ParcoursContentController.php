<?php

namespace App\Controller;

use App\Entity\Parcours;
use App\Service\PollyVoices;
use App\Service\SpeechSynthesizer;
use App\Service\TextTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Traduction et synthèse vocale des contenus d'un parcours.
 *
 * VERROUILLAGE — ces routes ne doivent jamais devenir un service de traduction
 * ou de synthèse gratuit pour des tiers. Trois barrières, dans cet ordre
 * d'importance :
 *
 *  1. AUCUN TEXTE LIBRE N'EST ACCEPTÉ. Le corps de la requête ne contient que
 *     des codes de langue. Le texte source est lu dans le parcours en base.
 *     C'est la protection décisive : même avec un compte valide, on ne peut
 *     faire traduire que ce qu'on a soi-même écrit et enregistré.
 *  2. Propriété vérifiée : le voter PARCOURS_EDIT s'assure que le parcours
 *     appartient bien à l'organisation de l'utilisateur connecté.
 *  3. Limitation de débit par utilisateur, contre l'abus par un client
 *     légitime (boucle, script) autant que contre le coût AWS.
 */
final class ParcoursContentController
{
    /** Plafond de sécurité : un parcours anormalement long trahit un détournement. */
    private const int MAX_CHARS = 20000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RateLimiterFactoryInterface $contentGenerationLimiter,
    ) {
    }

    #[Route('/api/parcours/{id}/translate', name: 'parcours_translate', methods: ['POST'])]
    #[IsGranted('PARCOURS_EDIT', 'parcours')]
    public function translate(Parcours $parcours, Request $request, TextTranslator $translator): JsonResponse
    {
        $cibles = $this->readLocales($request);
        $this->assertQuota($parcours);

        $parcours->setDescription($translator->completeTranslations($parcours->getDescription(), $cibles));
        $parcours->setDepartDescription(
            $translator->completeTranslations($parcours->getDepartDescription(), $cibles),
        );

        foreach ($parcours->getPointEntities() as $point) {
            $point->setName($translator->completeTranslations($point->getName(), $cibles));
            $point->setDescription($translator->completeTranslations($point->getDescription(), $cibles));
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'locales' => $cibles,
            'message' => 'Traduction terminée. Relisez les textes : une traduction automatique reste une première version.',
        ]);
    }

    #[Route('/api/parcours/{id}/audio', name: 'parcours_audio', methods: ['POST'])]
    #[IsGranted('PARCOURS_EDIT', 'parcours')]
    public function audio(Parcours $parcours, Request $request, SpeechSynthesizer $synthesizer): JsonResponse
    {
        $cibles = $this->readLocales($request);
        $this->assertQuota($parcours);

        $generes = 0;
        $ignores = [];

        $descriptions = $parcours->getDescription();
        $audios = $parcours->getDescriptionAudio();
        foreach ($cibles as $locale) {
            if (null === PollyVoices::forLocale($locale)) {
                $ignores[$locale] = true;
                continue;
            }
            $chemin = $synthesizer->synthesize('parcours-'.$parcours->getId(), $locale, $descriptions[$locale] ?? '');
            if (null !== $chemin && ($audios[$locale] ?? null) !== $chemin) {
                $audios[$locale] = $chemin;
                ++$generes;
            }
        }
        $parcours->setDescriptionAudio($audios);

        foreach ($parcours->getPointEntities() as $point) {
            $textes = $point->getDescription();
            $noms = $point->getName();
            $audiosPoint = $point->getAudio();
            foreach ($cibles as $locale) {
                if (null === PollyVoices::forLocale($locale)) {
                    continue;
                }
                // Nom et description lus d'un seul tenant : c'est ce que le
                // visiteur entend, et ça évite deux fichiers pour un seul point.
                $texte = trim(($noms[$locale] ?? '').'. '.($textes[$locale] ?? ''), ". \t\n");
                $chemin = $synthesizer->synthesize('point-'.$point->getId(), $locale, $texte);
                if (null !== $chemin && ($audiosPoint[$locale] ?? null) !== $chemin) {
                    $audiosPoint[$locale] = $chemin;
                    ++$generes;
                }
            }
            $point->setAudio($audiosPoint);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'generated' => $generes,
            'unsupported' => array_keys($ignores),
            'supportedLocales' => PollyVoices::supportedLocales(),
        ]);
    }

    /**
     * Le corps ne porte que des codes de langue — jamais de texte. Les codes
     * sont filtrés pour ne laisser passer que des identifiants plausibles.
     *
     * @return list<string>
     */
    private function readLocales(Request $request): array
    {
        $this->assertRateLimit();

        $data = json_decode($request->getContent() ?: '{}', true);
        $locales = is_array($data['locales'] ?? null) ? $data['locales'] : [];

        $locales = array_values(array_unique(array_filter(
            array_map(static fn ($l): string => is_string($l) ? strtolower(trim($l)) : '', $locales),
            static fn (string $l): bool => 1 === preg_match('/^[a-z]{2,3}$/', $l) && 'fr' !== $l,
        )));

        if (!$locales) {
            throw new BadRequestHttpException('Indiquez au moins une langue cible, autre que le français.');
        }
        if (count($locales) > 12) {
            throw new BadRequestHttpException('Douze langues au maximum par appel.');
        }

        return $locales;
    }

    private function assertRateLimit(): void
    {
        $utilisateur = $this->security->getUser();
        if (null === $utilisateur) {
            throw new AccessDeniedHttpException();
        }

        $limite = $this->contentGenerationLimiter->create($utilisateur->getUserIdentifier())->consume();
        if (!$limite->isAccepted()) {
            throw new TooManyRequestsHttpException(
                message: 'Trop de générations en peu de temps. Réessayez dans quelques minutes.',
            );
        }
    }

    private function assertQuota(Parcours $parcours): void
    {
        $total = mb_strlen(json_encode($parcours->getDescription(), JSON_THROW_ON_ERROR));
        foreach ($parcours->getPointEntities() as $point) {
            $total += mb_strlen(json_encode([$point->getName(), $point->getDescription()], JSON_THROW_ON_ERROR));
        }

        if ($total > self::MAX_CHARS) {
            throw new BadRequestHttpException(
                'Ce parcours dépasse le volume de texte autorisé pour une génération automatique.',
            );
        }
    }
}
