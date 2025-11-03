<?php



namespace App\Controller;



use App\Entity\Brand;

use App\Entity\Ride;

use App\Entity\User;

use App\Entity\Vehicle;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\File\UploadedFile;

use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Annotation\Route;



#[Route('/api/me', name: 'api_account_')]

class AccountController extends AbstractController

{

    #[Route('/overview', name: 'overview', methods: ['GET'])]

    public function overview(Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['auth' => false], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['auth' => false], Response::HTTP_UNAUTHORIZED);

        }



        $vehicleRepo = $em->getRepository(Vehicle::class);

        $vehicles = $vehicleRepo->findBy(['owner' => $user], ['model' => 'ASC']);

        $vehicleData = array_map(static function (Vehicle $vehicle): array {

            $brand = $vehicle->getBrand();

            return [

                'id'     => $vehicle->getId(),

                'brand'  => $brand?->getName(),

                'model'  => $vehicle->getModel(),

                'energy' => $vehicle->getEnergy(),

                'seats'  => $vehicle->getSeatsTotal(),

                'color'  => $vehicle->getColor(),

                'eco'    => (bool)($vehicle->isEco() ?? false),

                'plate'  => $vehicle->getPlate(),

            ];

        }, $vehicles);



        $preferences = array_merge(

            [

                'allowSmoker'  => false,

                'allowAnimals' => false,

                'musicStyle'   => null,

            ],

            $user->getDriverPreferences()

        );



        return $this->json([

            'auth'        => true,

            'user'        => [

                'id'      => $user->getId(),

                'email'   => $user->getEmail(),

                'pseudo'  => $user->getPseudo(),

                'photo'   => $user->getProfilePhoto(),

                'roles'   => $user->getRoles(),

                'credits' => $user->getCreditsBalance(),

            ],

            'preferences' => $preferences,

            'vehicles'    => $vehicleData,

        ]);

    }



    #[Route('/photo', name: 'photo_upload', methods: ['POST', 'DELETE'])]

    public function managePhoto(Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);

        }



        if ($request->isMethod('DELETE')) {

            $user->setProfilePhoto(null);

            $em->persist($user);

            $em->flush();



            return $this->json(['ok' => true, 'photo' => null]);

        }



        /** @var UploadedFile|null $file */

        $file = $request->files->get('photo');

        if (!$file instanceof UploadedFile) {

            return $this->json(['error' => 'Aucun fichier reu'], Response::HTTP_BAD_REQUEST);

        }

        if (!$file->isValid()) {

            return $this->json(['error' => 'Fichier invalide', 'detail' => $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);

        }



        $mime = (string) $file->getMimeType();

        if ($mime === '' || !str_starts_with($mime, 'image/')) {

            return $this->json(['error' => 'Le fichier doit être une image'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);

        }

        $size = $file->getSize() ?? 0;

        if ($size > 5 * 1024 * 1024) {

            return $this->json(['error' => 'Image trop volumineuse (max 5 Mo)'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);

        }



        $content = @file_get_contents($file->getPathname());

        if ($content === false) {

            return $this->json(['error' => 'Impossible de lire le fichier téléchargé'], Response::HTTP_INTERNAL_SERVER_ERROR);

        }



        $encoded = base64_encode($content);

        $dataUri = sprintf('data:%s;base64,%s', $mime ?: 'application/octet-stream', $encoded);



        $user->setProfilePhoto($dataUri);

        $em->persist($user);

        $em->flush();



        return $this->json(['ok' => true, 'photo' => $dataUri], Response::HTTP_CREATED);

    }



    #[Route('/roles', name: 'roles', methods: ['POST'])]

    public function updateRoles(Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);

        }



        $raw = $request->getContent() ?? '';

        try {

            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];

        } catch (\JsonException) {

            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);

        }



        $wantDriver = isset($payload['driver']) ? (bool)$payload['driver'] : null;

        if ($wantDriver === null) {

            return $this->json(['error' => 'driver flag required'], Response::HTTP_BAD_REQUEST);

        }



        $roles = $user->getRoles();

        if ($wantDriver) {

            if (!in_array('ROLE_DRIVER', $roles, true)) {

                $roles[] = 'ROLE_DRIVER';

            }

        } else {

            $roles = array_values(array_filter($roles, static fn(string $role): bool => $role !== 'ROLE_DRIVER'));

        }



        $user->setRoles($roles);

        $em->persist($user);

        $em->flush();



        return $this->json([

            'ok'    => true,

            'roles' => $user->getRoles(),

        ]);

    }



    #[Route('/preferences', name: 'preferences', methods: ['PUT', 'PATCH'])]

    public function updatePreferences(Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);

        }



        $raw = $request->getContent() ?? '';

        try {

            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];

        } catch (\JsonException) {

            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);

        }



        $current = $user->getDriverPreferences();

        if (isset($payload['allowSmoker'])) {

            $current['allowSmoker'] = (bool)$payload['allowSmoker'];

        }

        if (isset($payload['allowAnimals'])) {

            $current['allowAnimals'] = (bool)$payload['allowAnimals'];

        }

        if (array_key_exists('musicStyle', $payload)) {

            $music = trim((string)$payload['musicStyle']);

            $current['musicStyle'] = $music !== '' ? $music : null;

        }



        $user->setDriverPreferences($current);

        $em->persist($user);

        $em->flush();



        return $this->json([

            'ok'          => true,

            'preferences' => $user->getDriverPreferences(),

        ]);

    }



    #[Route('/vehicles', name: 'vehicle_save', methods: ['POST'])]

    public function saveVehicle(Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);

        }



        $raw = $request->getContent() ?? '';

        try {

            $payload = $raw !== '' ? json_decode($raw, true, 512, \JSON_THROW_ON_ERROR) : [];

        } catch (\JsonException) {

            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);

        }



        $vehicleId = isset($payload['id']) ? (int)$payload['id'] : null;

        $brandName = trim((string)($payload['brand'] ?? ''));

        $model     = trim((string)($payload['model'] ?? ''));

        $seats     = isset($payload['seats']) ? max(1, (int)$payload['seats']) : 4;

        $energy    = trim((string)($payload['energy'] ?? 'electric'));

        $color     = isset($payload['color']) ? trim((string)$payload['color']) : null;

        $plate     = isset($payload['plate']) ? trim((string)$payload['plate']) : null;

        $eco       = (bool)($payload['eco'] ?? false);



        $errors = [];

        if ($brandName === '') {

            $errors[] = 'brand is required';

        }

        if ($model === '') {

            $errors[] = 'model is required';

        }

        if ($energy === '') {

            $errors[] = 'energy is required';

        }

        if ($errors) {

            return $this->json(['error' => 'Validation failed', 'details' => $errors], Response::HTTP_BAD_REQUEST);

        }



        $vehicleRepo = $em->getRepository(Vehicle::class);



        if ($vehicleId) {

            /** @var Vehicle|null $vehicle */

            $vehicle = $vehicleRepo->find($vehicleId);

            if (!$vehicle || $vehicle->getOwner()?->getId() !== $user->getId()) {

                return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);

            }

        } else {

            $vehicle = new Vehicle();

            $vehicle->setOwner($user);

        }



        $brandRepo = $em->getRepository(Brand::class);

        $brand = $brandRepo->findOneBy(['name' => $brandName]);

        if (!$brand) {

            $brand = (new Brand())->setName($brandName);

            $em->persist($brand);

        }



        $vehicle

            ->setBrand($brand)

            ->setModel($model)

            ->setSeatsTotal($seats)

            ->setEnergy($energy)

            ->setColor($color !== '' ? $color : null)

            ->setEco($eco)

            ->setPlate($plate !== '' ? $plate : null);



        if (!$vehicle->getFirstRegistrationAt()) {

            $vehicle->setFirstRegistrationAt(new \DateTimeImmutable('2019-01-01'));

        }



        $em->persist($vehicle);

        $em->flush();



        return $this->json([

            'ok'      => true,

            'vehicle' => [

                'id'     => $vehicle->getId(),

                'brand'  => $vehicle->getBrand()?->getName(),

                'model'  => $vehicle->getModel(),

                'seats'  => $vehicle->getSeatsTotal(),

                'energy' => $vehicle->getEnergy(),

                'color'  => $vehicle->getColor(),

                'eco'    => (bool)($vehicle->isEco() ?? false),

                'plate'  => $vehicle->getPlate(),

            ],

        ]);

    }



    #[Route('/vehicles/{id<\d+>}', name: 'vehicle_delete', methods: ['DELETE'])]

    public function deleteVehicle(int $id, Request $request, EntityManagerInterface $em): JsonResponse

    {

        $sessionUserId = (int)($request->getSession()->get('user_id') ?? 0);

        if ($sessionUserId <= 0) {

            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var User|null $user */

        $user = $em->getRepository(User::class)->find($sessionUserId);

        if (!$user) {

            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);

        }



        /** @var Vehicle|null $vehicle */

        $vehicle = $em->getRepository(Vehicle::class)->find($id);

        if (!$vehicle || $vehicle->getOwner()?->getId() !== $user->getId()) {

            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);

        }



        // Prevent removing vehicle if linked to future rides

        $qb = $em->createQueryBuilder()

            ->select('COUNT(r.id)')

            ->from(Ride::class, 'r')

            ->where('r.vehicle = :vehicle')

            ->andWhere('r.startAt >= :now')

            ->setParameters([

                'vehicle' => $vehicle,

                'now'     => new \DateTimeImmutable('now'),

            ]);



        $futureCount = (int)$qb->getQuery()->getSingleScalarResult();

        if ($futureCount > 0) {

            return $this->json(['error' => 'Vehicle has upcoming rides'], Response::HTTP_CONFLICT);

        }



        $em->remove($vehicle);

        $em->flush();



        return $this->json(['ok' => true]);

}



}

