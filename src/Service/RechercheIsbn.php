<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RechercheIsbn
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire('%env(default::GOOGLE_BOOKS_API_KEY)%')]
        private ?string $cleGoogle = null,
    ) {
    }

    // Essaie les catalogues dans l’ordre ; une panne ne bloque pas les sources suivantes.
    public function rechercher(string $isbn): array
    {
        $erreurs = 0;
        $reponses = 0;
        $livreRetenu = null;
        $sourceRetenue = null;
        foreach (['openLibrary', 'googleBooks', 'bnf'] as $source) {
            if ($source === 'googleBooks' && !$this->cleGoogle) continue;
            try {
                $livre = $this->$source($isbn);
                $reponses++;
                if ($livre === null) continue;
                $auteurs = [];
                foreach ($livre['authors'] ?? [] as $auteur) {
                    $nom = is_string($auteur) ? $auteur : ($auteur['name'] ?? '');
                    if (is_string($nom) && trim($nom) !== '') $auteurs[] = ['name' => trim($nom)];
                }
                if ($livreRetenu === null) {
                    $livreRetenu = $livre;
                    $livreRetenu['authors'] = $auteurs;
                    $sourceRetenue = $source;
                } elseif ($auteurs) {
                    // Complète uniquement les auteurs manquants avec une autre notice du même ISBN.
                    $livreRetenu['authors'] = $auteurs;
                }
                if ($livreRetenu['authors']) return ['livre' => $livreRetenu, 'source' => $sourceRetenue];
            } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface | \UnexpectedValueException) {
                $erreurs++;
            }
        }
        if ($livreRetenu !== null) return ['livre' => $livreRetenu, 'source' => $sourceRetenue];

        return [
            'livre' => null,
            'indisponible' => $erreurs > 0,
            'aucunCatalogueDisponible' => $reponses === 0 && $erreurs > 0,
        ];
    }

    // Borne la durée des appels pour pouvoir passer rapidement au catalogue suivant.
    private function requete(string $url, array $query, bool $json = true): array|string
    {
        $reponse = $this->client->request('GET', $url, [
            'query' => $query,
            'timeout' => 5,
            'max_duration' => 7,
            'headers' => ['User-Agent' => 'Incipit/1.0 (https://incipit.alwaysdata.net)'],
        ]);

        return $json ? $reponse->toArray() : $reponse->getContent();
    }

    // Récupère la notice Open Library, dont le format sert de référence au formulaire.
    private function openLibrary(string $isbn): ?array
    {
        $donnees = $this->requete('https://openlibrary.org/api/books', [
            'bibkeys' => 'ISBN:'.$isbn, 'jscmd' => 'data', 'format' => 'json',
        ]);
        $livre = $donnees['ISBN:'.$isbn] ?? null;

        return !empty($livre['title']) ? $livre : null;
    }

    // Ramène Google Books au format utilisé par le formulaire partagé.
    private function googleBooks(string $isbn): ?array
    {
        $donnees = $this->requete('https://www.googleapis.com/books/v1/volumes', [
            'q' => 'isbn:'.$isbn, 'key' => $this->cleGoogle, 'maxResults' => 10,
        ]);
        foreach ($donnees['items'] ?? [] as $item) {
            $volume = $item['volumeInfo'] ?? [];
            $identifiants = [];
            foreach ($volume['industryIdentifiers'] ?? [] as $identifiant) {
                $valeur = str_replace(['-', ' '], '', $identifiant['identifier'] ?? '');
                $identifiants[strtolower($identifiant['type'] ?? '')][] = $valeur;
            }
            // Vérifie l’édition pour ne pas préremplir le formulaire avec un autre ISBN.
            if (empty($volume['title']) || !in_array($isbn, array_merge(...array_values($identifiants)), true)) {
                continue;
            }
            $couverture = $volume['imageLinks']['thumbnail'] ?? null;

            return [
                'title' => $volume['title'],
                'subtitle' => $volume['subtitle'] ?? null,
                'authors' => array_map(static fn (string $nom): array => ['name' => $nom], $volume['authors'] ?? []),
                'identifiers' => $identifiants,
                'publishers' => isset($volume['publisher']) ? [['name' => $volume['publisher']]] : [],
                'number_of_pages' => $volume['pageCount'] ?? null,
                'publish_date' => $volume['publishedDate'] ?? null,
                'cover' => ['large' => is_string($couverture) ? preg_replace('~^http://~', 'https://', $couverture) : null],
            ];
        }

        return null;
    }

    // Lit les notices Dublin Core de la BnF sans charger de ressources XML externes.
    private function bnf(string $isbn): ?array
    {
        $xml = $this->requete('https://catalogue.bnf.fr/api/SRU', [
            'version' => '1.2', 'operation' => 'searchRetrieve',
            'query' => 'bib.isbn all "'.$isbn.'"',
            'recordSchema' => 'dublincore', 'maximumRecords' => 1,
        ], false);
        $document = new \DOMDocument();
        $ancien = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                throw new \UnexpectedValueException('Réponse BnF invalide.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($ancien);
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('srw', 'http://www.loc.gov/zing/srw/');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        if ($xpath->evaluate('count(//srw:diagnostic | //*[local-name()="diagnostic"])') > 0) {
            throw new \UnexpectedValueException('Recherche BnF indisponible.');
        }
        // Extrait les champs bibliographiques non vides de la réponse Dublin Core.
        $valeurs = static function (string $champ) use ($xpath): array {
            $resultats = [];
            foreach ($xpath->query('//srw:recordData//dc:'.$champ) as $noeud) {
                $resultats[] = trim($noeud->textContent);
            }

            return array_values(array_filter($resultats));
        };
        $titre = $valeurs('title')[0] ?? null;
        if (!$titre) {
            return null;
        }

        // Certaines notices placent l’auteur parmi les contributeurs ; exclut les rôles de traduction et d’illustration.
        $nomsAuteurs = $valeurs('creator');
        foreach ($valeurs('contributor') as $nom) {
            if (preg_match('/\. (?:Auteur(?: du texte)?|Fonction indéterminée|Écrivain)\b/u', $nom)) {
                $nomsAuteurs[] = $nom;
            }
        }
        $auteurs = [];
        foreach ($nomsAuteurs as $nom) {
            if (preg_match('/\. (?:Traducteur|Illustrateur|Préfacier|Postfacier|Éditeur|Commentateur)\b/u', $nom)) continue;
            // Retire les mentions bibliographiques avant de remettre le prénom devant le nom.
            $nom = trim(preg_replace('/\s*\([^)]*\).*$/u', '', $nom));
            $nom = trim(preg_replace('/\. (?:Auteur(?: du texte)?|Fonction indéterminée|Écrivain).*$/u', '', $nom));
            $parties = explode(', ', $nom, 2);
            $nom = count($parties) === 2 ? $parties[1].' '.$parties[0] : $nom;
            if ($nom !== '') $auteurs[mb_strtolower($nom)] = ['name' => $nom];
        }

        return [
            'title' => trim(explode(' / ', $titre, 2)[0]),
            'authors' => array_values($auteurs),
            'publishers' => array_map(static fn (string $nom): array => ['name' => $nom], $valeurs('publisher')),
            'publish_date' => $valeurs('date')[0] ?? null,
        ];
    }
}
