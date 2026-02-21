<?php

// ─── Stopwords FR (~150 mots) ──────────────────────────────────────────────
const STOPWORDS_FR = [
    'le','la','les','un','une','des','de','du','au','aux','ce','ces','cette',
    'cet','mon','ma','mes','ton','ta','tes','son','sa','ses','notre','nos',
    'votre','vos','leur','leurs','je','tu','il','elle','on','nous','vous',
    'ils','elles','me','te','se','lui','en','y','qui','que','quoi','dont',
    'ou','où','et','mais','ou','donc','or','ni','car','si','ne','pas','plus',
    'moins','très','bien','mal','peu','trop','aussi','encore','toujours',
    'jamais','rien','tout','tous','toute','toutes','même','autre','autres',
    'avec','pour','par','sur','sous','dans','entre','vers','chez','sans',
    'avant','après','depuis','pendant','contre','comme','être','avoir',
    'faire','dire','aller','voir','savoir','pouvoir','vouloir','devoir',
    'falloir','est','sont','été','était','sera','fait','peut','doit',
    'a','ai','as','avons','avez','ont','suis','es','sommes','êtes',
    'c','d','j','l','m','n','s','t','qu','à','ça','ci','là',
    'the','a','an','and','or','but','in','on','at','to','for','of','with',
    'is','are','was','were','be','been','has','have','had','do','does','did',
    'not','no','this','that','these','those','it','its','i','you','he','she',
    'we','they','my','your','his','her','our','their','what','which','who',
    'how','when','where','why','will','would','can','could','should','may',
    'might','shall','must','than','then','so','if','about','up','out','just',
    'also','from','by','all','each','every','both','few','more','most',
    'some','any','many','much','own','other','new','old','big','small',
    'long','short','good','bad','great','little','right','left','part',
    'only','own','same','first','last','next','still','already','entre',
    'ainsi','alors','cela','celui','celle','ceux','celles','chaque',
    'deux','trois','quatre','cinq','six','sept','huit','neuf','dix',
    'cent','mille','premier','première','dernier','dernière','fois',
    'temps','jour','vie','monde','main','chose','cas',
    'point','fait','ici','quand','comment','pourquoi','parce',
    'www','http','https','com','fr','org','html','php','page',
];

// ─── Mots indicateurs d'intention de recherche ─────────────────────────────
const INTENT_TRANSACTIONAL = [
    'acheter','achat','prix','tarif','promo','promotion','solde','soldes',
    'pas cher','meilleur prix','commander','commande','livraison','boutique',
    'magasin','offre','réduction','discount','vente','bon plan','devis',
    'buy','purchase','price','cheap','deal','order','shop','store','sale',
];

const INTENT_COMMERCIAL = [
    'comparatif','comparateur','avis','test','meilleur','top','classement',
    'versus','vs','review','alternative','quel','quelle','choisir','guide',
    'sélection','recommandation','best','compare','comparison','rating',
];

const INTENT_NAVIGATIONAL = [
    'connexion','login','compte','espace client','mon compte','inscription',
    'contact','accueil','page','site officiel','official','sign in','log in',
];

// ─── Récupération HTTP (via Puppeteer) ────────────────────────────────────

function fetch_page(string $url): array
{
    $script = __DIR__ . '/fetch-page.js';
    $ldPath = __DIR__ . '/chromium-libs';
    $cmd = 'LD_LIBRARY_PATH=' . escapeshellarg($ldPath) . ' node ' . escapeshellarg($script) . ' ' . escapeshellarg($url) . ' 2>&1';
    $output = shell_exec($cmd);

    if ($output === null || trim($output) === '') {
        return ['status' => 'error', 'html' => '', 'finalUrl' => $url, 'httpCode' => 0, 'error' => 'Puppeteer : aucune sortie (node introuvable ou crash)'];
    }

    $data = json_decode($output, true);
    if (!is_array($data) || !isset($data['status'])) {
        return ['status' => 'error', 'html' => '', 'finalUrl' => $url, 'httpCode' => 0, 'error' => 'Puppeteer : JSON invalide — ' . mb_substr($output, 0, 200)];
    }

    if ($data['status'] !== 'ok') {
        return ['status' => 'error', 'html' => '', 'finalUrl' => $url, 'httpCode' => 0, 'error' => 'Puppeteer : ' . ($data['error'] ?? 'erreur inconnue')];
    }

    $httpCode = $data['httpCode'] ?? 0;
    if ($httpCode >= 400) {
        return ['status' => 'error', 'html' => '', 'finalUrl' => $data['finalUrl'] ?? $url, 'httpCode' => $httpCode, 'error' => "Erreur HTTP $httpCode"];
    }

    return [
        'status'   => 'ok',
        'html'     => $data['html'] ?? '',
        'finalUrl' => $data['finalUrl'] ?? $url,
        'httpCode' => $httpCode,
    ];
}

// ─── Parsing HTML ──────────────────────────────────────────────────────────

