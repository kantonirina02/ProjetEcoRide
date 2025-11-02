<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RideFlowTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required to run functional tests.');
            return;
        }
        static::ensureKernelShutdown();
        $kernel = static::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $fixtures = new AppFixtures($hasher);
        $fixtures->load($entityManager);

        static::ensureKernelShutdown();
    }

    private function login($client, string $email, string $password = 'Passw0rd!'): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'] ?? false);
    }

    private function logout($client): void
    {
        $client->request('POST', '/api/auth/logout');
        self::assertResponseIsSuccessful();
    }

    public function testLoginEndpoint(): void
    {
        $client = static::createClient();
        $this->login($client, 'user1@mail.test');
    }

    public function testRideCreationBookingAndCancellation(): void
    {
        $client = static::createClient();
        $this->login($client, 'user1@mail.test');

        $client->request(
            'GET',
            '/api/me/vehicles',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
        self::assertResponseIsSuccessful();
        $vehiclesPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $vehicleId = $vehiclesPayload['vehicles'][0]['id'] ?? null;
        self::assertNotNull($vehicleId, 'Fixtures must provide at least one vehicle for the driver.');

        $start = (new \DateTimeImmutable('+2 days'))->setTime(9, 0);
        $end = $start->modify('+3 hours');

        $ridePayload = [
            'fromCity' => 'Paris',
            'toCity' => 'Lyon',
            'startAt' => $start->format('Y-m-d H:i'),
            'endAt' => $end->format('Y-m-d H:i'),
            'price' => 25,
            'allowSmoker' => false,
            'allowAnimals' => false,
            'vehicleId' => $vehicleId,
        ];

        $client->request(
            'POST',
            '/api/rides',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($ridePayload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $rideResponse = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $rideResponse);
        self::assertArrayHasKey('vehicle', $rideResponse);

        $rideId = $rideResponse['id'];
        self::assertEquals(98.0, (float)($rideResponse['balance'] ?? 0));

        $this->logout($client);
        $this->login($client, 'employee@mail.test');

        $client->request(
            'POST',
            sprintf('/api/rides/%d/book', $rideId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['seats' => 1, 'confirm' => true], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $bookingResponse = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($bookingResponse['ok'] ?? false);
        self::assertSame($rideId, $bookingResponse['rideId'] ?? null);

        $client->request(
            'DELETE',
            sprintf('/api/rides/%d/book', $rideId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        self::assertResponseIsSuccessful();
        $cancelResponse = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($cancelResponse['ok'] ?? false);
        self::assertSame($rideId, $cancelResponse['rideId'] ?? null);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            static::ensureKernelShutdown();
        }
        parent::tearDown();
    }
}
