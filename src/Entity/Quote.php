<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuoteRepository")
 */
class Quote
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $policy_number;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $age;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $postcode;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $reg_no;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $abi_code;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private $premium;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPolicyNumber(): ?string
    {
        return $this->policy_number;
    }

    public function setPolicyNumber(string $policy_number): self
    {
        $this->policy_number = $policy_number;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;

        return $this;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function setPostcode(?string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getRegNo(): ?string
    {
        return $this->reg_no;
    }

    public function setRegNo(?string $reg_no): self
    {
        $this->reg_no = $reg_no;

        return $this;
    }

    public function getAbiCode(): ?string
    {
        return $this->abi_code;
    }

    public function setAbiCode(?string $abi_code): self
    {
        $this->abi_code = $abi_code;

        return $this;
    }

    public function getPremium(): ?string
    {
        return $this->premium;
    }

    public function setPremium(?string $premium): self
    {
        $this->premium = $premium;

        return $this;
    }
}