function parse_page(string $html, string $url): array
{
    $result = [
        'title'            => '',
        'meta_description' => '',
        'meta_keywords'    => '',
        'canonical'        => '',
        'h1'               => [],
        'h2'               => [],
        'h3'               => [],
        'body_text'        => '',
        'url_parts'        => [],
        'img_alts'         => [],
        'links_text'       => [],
        'word_count'       => 0,
    ];

    // Décomposition URL
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    $segments = array_filter(explode('/', $path), fn($s) => $s !== '');
    $result['url_parts'] = array_values(array_map(function ($seg) {
        // Retirer les extensions et remplacer tirets/underscores par des espaces
        $seg = preg_replace('/\.[a-z]{2,5}$/i', '', $seg);
        $seg = str_replace(['-', '_'], ' ', $seg);
        return trim($seg);
    }, $segments));

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    // Title
    $titles = $doc->getElementsByTagName('title');
    if ($titles->length > 0) {
        $result['title'] = trim($titles->item(0)->textContent);
    }

    // Meta tags
    $metas = $doc->getElementsByTagName('meta');
    foreach ($metas as $meta) {
        $name = strtolower($meta->getAttribute('name'));
        $content = $meta->getAttribute('content');
        if ($name === 'description') {
            $result['meta_description'] = trim($content);
        } elseif ($name === 'keywords') {
            $result['meta_keywords'] = trim($content);
        }
    }

    // Canonical
    $links = $doc->getElementsByTagName('link');
    foreach ($links as $link) {
        if (strtolower($link->getAttribute('rel')) === 'canonical') {
            $result['canonical'] = trim($link->getAttribute('href'));
            break;
        }
    }

    // Headings
    foreach (['h1', 'h2', 'h3'] as $tag) {
        $nodes = $doc->getElementsByTagName($tag);
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $result[$tag][] = $text;
            }
        }
    }

    // Images alt
    $imgs = $doc->getElementsByTagName('img');
    foreach ($imgs as $img) {
        $alt = trim($img->getAttribute('alt'));
        if ($alt !== '') {
            $result['img_alts'][] = $alt;
        }
    }

    // Links text (internes)
    $anchors = $doc->getElementsByTagName('a');
    $host = $parsed['host'] ?? '';
    foreach ($anchors as $a) {
        $href = $a->getAttribute('href');
        $linkHost = parse_url($href, PHP_URL_HOST);
        // Liens internes ou relatifs
        if ($linkHost === null || $linkHost === '' || $linkHost === $host) {
            $text = trim($a->textContent);
            if ($text !== '' && mb_strlen($text) < 200) {
                $result['links_text'][] = $text;
            }
        }
    }

    // Body text (sans scripts/styles)
    $scripts = $xpath->query('//script|//style|//noscript|//nav|//footer|//header');
    foreach ($scripts as $script) {
        $script->parentNode->removeChild($script);
    }
    $body = $doc->getElementsByTagName('body');
    if ($body->length > 0) {
        $rawText = $body->item(0)->textContent;
        // Nettoyage
        $rawText = preg_replace('/\s+/', ' ', $rawText);
        $result['body_text'] = trim($rawText);
    }

    // Word count
    $words = preg_split('/\s+/', $result['body_text'], -1, PREG_SPLIT_NO_EMPTY);
    $result['word_count'] = count($words);

    return $result;
}

// ─── Analyse sémantique ────────────────────────────────────────────────────

function tokenize(string $text): array
{
    $text = mb_strtolower($text);
    // Garder les lettres, chiffres, accents
    $text = preg_replace('/[^\p{L}\p{N}\s\'-]/u', ' ', $text);
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    // Filtrer stopwords et mots trop courts
    $filtered = array_values(array_filter($tokens, function ($t) {
        return mb_strlen($t) >= 3 && !in_array($t, STOPWORDS_FR, true);
    }));
    // Stemmer chaque token
    return array_map('stem_token', $filtered);
}

// ─── Stemmer français (suffix-stripping) ────────────────────────────────────

function get_french_stemmer(): \Wamania\Snowball\Stemmer\French
{
    static $stemmer = null;
    if ($stemmer === null) {
        $stemmer = new \Wamania\Snowball\Stemmer\French();
    }
    return $stemmer;
}

function stem_token(string $token): string
{
    $len = mb_strlen($token);
    if ($len < 4) return $token;

    // Suffixes ordonnés du plus long au plus court
    // Chaque paire : [suffixe à retirer, remplacement]
    $rules = [
        ['issement', ''],
        ['ement', ''],
        ['ment', ''],
        ['ches', 'c'],     // blanches → blanc
        ['che', 'c'],      // blanche → blanc
        ['gues', 'g'],     // longues → long
        ['gue', 'g'],      // longue → long
        ['euses', 'eux'],  // heureuses → heureux
        ['euse', 'eux'],   // heureuse → heureux
        ['ères', 'er'],    // premières → premier
        ['ère', 'er'],     // première → premier
        ['ives', 'if'],    // sportives → sportif
        ['ive', 'if'],     // sportive → sportif
        ['ées', 'é'],      // colorées → coloré
        ['ée', 'é'],       // colorée → coloré
        ['es', ''],        // robes → rob
        ['e', ''],         // robe → rob
        ['s', ''],         // blancs → blanc
    ];

    foreach ($rules as [$suffix, $replacement]) {
        $suffixLen = mb_strlen($suffix);
        if ($len > $suffixLen + 2 && mb_substr($token, -$suffixLen) === $suffix) {
            $stem = mb_substr($token, 0, $len - $suffixLen) . $replacement;
            return $stem !== '' ? $stem : $token;
        }
    }

    return $token;
}

function tokenize_with_surface(string $text, float $weight, array &$surfaceMap): array
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s\'-]/u', ' ', $text);
    $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    // Filtrer stopwords et mots trop courts
    $filtered = array_values(array_filter($tokens, function ($t) {
        return mb_strlen($t) >= 3 && !in_array($t, STOPWORDS_FR, true);
    }));

    $stems = [];
    foreach ($filtered as $original) {
        $stem = stem_token($original);
        $stems[] = $stem;
        // Tracker la forme de surface la plus pondérée pour chaque stem
        if (!isset($surfaceMap[$stem])) {
            $surfaceMap[$stem] = [];
        }
        $surfaceMap[$stem][$original] = ($surfaceMap[$stem][$original] ?? 0) + $weight;
    }

    return ['stems' => $stems, 'originals' => $filtered];
}

