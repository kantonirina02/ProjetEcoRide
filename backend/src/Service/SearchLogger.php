<?php

namespace App\Service;

use App\Document\SearchLog;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\RequestStack;

class SearchLogger
{
    public function __construct(
        private DocumentManager $dm,
        private RequestStack $stack
    ) {}

    public function log(string $from, string $to, string $date, int $resultCount, ?string $userId = null): void
    {
        $req = $this->stack->getCurrentRequest();

        $log = (new SearchLog())
            ->setFrom($from)
            ->setTo($to)
            ->setDate($date)
            ->setResultCount($resultCount)
            ->setUserId($userId)
            ->setClientIp($req?->getClientIp())
            ->setUserAgent($req?->headers->get('User-Agent'));

        $this->dm->persist($log);
        $this->dm->flush();
    }
}
