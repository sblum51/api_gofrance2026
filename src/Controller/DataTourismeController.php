<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Proxy vers l'API DATAtourisme (https://api.datatourisme.fr/v1/docs) : la clé
 * API ne doit jamais être exposée au navigateur, ce contrôleur fait l'appel
 * côté serveur et simplifie la réponse pour le formulaire de parcours.
 */
final class DataTourismeController
{
    private const string BASE_URL = 'https://api.datatourisme.fr/v1/catalog';
    private const float DEFAULT_RADIUS_KM = 20;
    private const int MAX_RESULTS = 20;

    /**
     * DATAtourisme ne propose pas de filtre serveur fiable par catégorie : on
     * sur-échantillonne (voir page_size dans search()) puis on filtre
     * nous-mêmes sur le champ `type` (liste de tags JSON-LD portés par chaque
     * POI). Ce classement est une approximation manuelle des types observés
     * dans les données réelles, pas une taxonomie officielle DATAtourisme.
     *
     * @var array<string, list<string>>
     */
    private const array CATEGORIES = [
        // 'PlaceOfInterest'/'PointOfInterest' délibérément exclus : DATAtourisme
        // les pose sur quasi tous les POI (restaurants, hôtels, commerces...),
        // ce qui ferait fuiter toutes les catégories dans "site" si on les gardait.
        'site' => ['CulturalSite', 'Museum', 'Cathedral', 'ReligiousSite', 'RemarkableBuilding', 'ArcheologicalSite', 'CivicStructure', 'MilitaryCemetery', 'RemembranceSite'],
        'activite' => ['ActivityProvider', 'LeisureSportActivityProvider', 'CulturalActivityProvider', 'SportsAndLeisurePlace', 'PlayArea', 'Tour', 'WalkingTour', 'TouristTrain', 'SightseeingBoat', 'Visit', 'Course', 'IntroductionCourse', 'Practice', 'AccompaniedPractice'],
        'commerce' => ['Store', 'LocalProductsShop', 'Producer', 'Farmer', 'Trader', 'CraftsmanShop', 'LocalBusiness', 'Product', 'Tasting', 'TastingProvider', 'Brewery'],
        'hebergement_restauration' => ['Accommodation', 'AccommodationProduct', 'Hotel', 'HotelRestaurant', 'HotelTrade', 'BedAndBreakfast', 'Guesthouse', 'LodgingBusiness', 'RentalAccommodation', 'SelfCateringAccommodation', 'Restaurant', 'FoodEstablishment', 'GourmetRestaurant', 'BarOrPub', 'BrasserieOrTavern'],
        'evenement' => ['Event', 'EntertainmentAndEvent', 'CulturalEvent', 'MusicEvent', 'Concert', 'Exhibition', 'ExhibitionEvent', 'ShowEvent', 'TheaterEvent', 'ScreeningEvent', 'BusinessEvent'],
    ];

    /** Catégories retenues quand le client n'envoie pas explicitement de filtre (ex. appel curl direct). */
    private const array DEFAULT_CATEGORIES = ['site', 'activite', 'commerce'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Security $security,
        private readonly string $datatourismeApiKey,
    ) {
    }

    #[Route('/datatourisme/search', name: 'datatourisme_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if ((null === $lat || null === $lng) && $this->security->getUser() instanceof User) {
            $organization = $this->security->getUser()->getOrganization();
            $lat ??= $organization?->getMainCommuneLat();
            $lng ??= $organization?->getMainCommuneLng();
        }

        if (null === $lat || null === $lng) {
            return new JsonResponse(
                ['message' => "Coordonnées manquantes : renseignez la commune principale de l'organisation ou fournissez lat/lng."],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $categories = $request->query->has('categories')
            ? array_values(array_filter(explode(',', (string) $request->query->get('categories'))))
            : self::DEFAULT_CATEGORIES;
        $categories = array_intersect($categories, array_keys(self::CATEGORIES));

        if ([] === $categories) {
            return new JsonResponse(['results' => []]);
        }

        $allowedTypes = array_unique(array_merge(...array_map(
            static fn (string $category): array => self::CATEGORIES[$category],
            $categories,
        )));

        $params = [
            'geo_distance' => sprintf('%s,%s,%skm', $lat, $lng, self::DEFAULT_RADIUS_KM),
            'lang' => 'fr,en',
            // Sur-échantillonné : le filtrage par catégorie se fait après coup, ci-dessous.
            'page_size' => 50,
        ];
        if ('' !== $query) {
            $params['search'] = $query;
        }

        $response = $this->httpClient->request('GET', self::BASE_URL, [
            'headers' => ['X-API-Key' => $this->datatourismeApiKey],
            'query' => $params,
            'timeout' => 10,
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            return new JsonResponse(
                ['message' => $data['message'] ?? 'Erreur lors de la recherche DATAtourisme.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        $pois = array_filter(
            $data['objects'] ?? [],
            static fn (array $poi): bool => [] !== array_intersect($poi['type'] ?? [], $allowedTypes),
        );

        $results = array_map($this->mapPoi(...), array_slice(array_values($pois), 0, self::MAX_RESULTS));

        return new JsonResponse(['results' => array_values($results)]);
    }

    /** @return array<string, mixed> */
    private function mapPoi(array $poi): array
    {
        $location = $poi['isLocatedAt'][0]['geo'] ?? null;
        $descriptionBlock = $poi['hasDescription'][0] ?? null;
        $description = $descriptionBlock['shortDescription'] ?? $descriptionBlock['description'] ?? [];

        return [
            'datatourismeId' => $poi['uuid'] ?? null,
            'name' => $this->extractLocales($poi['label'] ?? []),
            'description' => $this->extractLocales($description),
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'imageUrl' => $this->extractImageUrl($poi),
            'links' => $this->extractLinks($poi),
        ];
    }

    private function extractImageUrl(array $poi): ?string
    {
        $representation = $poi['hasMainRepresentation'][0] ?? null;

        return $representation['hasRelatedResource'][0]['locator'][0] ?? null;
    }

    /** @return list<array{label: string, url: string}> */
    private function extractLinks(array $poi): array
    {
        $links = [];
        foreach ($poi['hasContact'] ?? [] as $contact) {
            $url = $contact['homepage'][0] ?? null;
            if (is_string($url) && '' !== $url) {
                $links[] = ['label' => $this->labelForUrl($url), 'url' => $url];
            }
        }

        return $links;
    }

    private function labelForUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'facebook.') => 'Facebook',
            str_contains($host, 'instagram.') => 'Instagram',
            str_contains($host, 'twitter.') || str_contains($host, 'x.com') => 'Twitter/X',
            default => 'Site web',
        };
    }

    /** @return array<string, string> */
    private function extractLocales(array $localized): array
    {
        $result = [];
        foreach ($localized as $key => $value) {
            if (str_starts_with((string) $key, '@') && is_string($value) && '' !== $value) {
                $result[substr((string) $key, 1)] = $value;
            }
        }

        return $result;
    }
}
