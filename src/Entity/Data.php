<?php

namespace App\Entity;

use App\Repository\DataRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DataRepository::class)
 */
class Data
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $period;

    /**
     * @ORM\Column(type="float")
     */
    private $val;

    /**
     * @ORM\ManyToOne(targetEntity=Information::class, inversedBy="data")
     */
    private $info;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getVal(): ?float
    {
        return $this->val;
    }

    public function setVal(float $val): self
    {
        $this->val = $val;

        return $this;
    }

    public function getInfo(): ?Information
    {
        return $this->info;
    }

    public function setInfo(?Information $info): self
    {
        $this->info = $info;

        return $this;
    }
}