function resolve_surface_ngram(string $stemNgram, array $ngramSurfaceMap, array $surfaceMap): string
{
    // Essayer d'abord la résolution par n-gram complet (préserve le contexte : "robe blanche")
    if (isset($ngramSurfaceMap[$stemNgram]) && !empty($ngramSurfaceMap[$stemNgram])) {
        arsort($ngramSurfaceMap[$stemNgram]);
        return array_key_first($ngramSurfaceMap[$stemNgram]);
    }

    // Fallback : résolution par stem individuel
    $stems = explode(' ', $stemNgram);
    $resolved = [];
    foreach ($stems as $stem) {
        if (isset($surfaceMap[$stem]) && !empty($surfaceMap[$stem])) {
            arsort($surfaceMap[$stem]);
            $resolved[] = array_key_first($surfaceMap[$stem]);
        } else {
            $resolved[] = $stem;
        }
    }
    return implode(' ', $resolved);
}

function count_ngrams(array $tokens, int $n = 1): array
{
    $counts = [];
    $len = count($tokens);
    for ($i = 0; $i <= $len - $n; $i++) {
        $ngram = implode(' ', array_slice($tokens, $i, $n));
        $counts[$ngram] = ($counts[$ngram] ?? 0) + 1;
    }
    return $counts;
}

function calculate_soseo(array $topTerms, array $termZones): float
{
    if (empty($topTerms)) return 0;

    $coverageScores = [];
    foreach ($topTerms as $term => $score) {
        $zones = $termZones[$term] ?? [];
        $coverage = 0;
        if (isset($zones['title']))     $coverage += 25;
        if (isset($zones['h1']))        $coverage += 25;
        if (isset($zones['url']))       $coverage += 15;
        if (isset($zones['meta_desc'])) $coverage += 10;
        if (isset($zones['h2']))        $coverage += 10;
        if (isset($zones['body']))      $coverage += 15;
        $coverageScores[] = min($coverage, 100);
    }

    return array_sum($coverageScores) / count($coverageScores);
}

function calculate_dseo(array $topTerms, array $bodyNgramCounts, int $totalBodyWords, string $primaryKwStem): float
{
    if (empty($topTerms) || $totalBodyWords === 0) return 0;

    $overOptimized = 0;
    $count = count($topTerms);

    foreach ($topTerms as $term => $score) {
        $bodyCount = $bodyNgramCounts[$term] ?? 0;
        $wordCount = substr_count($term, ' ') + 1;
        $density = $bodyCount / $totalBodyWords * 100;

        $threshold = match ($wordCount) {
            1 => 3,
            2 => 2,
            default => 1.5,
        };

        if ($density > $threshold) {
            $overOptimized++;
        }
    }

    $dseo = ($overOptimized / $count) * 100;

    // Pénalité si le keyword principal a une densité > 4%
    $pkBodyCount = $bodyNgramCounts[$primaryKwStem] ?? 0;
    $pkDensity = $pkBodyCount / $totalBodyWords * 100;
    if ($pkDensity > 4) {
        $dseo += 30;
    }

    return min($dseo, 100);
}

