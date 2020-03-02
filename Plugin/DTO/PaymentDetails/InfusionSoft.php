<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

class InfusionSoft extends AbstractPaymentDetails
{
    public function toArray()
    {
        return \get_object_vars($this);
    }
}
