<?php

namespace App\Entity;

use App\Repository\BrandRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BrandRepository::class)]
#[ORM\Table(name: 'brands')]
#[ORM\UniqueConstraint(name: 'uniq_brands_name', columns: ['name'])]
class Brand
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    /** @var Collection<int, Vehicle> */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'brand')]
    private Collection $vehicles;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, Vehicle> */
    public function getVehicles(): Collection { return $this->vehicles; }
    public function addVehicle(Vehicle $vehicle): self
    { if(!$this->vehicles->contains($vehicle)){ $this->vehicles->add($vehicle); $vehicle->setBrand($this);} return $this; }
    public function removeVehicle(Vehicle $vehicle): self
    { if($this->vehicles->removeElement($vehicle) && $vehicle->getBrand() === $this){ $vehicle->setBrand(null);} return $this; }
}
