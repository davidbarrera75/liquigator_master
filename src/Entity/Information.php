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

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $conclusiones;

    /**
     * @ORM\Column(type="float")
     */
    private $total_weeks_original;

    /**
     * @ORM\OneToMany(targetEntity=PDFReport::class, mappedBy="information")
     * @ORM\OrderBy({"created_at"="DESC"})
     */
    private $PDFReports;

    /**
     * @ORM\Column(type="integer")
     */
    private $cotizacion_anio;

    /**
     * @ORM\OneToMany(targetEntity=Proyeccion::class, mappedBy="information")
     */
    private $proyecciones;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $total_days;

    /**
     * @ORM\Column(type="string", length=1, options={"default": "M"})
     * M = Masculino, F = Femenino
     */
    private $genero = 'M';

    /**
     * @ORM\OneToMany(targetEntity=ResumenSemanas::class, mappedBy="info")
     */
    private $resumenSemanas;

    public function __construct()
    {
        $this->data = new ArrayCollection();
        $this->PDFReports = new ArrayCollection();
        $this->proyecciones = new ArrayCollection();
        $this->resumenSemanas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return strtoupper($this->full_name);
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
        $uniq = md5(dechex($Identification).bin2hex(random_bytes(3)));
        $this->setUniqId($uniq);
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

    public function getConclusiones(): ?string
    {
        return $this->conclusiones;
    }

    public function setConclusiones(?string $conclusiones): self
    {
        $this->conclusiones = $conclusiones;

        return $this;
    }

    public function getTotalWeeksOriginal(): ?float
    {
        return $this->total_weeks_original;
    }

    public function setTotalWeeksOriginal(float $total_weeks_original): self
    {
        $this->total_weeks_original = $total_weeks_original;

        return $this;
    }

    /**
     * @return Collection|PDFReport[]
     */
    public function getPDFReports(): Collection
    {
        return $this->PDFReports;
    }

    public function addPDFReport(PDFReport $pDFReport): self
    {
        if (!$this->PDFReports->contains($pDFReport)) {
            $this->PDFReports[] = $pDFReport;
            $pDFReport->setInformation($this);
        }

        return $this;
    }

    public function removePDFReport(PDFReport $pDFReport): self
    {
        if ($this->PDFReports->removeElement($pDFReport)) {
            // set the owning side to null (unless already changed)
            if ($pDFReport->getInformation() === $this) {
                $pDFReport->setInformation(null);
                $pDFReport->removePDF();
            }
        }

        return $this;
    }

    public function getCotizacionAnio(): ?int
    {
        return $this->cotizacion_anio;
    }

    public function setCotizacionAnio(int $cotizacion_anio): self
    {
        $this->cotizacion_anio = $cotizacion_anio;

        return $this;
    }

    /**
     * @return Collection|Proyeccion[]
     */
    public function getProyecciones(): Collection
    {
        return $this->proyecciones;
    }

    public function addProyeccione(Proyeccion $proyeccione): self
    {
        if (!$this->proyecciones->contains($proyeccione)) {
            $this->proyecciones[] = $proyeccione;
            $proyeccione->setInformation($this);
        }

        return $this;
    }

    public function removeProyeccione(Proyeccion $proyeccione): self
    {
        if ($this->proyecciones->removeElement($proyeccione)) {
            // set the owning side to null (unless already changed)
            if ($proyeccione->getInformation() === $this) {
                $proyeccione->setInformation(null);
            }
        }

        return $this;
    }

    public function getTotalDays(): ?int
    {
        return $this->total_days;
    }

    public function setTotalDays(?int $total_days): self
    {
        $this->total_days = $total_days;

        return $this;
    }

    public function getGenero(): ?string
    {
        return $this->genero;
    }

    public function setGenero(string $genero): self
    {
        $this->genero = $genero;

        return $this;
    }

    public function isMujer(): bool
    {
        return $this->genero === 'F';
    }

    public function getGeneroTexto(): string
    {
        return $this->genero === 'F' ? 'Femenino' : 'Masculino';
    }

    /**
     * @return Collection|ResumenSemanas[]
     */
    public function getResumenSemanas(): Collection
    {
        return $this->resumenSemanas;
    }

    public function addResumenSemanas(ResumenSemanas $resumenSemanas): self
    {
        if (!$this->resumenSemanas->contains($resumenSemanas)) {
            $this->resumenSemanas[] = $resumenSemanas;
            $resumenSemanas->setInfo($this);
        }

        return $this;
    }

    public function removeResumenSemanas(ResumenSemanas $resumenSemanas): self
    {
        if ($this->resumenSemanas->removeElement($resumenSemanas)) {
            if ($resumenSemanas->getInfo() === $this) {
                $resumenSemanas->setInfo(null);
            }
        }

        return $this;
    }
}
