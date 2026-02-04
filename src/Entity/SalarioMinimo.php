<?php

namespace App\Entity;

use App\Repository\SalarioMinimoRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SalarioMinimoRepository::class)
 */
class SalarioMinimo
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer",unique=true)
     */
    private $anio;

    /**
     * @ORM\Column(type="float")
     */
    private $valor;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $tope;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTope(): ?float
    {
        // Si el tope está en la base de datos, usarlo
        if ($this->tope !== null) {
            return $this->tope;
        }

        // Fallback: calcular tope usando lógica anterior (por si acaso)
        $topes = [];
        for ($i = 1967; $i <= 1971; $i++) {
            $topes[$i] = 5490;
        }
        for ($i = 1972; $i <= 1973; $i++) {
            $topes[$i] = 14610;
        }
        for ($i = 1974; $i <= 1978; $i++) {
            $topes[$i] = 25530;
        }
        for ($i = 1979; $i <= 1982; $i++) {
            $topes[$i] = 79290;
        }
        for ($i = 1983; $i <= 1988; $i++) {
            $topes[$i] = 163020;
        }
        for ($i = 1989; $i <= 1992; $i++) {
            $topes[$i] = 665070;
        }
        for ($i = 1993; $i < 2003; $i++) {
            $topes[$i] = $this->getValor() * 20;
        }
        for ($i = 2003; $i <= date('Y'); $i++) {
            $topes[$i] = $this->getValor() * 25;
        }

        return $topes[$this->anio] ?? ($this->getValor() * 25);
    }

    public function setTope(?float $tope): self
    {
        $this->tope = $tope;
        return $this;
    }

    public function getValor(): ?float
    {
        return $this->valor;
    }

    public function setValor(float $valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function getAnio(): ?int
    {
        return $this->anio;
    }

    public function setAnio(int $anio): self
    {
        $this->anio = $anio;

        return $this;
    }
}
