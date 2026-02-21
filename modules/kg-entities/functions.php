<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// =======================
// CONFIG
// =======================

function load_config(): string {
    // Load local .env if it exists (standalone mode)
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    }

    $key = $_ENV['GOOGLE_KG_API_KEY'] ?? '';
    if (empty($key) || $key === 'your_api_key_here') {
        return '';
    }
    return $key;
}

// =======================
// API CALLS
// =======================

const KG_SEARCH_ENDPOINT = 'https://kgsearch.googleapis.com/v1/entities:search';

/**
 * Recherche d'entites dans le Knowledge Graph.
 */
function kg_search(string $query, string $language, ?string $type = null, int $limit = 5): array {
    $apiKey = load_config();
    if (empty($apiKey)) {
        return ['error' => 'Cle API manquante. Configurez GOOGLE_KG_API_KEY dans le fichier .env.'];
    }

    $params = [
        'query'     => $query,
        'key'       => $apiKey,
        'limit'     => $limit,
        'indent'    => 'true',
        'languages' => $language,
    ];

    if (!empty($type)) {
        $params['types'] = $type;
    }

    $client = new Client(['timeout' => 10]);

    try {
        $response = $client->get(KG_SEARCH_ENDPOINT, ['query' => $params]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data ?: ['error' => 'Reponse vide de l\'API.'];
    } catch (GuzzleException $e) {
        return ['error' => 'Erreur API : ' . $e->getMessage()];
    }
}

/**
 * Lookup d'une entite par son MID (Machine ID).
 */
function kg_lookup(string $id, string $language): array {
    $apiKey = load_config();
    if (empty($apiKey)) {
        return ['error' => 'Cle API manquante.'];
    }

    $params = [
        'ids'       => $id,
        'key'       => $apiKey,
        'indent'    => 'true',
        'languages' => $language,
    ];

    $client = new Client(['timeout' => 10]);

    try {
        $response = $client->get(KG_SEARCH_ENDPOINT, ['query' => $params]);
        $data = json_decode($response->getBody()->getContents(), true);
        return $data ?: ['error' => 'Reponse vide de l\'API.'];
    } catch (GuzzleException $e) {
        return ['error' => 'Erreur API : ' . $e->getMessage()];
    }
}

// =======================
// AUDIT
// =======================

/**
 * Audite une entite : recherche dans le KG et retourne un rapport structure.
 */
function audit_entity(string $query, string $language): array {
    $raw = kg_search($query, $language, null, 20);

    if (isset($raw['error'])) {
        return ['found' => false, 'error' => $raw['error']];
    }

    $elements = $raw['itemListElement'] ?? [];
    if (empty($elements)) {
        return [
            'found'            => false,
            'query'            => $query,
            'recommendations'  => generate_recommendations([]),
        ];
    }

    // Prendre le premier resultat (meilleur score)
    $top    = $elements[0];
    $result = $top['result'] ?? [];
    $score  = $top['resultScore'] ?? 0;

    $entity = _extract_entity($result, $score);
    $entity['found'] = true;
    $entity['query'] = $query;
    $entity['all_results'] = array_map(function ($el) {
        return [
            'name'  => $el['result']['name'] ?? '—',
            'type'  => implode(', ', $el['result']['@type'] ?? []),
            'score' => $el['resultScore'] ?? 0,
        ];
    }, $elements);
    $entity['completeness'] = calculate_completeness($entity);
    $entity['recommendations'] = generate_recommendations($entity);

    return $entity;
}

/**
 * Extrait les donnees structurees d'un resultat KG.
 */
function _extract_entity(array $result, float $score): array {
    $types = $result['@type'] ?? [];
    // Filtrer "Thing" qui est generique
    $filteredTypes = array_filter($types, fn($t) => $t !== 'Thing');

    $mid = $result['@id'] ?? '';
    // Extraire le MID du format "kg:/m/xxx"
    if (str_starts_with($mid, 'kg:')) {
        $mid = substr($mid, 3);
    }

    $detailedDesc = $result['detailedDescription'] ?? [];

    return [
        'name'                 => $result['name'] ?? '',
        'type'                 => !empty($filteredTypes) ? implode(', ', $filteredTypes) : (implode(', ', $types) ?: '—'),
        'types_array'          => $types,
        'description'          => $result['description'] ?? '',
        'detailed_description' => $detailedDesc['articleBody'] ?? '',
        'detailed_url'         => $detailedDesc['url'] ?? '',
        'result_score'         => round($score, 2),
        'official_url'         => $result['url'] ?? '',
        'image_url'            => $result['image']['contentUrl'] ?? '',
        'identifiers'          => [
            'mid'      => $mid,
            'wikidata' => _extract_wikidata_id($detailedDesc['url'] ?? ''),
        ],
    ];
}

/**
 * Tente d'extraire un QID Wikidata a partir de l'URL Wikipedia.
 */
function _extract_wikidata_id(string $url): string {
    if (empty($url) || !str_contains($url, 'wikipedia.org')) {
        return '';
    }
    // On ne peut pas resoudre le QID cote serveur sans appel supplementaire
    // On fournit un lien vers le concept Wikipedia
    return '';
}

// =======================
// SCORE DE COMPLETUDE
// =======================

/**
 * Calcule un score de completude de l'entite dans le Knowledge Graph.
 * 8 criteres verifies, retourne un score de 0 a 100.
 */
function calculate_completeness(array $entity): array {
    $checks = [
        [
            'label' => 'Nom',
            'ok'    => !empty($entity['name']),
        ],
        [
            'label' => 'Description courte',
            'ok'    => !empty($entity['description']),
        ],
        [
            'label' => 'Description detaillee',
            'ok'    => !empty($entity['detailed_description']),
        ],
        [
            'label' => 'URL officielle',
            'ok'    => !empty($entity['official_url']),
        ],
        [
            'label' => 'Image',
            'ok'    => !empty($entity['image_url']),
        ],
        [
            'label' => 'Page Wikipedia',
            'ok'    => !empty($entity['detailed_url']),
        ],
        [
            'label' => 'Type specifique',
            'ok'    => !empty($entity['types_array']) && $entity['types_array'] !== ['Thing'],
        ],
        [
            'label' => 'Score >= 100',
            'ok'    => ($entity['result_score'] ?? 0) >= 100,
        ],
    ];

    $present = count(array_filter($checks, fn($c) => $c['ok']));
    $total   = count($checks);
    $score   = $total > 0 ? round(($present / $total) * 100) : 0;

    return [
        'score'   => $score,
        'present' => $present,
        'total'   => $total,
        'checks'  => $checks,
    ];
}

// =======================
// RECOMMENDATIONS SEO
// =======================

/**
 * Genere des recommandations SEO basees sur les lacunes detectees.
 */
function generate_recommendations(array $entity): array {
    $recs = [];

    if (empty($entity) || empty($entity['found'] ?? false)) {
        $recs[] = [
            'level'   => 'danger',
            'icon'    => '!',
            'title'   => 'Entite non trouvee',
            'message' => 'Cette entite n\'est pas reconnue par le Knowledge Graph de Google. Creez une page Wikidata, renforcez les signaux d\'entite (mentions coherentes, liens, balisage schema.org) et soumettez votre Knowledge Panel.',
        ];
        return $recs;
    }

    // Score faible (< 100)
    if (($entity['result_score'] ?? 0) < 100) {
        $recs[] = [
            'level'   => 'warning',
            'icon'    => '~',
            'title'   => 'Score de confiance faible',
            'message' => 'Le score de confiance Google est bas. Renforcez les signaux d\'entite : mentions coherentes sur des sources fiables, liens entrants depuis des sites d\'autorite, balisage schema.org structure.',
        ];
    }

    // Pas d'URL officielle
    if (empty($entity['official_url'] ?? '')) {
        $recs[] = [
            'level'   => 'warning',
            'icon'    => '~',
            'title'   => 'Pas d\'URL officielle',
            'message' => 'Aucune URL officielle n\'est associee a cette entite. Revendiquez votre Knowledge Panel via Google Search et assurez-vous que votre site est bien lie a votre entite (Wikidata, Wikipedia, profils sociaux).',
        ];
    }

    // Pas de description
    if (empty($entity['description'] ?? '') && empty($entity['detailed_description'] ?? '')) {
        $recs[] = [
            'level'   => 'warning',
            'icon'    => '~',
            'title'   => 'Pas de description',
            'message' => 'L\'entite n\'a pas de description dans le Knowledge Graph. Creez ou ameliorez la page Wikidata correspondante et assurez-vous qu\'une page Wikipedia existe avec des sources fiables.',
        ];
    }

    // Type manquant ou generique
    if (empty($entity['type'] ?? '') || ($entity['type'] ?? '') === '—' || ($entity['type'] ?? '') === 'Thing') {
        $recs[] = [
            'level'   => 'info',
            'icon'    => 'i',
            'title'   => 'Type d\'entite generique',
            'message' => 'L\'entite n\'a pas de type specifique. Ajoutez du balisage schema.org avec un type precis (Organization, Person, LocalBusiness, Product, etc.) sur votre site.',
        ];
    }

    // Pas d'image
    if (empty($entity['image_url'] ?? '')) {
        $recs[] = [
            'level'   => 'info',
            'icon'    => 'i',
            'title'   => 'Pas d\'image',
            'message' => 'Aucune image n\'est associee a cette entite. Ajoutez une image sur Wikidata/Wikimedia Commons et utilisez la propriete "image" dans votre balisage schema.org.',
        ];
    }

    // Ajouter la categorie generic a toutes les recs existantes
    foreach ($recs as &$rec) {
        $rec['category'] = 'generic';
    }
    unset($rec);

    // Merger les recommandations specifiques au type
    $typeRecs = get_type_specific_recommendations($entity);
    $allRecs = array_merge($recs, $typeRecs);

    // Le message de succes ne s'affiche que s'il n'y a aucune recommandation
    if (empty($allRecs)) {
        $allRecs[] = [
            'level'    => 'success',
            'icon'     => 'v',
            'title'    => 'Bonne presence Knowledge Graph',
            'message'  => 'Cette entite est bien reconnue par Google avec un score eleve, un type specifique, une description et une URL officielle. Continuez a renforcer vos signaux d\'entite.',
            'category' => 'generic',
        ];
    }

    return $allRecs;
}

/**
 * Genere des recommandations specifiques au type d'entite detecte.
 */
function get_type_specific_recommendations(array $entity): array {
    $types = $entity['types_array'] ?? [];
    $typeStr = implode(' ', array_map('strtolower', $types));
    $recs = [];

    if (str_contains($typeStr, 'person')) {
        if (empty($entity['detailed_url'])) {
            $recs[] = [
                'level'    => 'warning',
                'icon'     => '~',
                'title'    => 'Page Wikipedia manquante',
                'message'  => 'Pour une personne, une page Wikipedia bien sourcee est essentielle pour renforcer la presence dans le Knowledge Graph.',
                'category' => 'type_specific',
            ];
        }
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Profils sociaux (sameAs)',
            'message'  => 'Ajoutez des liens sameAs vers vos profils sociaux (LinkedIn, Twitter/X, etc.) dans votre balisage schema.org pour renforcer les signaux d\'identite.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Oeuvres notables',
            'message'  => 'Referencez vos oeuvres, publications ou realisations notables sur Wikidata et dans votre balisage schema.org (propriete "knowsAbout", "award", "worksFor").',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'organization') || str_contains($typeStr, 'corporation')) {
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Google Business Profile',
            'message'  => 'Creez et verifiez votre Google Business Profile pour renforcer la presence de votre organisation dans le Knowledge Graph.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Fiche Wikidata',
            'message'  => 'Creez ou enrichissez votre fiche Wikidata avec des proprietes detaillees : date de fondation, secteur d\'activite, dirigeants, site officiel.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Couverture presse',
            'message'  => 'Obtenez des mentions dans des sources d\'autorite (presse, annuaires professionnels) pour renforcer les signaux d\'entite.',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'localbusiness') || str_contains($typeStr, 'restaurant') || str_contains($typeStr, 'store')) {
        $recs[] = [
            'level'    => 'warning',
            'icon'     => '~',
            'title'    => 'Google Business Profile verifie',
            'message'  => 'Verifiez votre Google Business Profile. Pour un commerce local, c\'est le signal le plus fort pour apparaitre dans le Knowledge Panel.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Coherence NAP',
            'message'  => 'Assurez la coherence de vos informations NAP (Nom, Adresse, Telephone) sur toutes les plateformes : site web, annuaires, reseaux sociaux.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Citations locales et adresse structuree',
            'message'  => 'Inscrivez-vous sur les annuaires locaux et ajoutez le balisage PostalAddress dans votre schema.org LocalBusiness.',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'place') || str_contains($typeStr, 'city') || str_contains($typeStr, 'country') || str_contains($typeStr, 'landmark')) {
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Coordonnees GPS',
            'message'  => 'Ajoutez les coordonnees geographiques (latitude/longitude) dans votre balisage schema.org Place avec la propriete "geo".',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Photos Wikimedia / Google Maps',
            'message'  => 'Ajoutez des photos de qualite sur Wikimedia Commons et Google Maps pour enrichir le Knowledge Panel du lieu.',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'event')) {
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Plateformes de billetterie',
            'message'  => 'Referencez votre evenement sur des plateformes de billetterie (Eventbrite, Ticketmaster) pour renforcer les signaux d\'entite.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Balisage Event avec dates et offres',
            'message'  => 'Utilisez le balisage schema.org Event avec les proprietes startDate, endDate, location et offers pour etre eligible aux resultats enrichis.',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'creativework') || str_contains($typeStr, 'movie') || str_contains($typeStr, 'book') || str_contains($typeStr, 'musicalbum') || str_contains($typeStr, 'tvseries')) {
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Avis et notations',
            'message'  => 'Obtenez des avis sur les plateformes specialisees (IMDb, Goodreads, AlloCine) pour enrichir le Knowledge Panel.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Attribution auteur / editeur',
            'message'  => 'Ajoutez les proprietes author et publisher dans votre balisage schema.org CreativeWork pour une meilleure attribution.',
            'category' => 'type_specific',
        ];
    }

    if (str_contains($typeStr, 'product') || str_contains($typeStr, 'brand')) {
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Avis produit',
            'message'  => 'Collectez des avis produit structures et utilisez le balisage AggregateRating dans votre schema.org pour les resultats enrichis.',
            'category' => 'type_specific',
        ];
        $recs[] = [
            'level'    => 'info',
            'icon'     => 'i',
            'title'    => 'Google Merchant Center',
            'message'  => 'Inscrivez vos produits sur Google Merchant Center pour renforcer votre presence dans les resultats shopping et le Knowledge Graph.',
            'category' => 'type_specific',
        ];
    }

    return $recs;
}