function analyze_keywords(array $parsed): array
{
    // Zones texte (une seule chaîne)
    $textZones = [
        'h1'       => implode(' ', $parsed['h1']),
        'title'    => $parsed['title'],
        'url'      => implode(' ', $parsed['url_parts']),
        'h2'       => implode(' ', $parsed['h2']),
        'meta_desc'=> $parsed['meta_description'],
        'body'     => $parsed['body_text'],
    ];
    // Zones multi-items : tokenisées par item pour éviter les ngrams inter-items
    $arrayZones = [
        'img_alts' => $parsed['img_alts'],
    ];

    $weights = [
        'h1'        => 5,
        'title'     => 4,
        'url'       => 3,
        'h2'        => 2,
        'meta_desc' => 2,
        'body'      => 1,
        'img_alts'  => 1,
    ];

    $scores = [];
    $surfaceMap = [];
    $ngramSurfaceMap = [];
    $termZones = [];       // $termZones[stemTerm][zone] = true
    $bodyNgramCounts = []; // compteurs bruts body pour calcul de densité

    // Zones texte classiques (body log-damped)
    foreach ($textZones as $zone => $text) {
        if (trim($text) === '') continue;
        $w = $weights[$zone];
        $result = tokenize_with_surface($text, $w, $surfaceMap);
        $tokens = $result['stems'];
        $origTokens = $result['originals'];
        $useLog = ($zone === 'body');

        foreach ([1 => 1, 2 => 1.5, 3 => 2] as $n => $ngramBonus) {
            $ngrams = count_ngrams($tokens, $n);

            // Construire ngramSurfaceMap (formes de surface au niveau n-gram)
            $len = count($tokens);
            for ($i = 0; $i <= $len - $n; $i++) {
                $stemNgram = implode(' ', array_slice($tokens, $i, $n));
                $surfaceNgram = implode(' ', array_slice($origTokens, $i, $n));
                $ngramSurfaceMap[$stemNgram][$surfaceNgram] =
                    ($ngramSurfaceMap[$stemNgram][$surfaceNgram] ?? 0) + $w;
            }

            foreach ($ngrams as $term => $count) {
                // Tracker la présence par zone
                $termZones[$term][$zone] = true;

                // Compteurs bruts body pour densité
                if ($zone === 'body') {
                    $bodyNgramCounts[$term] = ($bodyNgramCounts[$term] ?? 0) + $count;
                }

                $effective = $useLog ? log(1 + $count) : $count;
                $scores[$term] = ($scores[$term] ?? 0) + $effective * $w * $ngramBonus;
            }
        }
    }

    // Zones array : tokeniser chaque item séparément puis agréger avec log
    foreach ($arrayZones as $zone => $items) {
        if (empty($items)) continue;
        $w = $weights[$zone];
        $perItem = [];
        foreach ($items as $item) {
            $result = tokenize_with_surface($item, $w, $surfaceMap);
            $tokens = $result['stems'];
            $origTokens = $result['originals'];
            foreach ([1 => 1, 2 => 1.5, 3 => 2] as $n => $ngramBonus) {
                $ngrams = count_ngrams($tokens, $n);

                // Construire ngramSurfaceMap
                $len = count($tokens);
                for ($i = 0; $i <= $len - $n; $i++) {
                    $stemNgram = implode(' ', array_slice($tokens, $i, $n));
                    $surfaceNgram = implode(' ', array_slice($origTokens, $i, $n));
                    $ngramSurfaceMap[$stemNgram][$surfaceNgram] =
                        ($ngramSurfaceMap[$stemNgram][$surfaceNgram] ?? 0) + $w;
                }

                foreach ($ngrams as $term => $count) {
                    $termZones[$term][$zone] = true;
                    $perItem[$term] = ($perItem[$term] ?? 0) + $count;
                }
            }
        }
        foreach ($perItem as $term => $count) {
            $wordCount = substr_count($term, ' ') + 1;
            $ngramBonus = [1 => 1, 2 => 1.5, 3 => 2][$wordCount] ?? 1;
            $scores[$term] = ($scores[$term] ?? 0) + log(1 + $count) * $w * $ngramBonus;
        }
    }

    // Position bonus : termes dans les 200 premiers caractères du body
    $bodyFirst200 = mb_substr($textZones['body'] ?? '', 0, 200);
    $earlyTokens = tokenize($bodyFirst200);
    $earlyNgrams = [];
    foreach ([1, 2, 3] as $n) {
        foreach (array_keys(count_ngrams($earlyTokens, $n)) as $ng) {
            $earlyNgrams[$ng] = true;
        }
    }

    // Appliquer bonus multi-zones + bonus position
    foreach ($scores as $term => &$scoreRef) {
        $zones = array_keys($termZones[$term] ?? []);
        $freqScore = $scoreRef;

        // Zone presence bonus
        $zoneBonus = 0;
        $inTitle = in_array('title', $zones);
        $inH1    = in_array('h1', $zones);
        $inUrl   = in_array('url', $zones);

        if ($inTitle && $inH1 && $inUrl) {
            $zoneBonus = 0.8;
        } elseif ($inTitle && $inH1) {
            $zoneBonus = 0.5;
        } elseif (count($zones) >= 3) {
            $zoneBonus = 0.3;
        }

        // Position bonus (premiers 200 caractères du body)
        $posBonus = isset($earlyNgrams[$term]) ? 0.2 : 0;

        $scoreRef = $freqScore * (1 + $zoneBonus + $posBonus);
    }
    unset($scoreRef);

    arsort($scores);

    // Filtrer : préférer les n-grams longs qui englobent des n-grams courts
    $topTerms = array_slice($scores, 0, 80, true);
    $filtered = [];
    foreach ($topTerms as $term => $score) {
        $dominated = false;
        foreach ($filtered as $kept => $keptScore) {
            if (str_contains($kept, $term) && $keptScore >= $score) {
                $dominated = true;
                break;
            }
        }
        if (!$dominated) {
            $filtered[$term] = $score;
        }
    }

    // Identifier le keyword principal : préférer les termes multi-mots
    // Bonus d'accord title+H1 (x1.5) pour la sélection
    $topTerm = array_key_first($filtered);
    $topScore = $filtered[$topTerm] ?? 0;
    $primaryKw = $topTerm;
    $primaryScore = $topScore;

    if ($topTerm !== null && !str_contains($topTerm, ' ')) {
        $bestMulti = '';
        $bestMultiScore = 0;
        foreach ($filtered as $term => $score) {
            if (!str_contains($term, ' ')) continue;
            if (!str_contains($term, $topTerm)) continue;

            $selectionScore = $score;
            // Bonus title+H1 agreement pour la sélection
            if (isset($termZones[$term]['title']) && isset($termZones[$term]['h1'])) {
                $selectionScore *= 1.5;
            }

            if ($selectionScore >= $topScore * 0.4 && $selectionScore > $bestMultiScore) {
                $bestMulti = $term;
                $bestMultiScore = $selectionScore;
            }
            if ($bestMulti !== '' && str_contains($term, $bestMulti) && $selectionScore >= $topScore * 0.3) {
                $bestMulti = $term;
                $bestMultiScore = $selectionScore;
            }
        }
        if ($bestMulti !== '') {
            $primaryKw = $bestMulti;
            $primaryScore = $filtered[$bestMulti];
        }
    }

    $variants = [];
    foreach ($filtered as $term => $score) {
        if ($term === $primaryKw) continue;
        if (str_contains($primaryKw, $term) || str_contains($term, $primaryKw)) continue;
        if (count($variants) < 5) {
            $variants[] = ['term' => $term, 'score' => $score];
        }
    }

    // Calculer SOSEO et DSEO (sur les stems, avant résolution)
    $top20Stems = array_slice($filtered, 0, 20, true);
    $totalBodyWords = $parsed['word_count'];

    $soseo = calculate_soseo($top20Stems, $termZones);
    $dseo = calculate_dseo($top20Stems, $bodyNgramCounts, $totalBodyWords, $primaryKw);

    // Construire term_details (top 20 termes avec infos détaillées)
    $termDetails = [];
    foreach ($top20Stems as $stemTerm => $score) {
        $zones = array_keys($termZones[$stemTerm] ?? []);
        $bodyCount = $bodyNgramCounts[$stemTerm] ?? 0;
        $wordCount = substr_count($stemTerm, ' ') + 1;
        $density = $totalBodyWords > 0 ? round($bodyCount / $totalBodyWords * 100, 2) : 0;

        $threshold = match ($wordCount) {
            1 => 3,
            2 => 2,
            default => 1.5,
        };

        if ($density > $threshold) {
            $status = 'sur-optimisé';
        } elseif (count($zones) <= 1) {
            $status = 'sous-optimisé';
        } else {
            $status = 'optimal';
        }

        $termDetails[] = [
            'term'       => resolve_surface_ngram($stemTerm, $ngramSurfaceMap, $surfaceMap),
            'score'      => round($score, 1),
            'zones'      => $zones,
            'body_count' => $bodyCount,
            'density'    => $density,
            'status'     => $status,
        ];
    }

    // Résoudre les formes de surface (stems → formes naturelles)
    $primaryKw = resolve_surface_ngram($primaryKw, $ngramSurfaceMap, $surfaceMap);
    foreach ($variants as &$v) {
        $v['term'] = resolve_surface_ngram($v['term'], $ngramSurfaceMap, $surfaceMap);
    }
    unset($v);
    $resolvedFiltered = [];
    foreach ($filtered as $stemTerm => $score) {
        $resolvedFiltered[resolve_surface_ngram($stemTerm, $ngramSurfaceMap, $surfaceMap)] = $score;
    }
    $filtered = $resolvedFiltered;

    // Intention de recherche
    $intent = detect_intent($primaryKw, $parsed);

    // Estimation concurrence
    $competition = estimate_competition($primaryKw);

    return [
        'primary_keyword' => $primaryKw,
        'primary_score'   => $primaryScore,
        'variants'        => $variants,
        'intent'          => $intent,
        'competition'     => $competition,
        'all_scores'      => array_slice($filtered, 0, 20, true),
        'soseo'           => round($soseo, 1),
        'dseo'            => round($dseo, 1),
        'term_details'    => $termDetails,
    ];
}

