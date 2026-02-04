<?php

namespace App\Entity;

use App\Repository\CatalogoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CatalogoRepository::class)
 */
class Catalogo
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
    private $slug;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $defaultValue;

    /**
     * @ORM\OneToMany(targetEntity=Parametros::class, mappedBy="catalogo")
     */
    private $parametros;

    public function __construct()
    {
        $this->parametros = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * @return Collection|Parametros[]
     */
    public function getParametros(): Collection
    {
        return $this->parametros;
    }

    public function addParametro(Parametros $parametro): self
    {
        if (!$this->parametros->contains($parametro)) {
            $this->parametros[] = $parametro;
            $parametro->setCatalogo($this);
        }

        return $this;
    }

    public function removeParametro(Parametros $parametro): self
    {
        if ($this->parametros->removeElement($parametro)) {
            // set the owning side to null (unless already changed)
            if ($parametro->getCatalogo() === $this) {
                $parametro->setCatalogo(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->slug;
    }
}
