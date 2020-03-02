<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

class Address extends AbstractDTO
{
    private $firstname;
    private $lastname;
    private $company;
    private $street;
    private $suburb;
    private $city;
    private $county;
    private $postcode;
    private $countryIsoCode;
    private $telephone;
    private $mobileTelephone;
    private $emailAddress;

    public function setFirstname($firstname)
    {
        $this->firstname = $this->enforceUnicode($firstname);
        return $this;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $this->enforceUnicode($lastname);
        return $this;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setCompany($company)
    {
        $this->company = $this->enforceUnicode($company);
        return $this;
    }

    public function getCompany()
    {
        return $this->company;
    }

    public function setStreet($street)
    {
        $this->street = $this->enforceUnicode($street);
        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function setSuburb($suburb)
    {
        $this->suburb = $this->enforceUnicode($suburb);
        return $this;
    }

    public function getSuburb()
    {
        return $this->suburb;
    }

    public function setCity($city)
    {
        $this->city = $this->enforceUnicode($city);
        return $this;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function setCounty($county)
    {
        $this->county = $this->enforceUnicode($county);
        return $this;
    }

    public function getCounty()
    {
        return $this->county;
    }

    public function setPostcode($postcode)
    {
        $this->postcode = $this->enforceUnicode($postcode);
        return $this;
    }

    public function getPostcode()
    {
        return $this->postcode;
    }

    public function setCountryIsoCode($countryIsoCode)
    {
        $this->countryIsoCode = $this->enforceUnicode($countryIsoCode);
        return $this;
    }

    public function getCountryIsoCode()
    {
        return $this->countryIsoCode;
    }

    public function setTelephone($telephone)
    {
        $this->telephone = $this->enforceUnicode($telephone);
        return $this;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }

    public function setMobileTelephone($mobileTelephone)
    {
        $this->mobileTelephone = $this->enforceUnicode($mobileTelephone);
        return $this;
    }

    public function getMobileTelephone()
    {
        return $this->mobileTelephone;
    }

    public function setEmailAddress($emailAddress)
    {
        $this->emailAddress = $this->enforceUnicode($emailAddress);
        return $this;
    }

    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    public function toArray()
    {
        return \get_object_vars($this);
    }
}