function detect_intent(string $keyword, array $parsed): array
{
    $allText = mb_strtolower($keyword . ' ' . $parsed['title'] . ' ' . implode(' ', $parsed['h1']));

    $scores = [
        'transactionnelle' => 0,
        'commerciale'      => 0,
        'navigationnelle'  => 0,
        'informationnelle' => 0,
    ];

    foreach (INTENT_TRANSACTIONAL as $w) {
        if (str_contains($allText, $w)) $scores['transactionnelle'] += 2;
    }
    foreach (INTENT_COMMERCIAL as $w) {
        if (str_contains($allText, $w)) $scores['commerciale'] += 2;
    }
    foreach (INTENT_NAVIGATIONAL as $w) {
        if (str_contains($allText, $w)) $scores['navigationnelle'] += 2;
    }

    // Heuristiques supplémentaires
    if (preg_match('/comment|guide|tutoriel|définition|qu.est.ce|pourquoi|explication/i', $allText)) {
        $scores['informationnelle'] += 3;
    }
    if (preg_match('/liste|top \d|meilleur|classement|comparatif/i', $allText)) {
        $scores['commerciale'] += 2;
    }

    // Si aucun signal clair, défaut = informationnelle
    $max = max($scores);
    if ($max === 0) {
        $scores['informationnelle'] = 1;
    }

    arsort($scores);
    $primary = array_key_first($scores);

    return [
        'type'  => $primary,
        'label' => ucfirst($primary),
        'score' => $scores[$primary],
    ];
}

function estimate_competition(string $keyword): array
{
    $wordCount = count(explode(' ', $keyword));
    $len = mb_strlen($keyword);

    // Plus un keyword est long/spécifique, moins il y a de concurrence
    if ($wordCount >= 4 || $len >= 30) {
        return ['level' => 'faible', 'label' => 'Faible', 'color' => '#22c55e'];
    } elseif ($wordCount >= 2 || $len >= 15) {
        return ['level' => 'moyen', 'label' => 'Moyen', 'color' => '#f97316'];
    } else {
        return ['level' => 'élevé', 'label' => 'Élevé', 'color' => '#ef4444'];
    }
}

// ─── Diagnostic SEO ────────────────────────────────────────────────────────