// =======================
// GENERATION SCHEMA.ORG JSON-LD
// =======================

/**
 * Types schema.org disponibles pour la generation.
 */
const SCHEMA_TYPES = [
    'Organization',
    'Person',
    'LocalBusiness',
    'Corporation',
    'Brand',
    'Product',
    'Event',
    'Place',
    'CreativeWork',
    'SoftwareApplication',
];

/**
 * Retourne des proprietes Schema.org specifiques au type avec valeurs placeholder.
 */
function get_type_properties(string $schema_type): array {
    $props = [];

    switch ($schema_type) {
        case 'Organization':
        case 'Corporation':
            $props = [
                'foundingDate'      => '2000-01-01',
                'numberOfEmployees' => ['@type' => 'QuantitativeValue', 'value' => 100],
                'contactPoint'      => ['@type' => 'ContactPoint', 'telephone' => '+33-1-23-45-67-89', 'contactType' => 'customer service'],
                'address'           => ['@type' => 'PostalAddress', 'streetAddress' => '1 rue Example', 'addressLocality' => 'Paris', 'postalCode' => '75001', 'addressCountry' => 'FR'],
                'logo'              => 'https://www.example.com/logo.png',
            ];
            break;

        case 'Person':
            $props = [
                'birthDate'   => '1990-01-01',
                'jobTitle'    => 'Titre du poste',
                'affiliation' => ['@type' => 'Organization', 'name' => 'Nom de l\'organisation'],
                'alumniOf'    => ['@type' => 'EducationalOrganization', 'name' => 'Nom de l\'etablissement'],
                'award'       => 'Nom du prix ou distinction',
            ];
            break;

        case 'LocalBusiness':
            $props = [
                'address'                   => ['@type' => 'PostalAddress', 'streetAddress' => '1 rue Example', 'addressLocality' => 'Paris', 'postalCode' => '75001', 'addressCountry' => 'FR'],
                'telephone'                 => '+33-1-23-45-67-89',
                'openingHoursSpecification' => [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '09:00', 'closes' => '18:00']],
                'priceRange'                => '$$',
                'geo'                       => ['@type' => 'GeoCoordinates', 'latitude' => 48.8566, 'longitude' => 2.3522],
            ];
            break;

        case 'Event':
            $props = [
                'startDate' => '2025-06-15T09:00:00+02:00',
                'endDate'   => '2025-06-15T18:00:00+02:00',
                'location'  => ['@type' => 'Place', 'name' => 'Nom du lieu', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Paris', 'addressCountry' => 'FR']],
                'organizer' => ['@type' => 'Organization', 'name' => 'Nom de l\'organisateur'],
                'offers'    => ['@type' => 'Offer', 'price' => '50.00', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => 'https://www.example.com/billets'],
            ];
            break;

        case 'Place':
            $props = [
                'geo'     => ['@type' => 'GeoCoordinates', 'latitude' => 48.8566, 'longitude' => 2.3522],
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Paris', 'addressCountry' => 'FR'],
                'hasMap'  => 'https://www.google.com/maps/place/...',
            ];
            break;

        case 'Product':
            $props = [
                'brand'           => ['@type' => 'Brand', 'name' => 'Nom de la marque'],
                'offers'          => ['@type' => 'Offer', 'price' => '99.99', 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock'],
                'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '4.5', 'reviewCount' => '100'],
                'sku'             => 'SKU-12345',
            ];
            break;

        case 'Brand':
            $props = [
                'logo'   => 'https://www.example.com/logo.png',
                'slogan' => 'Votre slogan ici',
            ];
            break;

        case 'CreativeWork':
            $props = [
                'author'        => ['@type' => 'Person', 'name' => 'Nom de l\'auteur'],
                'datePublished' => '2024-01-01',
                'publisher'     => ['@type' => 'Organization', 'name' => 'Nom de l\'editeur'],
                'genre'         => 'Genre',
            ];
            break;

        case 'SoftwareApplication':
            $props = [
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem'     => 'Windows, macOS, Linux',
                'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
                'aggregateRating'     => ['@type' => 'AggregateRating', 'ratingValue' => '4.5', 'reviewCount' => '100'],
            ];
            break;
    }

    return $props;
}

/**
 * Genere un bloc JSON-LD schema.org a partir des donnees KG.
 */
function generate_jsonld(array $entity, string $schema_type): string {
    if (empty($entity) || empty($entity['found'] ?? false)) {
        return '';
    }

    $jsonld = [
        '@context' => 'https://schema.org',
        '@type'    => $schema_type,
    ];

    // Name
    if (!empty($entity['name'])) {
        $jsonld['name'] = $entity['name'];
    }

    // Description
    $desc = $entity['detailed_description'] ?? $entity['description'] ?? '';
    if (!empty($desc)) {
        $jsonld['description'] = $desc;
    }

    // URL
    if (!empty($entity['official_url'])) {
        $jsonld['url'] = $entity['official_url'];
    }

    // Image
    if (!empty($entity['image_url'])) {
        $jsonld['image'] = $entity['image_url'];
    }

    // sameAs : liens vers Wikipedia, Wikidata
    $sameAs = [];
    if (!empty($entity['detailed_url'])) {
        $sameAs[] = $entity['detailed_url'];

        // Deduire le lien Wikidata depuis Wikipedia
        $wikiUrl = $entity['detailed_url'];
        if (preg_match('#https?://([a-z]+)\.wikipedia\.org/wiki/(.+)#', $wikiUrl, $m)) {
            $lang = $m[1];
            $title = $m[2];
            $sameAs[] = 'https://www.wikidata.org/wiki/Special:ItemByTitle/'. $lang . 'wiki/' . $title;
        }
    }
    if (!empty($entity['identifiers']['mid'])) {
        $sameAs[] = 'https://www.google.com/search?kgmid=' . urlencode($entity['identifiers']['mid']);
    }
    if (!empty($sameAs)) {
        $jsonld['sameAs'] = $sameAs;
    }

    // Identifiant supplementaire
    if (!empty($entity['identifiers']['mid'])) {
        $jsonld['identifier'] = [
            '@type'    => 'PropertyValue',
            'propertyID' => 'googleKgMID',
            'value'    => $entity['identifiers']['mid'],
        ];
    }

    // Merger les proprietes specifiques au type (sans ecraser les existantes)
    $typeProps = get_type_properties($schema_type);
    foreach ($typeProps as $key => $value) {
        if (!isset($jsonld[$key])) {
            $jsonld[$key] = $value;
        }
    }

    return json_encode($jsonld, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Suggere un type schema.org en fonction des types KG retournes.
 */
function suggest_schema_type(array $entity): string {
    $types = $entity['types_array'] ?? [];
    $typeStr = implode(' ', array_map('strtolower', $types));

    if (str_contains($typeStr, 'person')) return 'Person';
    if (str_contains($typeStr, 'organization') || str_contains($typeStr, 'corporation')) return 'Organization';
    if (str_contains($typeStr, 'localbusiness') || str_contains($typeStr, 'restaurant') || str_contains($typeStr, 'store')) return 'LocalBusiness';
    if (str_contains($typeStr, 'place') || str_contains($typeStr, 'city') || str_contains($typeStr, 'country')) return 'Place';
    if (str_contains($typeStr, 'event')) return 'Event';
    if (str_contains($typeStr, 'creativework') || str_contains($typeStr, 'movie') || str_contains($typeStr, 'book') || str_contains($typeStr, 'musicalbum')) return 'CreativeWork';
    if (str_contains($typeStr, 'product') || str_contains($typeStr, 'brand')) return 'Brand';
    if (str_contains($typeStr, 'softwareapplication')) return 'SoftwareApplication';

    return 'Organization';
}

// =======================
// FORM HANDLER
// =======================

/**
 * Gere la soumission du formulaire et retourne les donnees pour la vue.
 */
function handle_form(): array {
    $query      = '';
    $language   = 'fr';
    $audit      = null;
    $jsonld     = '';
    $schemaType = 'Organization';
    $hasPost    = false;
    $apiKeyOk   = true;
    $activeTab  = 'audit';

    // Verifier la cle API
    try {
        $key = load_config();
        if (empty($key)) {
            $apiKeyOk = false;
        }
    } catch (\Exception $e) {
        $apiKeyOk = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) {
        $hasPost    = true;
        $query      = trim($_POST['query']);
        $language   = $_POST['language'] ?? 'fr';
        $schemaType = $_POST['schema_type'] ?? '';

        if (($_POST['active_tab'] ?? '') === 'schema') {
            $activeTab = 'schema';
        }

        // Audit
        $audit = audit_entity($query, $language);

        // Type schema.org : si pas choisi, on suggere
        if (empty($schemaType) && !empty($audit['found'])) {
            $schemaType = suggest_schema_type($audit);
        }

        // Generer le JSON-LD
        if (!empty($audit['found'])) {
            $jsonld = generate_jsonld($audit, $schemaType);
        }
    }

    return compact('query', 'language', 'audit', 'jsonld', 'schemaType', 'hasPost', 'apiKeyOk', 'activeTab');
}
