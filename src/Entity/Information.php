<?php

namespace App\Entity;

use App\Repository\InformationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InformationRepository::class)
 */
class Information
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $full_name;

    /**
     * @ORM\Column(type="bigint")
     */
    private $Identification;

    /**
     * @ORM\Column(type="string", length=45)
     */
    private $fondo;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(type="string", unique=true)
     */
    private $uniq_id;

    /**
     * @ORM\Column(type="float")
     */
    private $total_weeks;

    /**
     * @ORM\OneToMany(targetEntity=Data::class, mappedBy="info")
     */
    private $data;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="information")
     */
    private $user;

    /**
     * @ORM\Column(type="date")
     */
    private $birthdate;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $resume = [];

    public function __construct()
    {
        $this->data = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(string $full_name): self
    {
        $this->full_name = $full_name;

        return $this;
    }

    public function getIdentification(): ?string
    {
        return $this->Identification;
    }

    public function setIdentification(string $Identification): self
    {
        $this->Identification = $Identification;
        $this->setUniqId($Identification . '-' . bin2hex(random_bytes(3)));
        return $this;
    }

    private function setUniqId(string $uniq_id): self
    {
        $this->uniq_id = $uniq_id;

        return $this;
    }

    public function getFondo(): ?string
    {
        return $this->fondo;
    }

    public function setFondo(string $fondo): self
    {
        $this->fondo = $fondo;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUniqId(): ?string
    {
        return $this->uniq_id;
    }

    public function getTotalWeeks(): ?float
    {
        return $this->total_weeks;
    }

    public function setTotalWeeks(float $total_weeks): self
    {
        $this->total_weeks = $total_weeks;

        return $this;
    }

    /**
     * @return Collection|Data[]
     */
    public function getData(): Collection
    {
        return $this->data;
    }

    public function addData(Data $data): self
    {
        if (!$this->data->contains($data)) {
            $this->data[] = $data;
            $data->setInfo($this);
        }

        return $this;
    }

    public function removeData(Data $data): self
    {
        if ($this->data->removeElement($data)) {
            // set the owning side to null (unless already changed)
            if ($data->getInfo() === $this) {
                $data->setInfo(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEdad()
    {
        $hoy = new \DateTime();
        $nac = $this->getBirthdate();
        $edad = $hoy->diff($nac);
        return $edad->y;
    }

    public function getBirthdate(): ?\DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(\DateTimeInterface $birthdate): self
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    public function getResume(): ?array
    {
        return $this->resume;
    }

    public function setResume(?array $resume): self
    {
        $this->resume = $resume;

        return $this;
    }
}