function generate_diagnostic(array $parsed, string $primaryKw, float $soseo = 0, float $dseo = 0): array
{
    $checks = [];
    $kwLower = mb_strtolower($primaryKw);
    $totalScore = 0;
    $maxScore = 0;

    // 1. Keyword dans le Title
    $maxScore += 15;
    $titleLower = mb_strtolower($parsed['title']);
    if (str_contains($titleLower, $kwLower)) {
        $checks[] = ['label' => 'Keyword dans le Title', 'status' => 'bon', 'message' => 'Le mot-clé principal est présent dans le titre.', 'points' => 15];
        $totalScore += 15;
    } else {
        // Vérifier si au moins un mot du keyword est présent
        $kwWords = explode(' ', $kwLower);
        $found = 0;
        foreach ($kwWords as $w) {
            if (mb_strlen($w) >= 3 && str_contains($titleLower, $w)) $found++;
        }
        if ($found > 0 && $found >= count($kwWords) / 2) {
            $checks[] = ['label' => 'Keyword dans le Title', 'status' => 'attention', 'message' => 'Le titre contient une partie du mot-clé mais pas la correspondance exacte.', 'points' => 8];
            $totalScore += 8;
        } else {
            $checks[] = ['label' => 'Keyword dans le Title', 'status' => 'mauvais', 'message' => 'Le mot-clé principal est absent du titre.', 'points' => 0];
        }
    }

    // 2. Cohérence Title ↔ H1
    $maxScore += 10;
    $h1Text = implode(' ', $parsed['h1']);
    $h1Lower = mb_strtolower($h1Text);
    if ($h1Text !== '' && $parsed['title'] !== '') {
        // Comparer les mots significatifs
        $titleTokens = tokenize($parsed['title']);
        $h1Tokens = tokenize($h1Text);
        $common = array_intersect($titleTokens, $h1Tokens);
        $ratio = count($common) / max(count($titleTokens), 1);
        if ($ratio >= 0.5) {
            $checks[] = ['label' => 'Cohérence Title ↔ H1', 'status' => 'bon', 'message' => 'Le Title et le H1 partagent un champ sémantique cohérent.', 'points' => 10];
            $totalScore += 10;
        } elseif ($ratio >= 0.25) {
            $checks[] = ['label' => 'Cohérence Title ↔ H1', 'status' => 'attention', 'message' => 'Le Title et le H1 ont peu de mots-clés en commun.', 'points' => 5];
            $totalScore += 5;
        } else {
            $checks[] = ['label' => 'Cohérence Title ↔ H1', 'status' => 'mauvais', 'message' => 'Le Title et le H1 traitent de sujets différents.', 'points' => 0];
        }
    } else {
        $checks[] = ['label' => 'Cohérence Title ↔ H1', 'status' => 'mauvais', 'message' => 'Title ou H1 manquant, impossible de vérifier la cohérence.', 'points' => 0];
    }

    // 3. Keyword dans la meta description
    $maxScore += 10;
    $metaLower = mb_strtolower($parsed['meta_description']);
    if ($parsed['meta_description'] === '') {
        $checks[] = ['label' => 'Keyword dans Meta Description', 'status' => 'mauvais', 'message' => 'Aucune meta description définie.', 'points' => 0];
    } elseif (str_contains($metaLower, $kwLower)) {
        $checks[] = ['label' => 'Keyword dans Meta Description', 'status' => 'bon', 'message' => 'Le mot-clé principal est présent dans la meta description.', 'points' => 10];
        $totalScore += 10;
    } else {
        $kwWords = explode(' ', $kwLower);
        $found = 0;
        foreach ($kwWords as $w) {
            if (mb_strlen($w) >= 3 && str_contains($metaLower, $w)) $found++;
        }
        if ($found > 0) {
            $checks[] = ['label' => 'Keyword dans Meta Description', 'status' => 'attention', 'message' => 'La meta description contient certains mots du keyword.', 'points' => 5];
            $totalScore += 5;
        } else {
            $checks[] = ['label' => 'Keyword dans Meta Description', 'status' => 'mauvais', 'message' => 'Le mot-clé est absent de la meta description.', 'points' => 0];
        }
    }

    // 4. Keyword dans l'URL
    $maxScore += 10;
    $urlText = mb_strtolower(implode(' ', $parsed['url_parts']));
    if (str_contains($urlText, $kwLower)) {
        $checks[] = ['label' => 'Keyword dans l\'URL', 'status' => 'bon', 'message' => 'Le mot-clé est présent dans l\'URL.', 'points' => 10];
        $totalScore += 10;
    } else {
        $kwWords = explode(' ', $kwLower);
        $found = 0;
        foreach ($kwWords as $w) {
            if (mb_strlen($w) >= 3 && str_contains($urlText, $w)) $found++;
        }
        if ($found > 0) {
            $checks[] = ['label' => 'Keyword dans l\'URL', 'status' => 'attention', 'message' => 'L\'URL contient une partie du mot-clé.', 'points' => 5];
            $totalScore += 5;
        } else {
            $checks[] = ['label' => 'Keyword dans l\'URL', 'status' => 'mauvais', 'message' => 'Le mot-clé est absent de l\'URL.', 'points' => 0];
        }
    }

    // 5. Richesse sémantique
    $maxScore += 10;
    $bodyWords = preg_split('/\s+/', mb_strtolower($parsed['body_text']), -1, PREG_SPLIT_NO_EMPTY);
    $uniqueWords = count(array_unique($bodyWords));
    $totalWords = count($bodyWords);
    $ratio = $totalWords > 0 ? $uniqueWords / $totalWords : 0;
    if ($ratio >= 0.4) {
        $checks[] = ['label' => 'Richesse sémantique', 'status' => 'bon', 'message' => sprintf('Bon ratio de diversité lexicale (%.0f%% mots uniques).', $ratio * 100), 'points' => 10];
        $totalScore += 10;
    } elseif ($ratio >= 0.25) {
        $checks[] = ['label' => 'Richesse sémantique', 'status' => 'attention', 'message' => sprintf('Diversité lexicale moyenne (%.0f%% mots uniques).', $ratio * 100), 'points' => 5];
        $totalScore += 5;
    } else {
        $checks[] = ['label' => 'Richesse sémantique', 'status' => 'mauvais', 'message' => sprintf('Faible diversité lexicale (%.0f%% mots uniques).', $ratio * 100), 'points' => 0];
    }

    // 6. Nombre de H1
    $maxScore += 10;
    $h1Count = count($parsed['h1']);
    if ($h1Count === 1) {
        $checks[] = ['label' => 'Unicité du H1', 'status' => 'bon', 'message' => 'Un seul H1, c\'est correct.', 'points' => 10];
        $totalScore += 10;
    } elseif ($h1Count === 0) {
        $checks[] = ['label' => 'Unicité du H1', 'status' => 'mauvais', 'message' => 'Aucun H1 trouvé sur la page.', 'points' => 0];
    } else {
        $checks[] = ['label' => 'Unicité du H1', 'status' => 'attention', 'message' => sprintf('%d balises H1 trouvées. Il devrait n\'y en avoir qu\'une seule.', $h1Count), 'points' => 4];
        $totalScore += 4;
    }

    // 7. Longueur du Title
    $maxScore += 10;
    $titleLen = mb_strlen($parsed['title']);
    if ($titleLen === 0) {
        $checks[] = ['label' => 'Longueur du Title', 'status' => 'mauvais', 'message' => 'Aucun Title défini.', 'points' => 0];
    } elseif ($titleLen <= 60 && $titleLen >= 30) {
        $checks[] = ['label' => 'Longueur du Title', 'status' => 'bon', 'message' => sprintf('Title de %d caractères (idéal : 30-60).', $titleLen), 'points' => 10];
        $totalScore += 10;
    } elseif ($titleLen <= 70) {
        $checks[] = ['label' => 'Longueur du Title', 'status' => 'attention', 'message' => sprintf('Title de %d caractères, légèrement au-dessus de la limite recommandée (60).', $titleLen), 'points' => 7];
        $totalScore += 7;
    } else {
        $checks[] = ['label' => 'Longueur du Title', 'status' => 'mauvais', 'message' => sprintf('Title de %d caractères, trop long (max recommandé : 60).', $titleLen), 'points' => 3];
        $totalScore += 3;
    }

    // 8. Longueur Meta Description
    $maxScore += 10;
    $metaLen = mb_strlen($parsed['meta_description']);
    if ($metaLen === 0) {
        $checks[] = ['label' => 'Longueur Meta Description', 'status' => 'mauvais', 'message' => 'Aucune meta description définie.', 'points' => 0];
    } elseif ($metaLen <= 160 && $metaLen >= 120) {
        $checks[] = ['label' => 'Longueur Meta Description', 'status' => 'bon', 'message' => sprintf('Meta description de %d caractères (idéal : 120-160).', $metaLen), 'points' => 10];
        $totalScore += 10;
    } elseif ($metaLen <= 170 && $metaLen >= 70) {
        $checks[] = ['label' => 'Longueur Meta Description', 'status' => 'attention', 'message' => sprintf('Meta description de %d caractères.', $metaLen), 'points' => 6];
        $totalScore += 6;
    } else {
        $checks[] = ['label' => 'Longueur Meta Description', 'status' => 'mauvais', 'message' => sprintf('Meta description de %d caractères (idéal : 120-160).', $metaLen), 'points' => 2];
        $totalScore += 2;
    }

    // 9. Keyword dans H1
    $maxScore += 15;
    if ($h1Text === '') {
        $checks[] = ['label' => 'Keyword dans le H1', 'status' => 'mauvais', 'message' => 'Pas de H1 pour vérifier la présence du keyword.', 'points' => 0];
    } elseif (str_contains($h1Lower, $kwLower)) {
        $checks[] = ['label' => 'Keyword dans le H1', 'status' => 'bon', 'message' => 'Le mot-clé principal est présent dans le H1.', 'points' => 15];
        $totalScore += 15;
    } else {
        $kwWords = explode(' ', $kwLower);
        $found = 0;
        foreach ($kwWords as $w) {
            if (mb_strlen($w) >= 3 && str_contains($h1Lower, $w)) $found++;
        }
        if ($found > 0 && $found >= count($kwWords) / 2) {
            $checks[] = ['label' => 'Keyword dans le H1', 'status' => 'attention', 'message' => 'Le H1 contient une partie du mot-clé principal.', 'points' => 8];
            $totalScore += 8;
        } else {
            $checks[] = ['label' => 'Keyword dans le H1', 'status' => 'mauvais', 'message' => 'Le mot-clé principal est absent du H1.', 'points' => 0];
        }
    }

    // 10. Optimisation sémantique (SOSEO)
    $maxScore += 10;
    if ($soseo >= 60) {
        $checks[] = ['label' => 'Optimisation sémantique', 'status' => 'bon', 'message' => sprintf('Bonne couverture sémantique (SOSEO : %.0f%%).', $soseo), 'points' => 10];
        $totalScore += 10;
    } elseif ($soseo >= 30) {
        $checks[] = ['label' => 'Optimisation sémantique', 'status' => 'attention', 'message' => sprintf('Couverture sémantique partielle (SOSEO : %.0f%%).', $soseo), 'points' => 5];
        $totalScore += 5;
    } else {
        $checks[] = ['label' => 'Optimisation sémantique', 'status' => 'mauvais', 'message' => sprintf('Couverture sémantique insuffisante (SOSEO : %.0f%%).', $soseo), 'points' => 0];
    }

    // 11. Risque de sur-optimisation (DSEO)
    $maxScore += 5;
    if ($dseo < 20) {
        $checks[] = ['label' => 'Risque de sur-optimisation', 'status' => 'bon', 'message' => sprintf('Pas de sur-optimisation détectée (DSEO : %.0f%%).', $dseo), 'points' => 5];
        $totalScore += 5;
    } elseif ($dseo <= 50) {
        $checks[] = ['label' => 'Risque de sur-optimisation', 'status' => 'attention', 'message' => sprintf('Risque modéré de sur-optimisation (DSEO : %.0f%%).', $dseo), 'points' => 3];
        $totalScore += 3;
    } else {
        $checks[] = ['label' => 'Risque de sur-optimisation', 'status' => 'mauvais', 'message' => sprintf('Sur-optimisation détectée (DSEO : %.0f%%).', $dseo), 'points' => 0];
    }

    $scorePercent = $maxScore > 0 ? round($totalScore / $maxScore * 100) : 0;

    return [
        'checks'     => $checks,
        'score'      => $totalScore,
        'max_score'  => $maxScore,
        'percentage' => $scorePercent,
    ];
}

