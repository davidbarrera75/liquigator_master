<?php

namespace App\Entity;

use App\Repository\PDFReportRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PDFReportRepository::class)
 */
class PDFReport
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $full_path;

    /**
     * @ORM\ManyToOne(targetEntity=Information::class, inversedBy="PDFReports", cascade={"remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $information;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFullPath(): ?string
    {
        return $this->full_path;
    }

    public function setFullPath(string $full_path): self
    {
        $this->full_path = $full_path;

        return $this;
    }

    public function getInformation(): ?Information
    {
        return $this->information;
    }

    public function setInformation(?Information $information): self
    {
        $this->information = $information;

        return $this;
    }

    public function removePDF(){
        unlink($this->full_path);
        return $this;
    }
}
