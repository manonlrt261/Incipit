<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RechercheCouvertures
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(service: 'cache.app')]
        private CacheInterface $cache,
        #[Autowire('%env(default::SERPAPI_API_KEY)%')]
        private ?string $cle = null,
    ) {
    }

    // Réutilise les recherches identiques pendant une heure pour économiser le quota SerpApi.
    public function rechercher(string $recherche): array
    {
        if (!$this->cle) {
            throw new \RuntimeException('La recherche de couvertures nécessite la configuration de SerpApi sur le serveur.', 503);
        }
        $identifiant = 'couvertures_serpapi_v1_'.hash('sha256', $this->cle.'|'.mb_strtolower($recherche));

        return $this->cache->get($identifiant, function (ItemInterface $item) use ($recherche): array {
            $item->expiresAfter(3600);
            try {
                $reponse = $this->client->request('GET', 'https://serpapi.com/search.json', [
                    'query' => [
                        'engine' => 'google_images',
                        'q' => $recherche.' couverture livre',
                        'api_key' => $this->cle,
                        'hl' => 'fr',
                        'gl' => 'fr',
                        'safe' => 'active',
                    ],
                    'timeout' => 10,
                    'max_duration' => 20,
                ]);
                if ($reponse->getStatusCode() === 429) {
                    throw new \RuntimeException('Le quota de recherche de couvertures est atteint. Réessaie plus tard ou importe une image.', 429);
                }
                if ($reponse->getStatusCode() === 401 || $reponse->getStatusCode() === 403) {
                    throw new \RuntimeException('La clé SerpApi doit être vérifiée sur le serveur.', 503);
                }
                $donnees = $reponse->toArray();
            } catch (ExceptionInterface $exception) {
                // Ne transmet jamais une erreur du fournisseur qui pourrait contenir l’URL avec la clé.
                throw new \RuntimeException('La recherche de couvertures est momentanément indisponible.', 502);
            }
            if (!empty($donnees['error'])) {
                throw new \RuntimeException('SerpApi ne peut pas effectuer cette recherche pour le moment.', 502);
            }

            // Ne renvoie que les données nécessaires à la sélection ; ignore les doublons et URL non sécurisées.
            $images = [];
            foreach ($donnees['images_results'] ?? [] as $image) {
                $original = $image['original'] ?? null;
                if (!$this->urlImageValide($original) || isset($images[$original])) continue;
                $miniature = $image['thumbnail'] ?? null;
                $images[$original] = [
                    'titre' => mb_substr((string) ($image['title'] ?? 'Couverture'), 0, 255),
                    'url' => $original,
                    'miniature' => $this->urlImageValide($miniature) ? $miniature : $original,
                ];
                if (count($images) === 30) break;
            }

            return array_values($images);
        });
    }

    private function urlImageValide(mixed $url): bool
    {
        return is_string($url) && strlen($url) <= 2048
            && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && !parse_url($url, PHP_URL_USER)
            && !str_contains($url, (string) $this->cle);
    }
}