// ─── Recommandations ───────────────────────────────────────────────────────

function generate_recommendations(array $parsed, array $diagnostic, string $primaryKw): array
{
    $recs = [];

    // Title optimisé
    $currentTitle = $parsed['title'];
    $titleLower = mb_strtolower($currentTitle);
    $kwLower = mb_strtolower($primaryKw);

    if (!str_contains($titleLower, $kwLower) || mb_strlen($currentTitle) > 60) {
        $kw = ucfirst($primaryKw);
        // Proposer un title optimisé
        if (mb_strlen($currentTitle) > 60) {
            // Raccourcir en gardant le keyword au début
            $proposed = $kw . ' — ' . mb_substr($currentTitle, 0, 55 - mb_strlen($kw));
            if (mb_strlen($proposed) > 60) {
                $proposed = mb_substr($proposed, 0, 57) . '...';
            }
        } else {
            $proposed = $kw . ' — ' . $currentTitle;
            if (mb_strlen($proposed) > 60) {
                $proposed = $kw . ' | ' . mb_substr($currentTitle, 0, 55 - mb_strlen($kw));
            }
        }
        $recs[] = [
            'type'    => 'title',
            'label'   => 'Title optimisé',
            'current' => $currentTitle,
            'proposed'=> $proposed,
            'reason'  => mb_strlen($currentTitle) > 60
                ? 'Le titre actuel dépasse 60 caractères et/ou ne contient pas le keyword principal.'
                : 'Le keyword principal devrait figurer dans le titre pour un meilleur référencement.',
        ];
    }

    // H1 optimisé
    $h1Text = implode(' ', $parsed['h1']);
    $h1Lower = mb_strtolower($h1Text);
    if ($h1Text === '' || !str_contains($h1Lower, $kwLower)) {
        $kw = ucfirst($primaryKw);
        if ($h1Text === '') {
            $proposed = $kw;
        } else {
            // Intégrer le keyword dans le H1 existant
            $proposed = $kw . ' : ' . $h1Text;
        }
        $recs[] = [
            'type'    => 'h1',
            'label'   => 'H1 optimisé',
            'current' => $h1Text ?: '(aucun)',
            'proposed'=> $proposed,
            'reason'  => $h1Text === ''
                ? 'Aucun H1 n\'est défini. Ajoutez un H1 contenant le mot-clé principal.'
                : 'Le H1 actuel ne contient pas le mot-clé principal identifié.',
        ];
    }

    // Meta description
    if ($parsed['meta_description'] === '' || !str_contains(mb_strtolower($parsed['meta_description']), $kwLower)) {
        $kw = ucfirst($primaryKw);
        if ($parsed['meta_description'] === '') {
            $proposed = "Découvrez tout sur $kwLower. Guide complet et informations essentielles pour comprendre $kwLower en détail.";
        } else {
            $proposed = $kw . ' — ' . mb_substr($parsed['meta_description'], 0, 150 - mb_strlen($kw));
        }
        if (mb_strlen($proposed) > 160) {
            $proposed = mb_substr($proposed, 0, 157) . '...';
        }
        $recs[] = [
            'type'    => 'meta_description',
            'label'   => 'Meta description optimisée',
            'current' => $parsed['meta_description'] ?: '(aucune)',
            'proposed'=> $proposed,
            'reason'  => $parsed['meta_description'] === ''
                ? 'Aucune meta description définie. Elle aide au CTR dans les résultats de recherche.'
                : 'La meta description ne mentionne pas le keyword principal.',
        ];
    }

    // Angle SEO si décalage Title/H1
    $titleTokens = tokenize($parsed['title']);
    $h1Tokens = tokenize($h1Text);
    if (!empty($titleTokens) && !empty($h1Tokens)) {
        $common = array_intersect($titleTokens, $h1Tokens);
        $ratio = count($common) / max(count($titleTokens), 1);
        if ($ratio < 0.3) {
            $recs[] = [
                'type'    => 'angle',
                'label'   => 'Angle SEO',
                'current' => 'Décalage entre Title et H1',
                'proposed'=> "Aligner le Title et le H1 autour du même mot-clé principal « $primaryKw » pour renforcer le signal sémantique.",
                'reason'  => 'Le Title et le H1 ciblent des thématiques différentes, ce qui dilue le signal SEO.',
            ];
        }
    }

    // Contenu trop court
    if ($parsed['word_count'] < 300) {
        $recs[] = [
            'type'    => 'content',
            'label'   => 'Enrichir le contenu',
            'current' => sprintf('%d mots', $parsed['word_count']),
            'proposed'=> 'Viser au minimum 300 mots pour un contenu de qualité, idéalement 800+ mots pour un article informatif.',
            'reason'  => 'Un contenu trop court limite les chances de positionnement sur des requêtes compétitives.',
        ];
    }

    return $recs;
}

