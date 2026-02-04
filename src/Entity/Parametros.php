<?php

namespace App\Entity;

use App\Repository\ParametrosRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ParametrosRepository::class)
 */
class Parametros
{
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $param1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $param2;

    /**
     * @ORM\ManyToOne(targetEntity=Catalogo::class, inversedBy="parametros")
     */
    private $catalogo;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getParam1(): ?string
    {
        return $this->param1;
    }

    public function setParam1(?string $param1): self
    {
        $this->param1 = $param1;

        return $this;
    }

    public function getParam2(): ?string
    {
        return $this->param2;
    }

    public function setParam2(?string $param2): self
    {
        $this->param2 = $param2;

        return $this;
    }

    public function getCatalogo(): ?Catalogo
    {
        return $this->catalogo;
    }

    public function setCatalogo(?Catalogo $catalogo): self
    {
        $this->catalogo = $catalogo;

        return $this;
    }
}
