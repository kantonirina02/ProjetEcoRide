<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\DataFixtures\AppFixtures;

class AdminMetricsTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required to run functional tests.');
        }

        static::ensureKernelShutdown();
        $kernel = static::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $fixtures = new AppFixtures($hasher);
        $fixtures->load($em);

        static::ensureKernelShutdown();
    }

    private function loginAdmin($client): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'admin@mail.test', 'password' => 'Passw0rd!'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
    }

    public function testAdminMetricsSecuredEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/metrics');
        self::assertResponseStatusCodeSame(401);

        $this->loginAdmin($client);

        $client->request('GET', '/api/admin/metrics');
        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('series', $payload);
        self::assertArrayHasKey('users', $payload);
    }

    public function testModerationListRequiresEmployeeRole(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/moderation/reviews');
        self::assertResponseStatusCodeSame(401);

        // login as employee
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'employee@mail.test', 'password' => 'Passw0rd!'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/moderation/reviews?status=pending');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('reviews', $data);
        self::assertIsArray($data['reviews']);
    }
}
