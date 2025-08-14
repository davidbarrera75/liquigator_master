<?php

namespace App\Entity;

use App\Repository\ConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConfigurationRepository::class)
 */
class Configuration
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
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $default_vslue;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $config_values;

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

    public function getDefaultVslue(): ?string
    {
        return $this->default_vslue;
    }

    public function setDefaultVslue(?string $default_vslue): self
    {
        $this->default_vslue = $default_vslue;

        return $this;
    }

    public function getConfigValues(): ?string
    {
        return $this->config_values;
    }

    public function setConfigValues(?string $config_values): self
    {
        $this->config_values = $config_values;

        return $this;
    }
}