// ─── Orchestrateur ─────────────────────────────────────────────────────────

function handle_seo_form(): array
{
    $data = [
        'hasPost'          => false,
        'url'              => '',
        'error'            => '',
        'parsed'           => [],
        'keywords'         => [],
        'diagnostic'       => [],
        'recommendations'  => [],
        'fetchInfo'        => [],
    ];

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['url'])) {
        return $data;
    }

    $data['hasPost'] = true;
    $url = trim($_POST['url']);

    // Ajouter https:// si absent
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $data['url'] = $url;

    // Vérifier URL valide
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $data['error'] = 'URL invalide. Veuillez saisir une URL complète (ex: https://example.com/page).';
        return $data;
    }

    // 1. Fetch
    $fetch = fetch_page($url);
    $data['fetchInfo'] = $fetch;

    if ($fetch['status'] !== 'ok') {
        $data['error'] = $fetch['error'] ?? 'Impossible de récupérer la page.';
        return $data;
    }

    // 2. Parse
    $parsed = parse_page($fetch['html'], $fetch['finalUrl']);
    $data['parsed'] = $parsed;

    // 3. Analyze keywords
    $keywords = analyze_keywords($parsed);
    $data['keywords'] = $keywords;

    // 4. Diagnostic
    $primaryKw = $keywords['primary_keyword'];
    $diagnostic = generate_diagnostic($parsed, $primaryKw, $keywords['soseo'] ?? 0, $keywords['dseo'] ?? 0);
    $data['diagnostic'] = $diagnostic;

    // 5. Recommendations
    $recommendations = generate_recommendations($parsed, $diagnostic, $primaryKw);
    $data['recommendations'] = $recommendations;

    return $data;
}
