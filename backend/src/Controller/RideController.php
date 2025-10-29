<?php
namespace App\Controller;

use App\Document\SearchLog;
use App\Service\SearchLogger;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RideController
{
    public function __construct(private SearchLogger $logger) {}

    #[Route('/api/rides', name: 'api_rides_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $from = (string)$request->query->get('from', '');
        $to   = (string)$request->query->get('to', '');
        $date = (string)$request->query->get('date', '');

        // MOCK minimal pour démarrer
        $mock = [[
            'id'=>1,
            'driver'=>['pseudo'=>'Alice','photo'=>'/img/alice.png','note'=>4.8],
            'seats_left'=>2, 'price'=>18.5,
            'start_at'=>'2025-11-01T08:00:00+01:00', 'end_at'=>'2025-11-01T10:30:00+01:00',
            'eco'=>true,
        ],[
            'id'=>2,
            'driver'=>['pseudo'=>'Bob','photo'=>'/img/bob.png','note'=>4.3],
            'seats_left'=>0, 'price'=>15,
            'start_at'=>'2025-11-01T09:00:00+01:00', 'end_at'=>'2025-11-01T11:50:00+01:00',
            'eco'=>false,
        ]];

        // Règle métier: n’afficher que les trajets avec >= 1 place restante
        $results = array_values(array_filter($mock, fn($r)=> ($r['seats_left']??0) >= 1));

        // Journalisation NoSQL (Mongo)
        $this->logger->log($from, $to, $date, \count($results), null);

        if (!$results) {
            return new JsonResponse([
                'suggestions' => [['date' => (new \DateTimeImmutable($date ?: 'now +1 day'))->format('Y-m-d'), 'count' => 0]],
                'query' => compact('from','to','date'),
            ]);
        }
        return new JsonResponse($results);
    }

    // Endpoint debug (optionnel) pour vérifier les logs
    #[Route('/api/debug/search-logs', name: 'api_debug_search_logs', methods: ['GET'])]
    public function logs(DocumentManager $dm): JsonResponse
    {
        $docs = $dm->getRepository(SearchLog::class)->findBy([], ['createdAt'=>'DESC'], 5);
        $out=[];
        foreach ($docs as $d) {
            $out[] = [
                'from'=>$d->getFrom(),
                'to'=>$d->getTo(),
                'date'=>$d->getDate(),
                'resultCount'=>$d->getResultCount(),
                'createdAt'=>$d->getCreatedAt()->format(DATE_ATOM),
            ];
        }
        return new JsonResponse($out);
    }
}
