<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfilePhotoTest extends WebTestCase
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

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $fixtures = new AppFixtures($hasher);
        $fixtures->load($em);

        static::ensureKernelShutdown();
    }

    private function login($client, string $email): void
    {
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => 'Passw0rd!'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
    }

    public function testProfilePhotoUploadAndDelete(): void
    {
        $client = static::createClient();
        $this->login($client, 'user1@mail.test');

        $tmpFile = tempnam(sys_get_temp_dir(), 'photo');
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
        file_put_contents($tmpFile, $pixel);

        $uploaded = new UploadedFile(
            $tmpFile,
            'avatar.png',
            'image/png',
            null,
            true
        );

        $client->request(
            'POST',
            '/api/me/photo',
            [],
            ['photo' => $uploaded],
            ['HTTP_ACCEPT' => 'application/json']
        );

        self::assertResponseStatusCodeSame(201);
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok'] ?? false);
        $photoData = $payload['photo'] ?? '';
        self::assertNotEmpty($photoData);
        self::assertStringStartsWith('data:image/png;base64,', $photoData);

        $client->request(
            'GET',
            '/api/me/overview',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
        self::assertResponseIsSuccessful();
        $overview = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($photoData, $overview['user']['photo'] ?? null);

        $client->request(
            'DELETE',
            '/api/me/photo',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        self::assertResponseIsSuccessful();
        $deletePayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($deletePayload['ok'] ?? false);
        self::assertNull($deletePayload['photo'] ?? null);

        $client->request(
            'GET',
            '/api/me/overview',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
        self::assertResponseIsSuccessful();
        $after = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($after['user']['photo'] ?? null);

        @unlink($tmpFile);
    }
}
