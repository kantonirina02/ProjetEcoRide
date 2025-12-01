<?php

namespace App\Service;

use App\Document\SearchLog;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\RequestStack;

final class SearchLogger
{
    public function __construct(
        private readonly RequestStack $stack,
        private readonly ?DocumentManager $dm = null
    ) {}

    /**
     * Log d'une recherche de covoiturages.
     */
    public function log(
        ?string $from = null,
        ?string $to = null,
        ?string $date = null,
        ?int $resultCount = null,
        ?int $userId = null
    ): void {
        try {
            if (!$this->dm instanceof DocumentManager) {
                return; // MongoDB non dispo en local
            }
            $req = $this->stack->getCurrentRequest();

            $log = (new SearchLog())
                ->setFrom($from)
                ->setTo($to)
                ->setDate($date)
                ->setResultCount($resultCount ?? 0)
                ->setUserId($userId !== null ? (string)$userId : null)
                ->setClientIp($req?->getClientIp())
                ->setUserAgent($req?->headers->get('User-Agent'));

            $this->dm->persist($log);
            $this->dm->flush();
        } catch (\Throwable $e) {
            // on ignore toute erreur de log (pas de remontée pour ne pas casser l'API)
        }
    }
}
