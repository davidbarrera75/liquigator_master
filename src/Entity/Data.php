<?php

namespace App\Entity;

use App\Repository\DataRepository;
use App\Service\IpcService;
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
     * @ORM\ManyToOne(targetEntity=Information::class, inversedBy="data", cascade={"remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $info;

    /**
     * @ORM\Column(type="integer")
     */
    private $days_period;

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

    public function getPeriodArray()
    {
        $period = $this->getFormattedPeriod();
        //Get textual representation of a month, three letters in spanish
        $month = IpcService::getMonthService($period->format('F'));
        return [
            'year' => $period->format('Y'),
            'month' => $month,
            'day' => $this->days_period,
        ];
    }

    //Create a function when get year, month, day from period, return in array

    public function getFormattedPeriod()
    {
        return \DateTime::createFromFormat('Y-m', $this->period);
    }

    public function getDaysPeriod(): ?int
    {
        return $this->days_period;
    }

    public function setDaysPeriod(int $days_period): self
    {
        $this->days_period = $days_period;

        return $this;
    }

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $ibc_original;

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     */
    private $tope_aplicado = 0;

    public function getIbcOriginal(): ?float
    {
        return $this->ibc_original;
    }

    public function setIbcOriginal(?float $ibc_original): self
    {
        $this->ibc_original = $ibc_original;

        return $this;
    }

    public function getTopeAplicado(): ?bool
    {
        return $this->tope_aplicado;
    }

    public function setTopeAplicado(bool $tope_aplicado): self
    {
        $this->tope_aplicado = $tope_aplicado;

        return $this;
    }

}
