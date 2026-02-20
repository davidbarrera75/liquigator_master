<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="resumen_semanas")
 */
class ResumenSemanas
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=500)
     */
    private $nombre_razon_social;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $desde;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $hasta;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $ultimo_salario;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $semanas;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $sim;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $total;

    /**
     * @ORM\ManyToOne(targetEntity=Information::class, inversedBy="resumenSemanas")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $info;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreRazonSocial(): ?string
    {
        return $this->nombre_razon_social;
    }

    public function setNombreRazonSocial(string $nombre_razon_social): self
    {
        $this->nombre_razon_social = $nombre_razon_social;
        return $this;
    }

    public function getDesde(): ?string
    {
        return $this->desde;
    }

    public function setDesde(string $desde): self
    {
        $this->desde = $desde;
        return $this;
    }

    public function getHasta(): ?string
    {
        return $this->hasta;
    }

    public function setHasta(string $hasta): self
    {
        $this->hasta = $hasta;
        return $this;
    }

    public function getUltimoSalario(): ?string
    {
        return $this->ultimo_salario;
    }

    public function setUltimoSalario(string $ultimo_salario): self
    {
        $this->ultimo_salario = $ultimo_salario;
        return $this;
    }

    public function getSemanas(): ?string
    {
        return $this->semanas;
    }

    public function setSemanas(string $semanas): self
    {
        $this->semanas = $semanas;
        return $this;
    }

    public function getSim(): ?string
    {
        return $this->sim;
    }

    public function setSim(string $sim): self
    {
        $this->sim = $sim;
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;
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
