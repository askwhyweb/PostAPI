<?php

namespace OrviSoft\Cloudburst\Plugin\DTO\PaymentDetails;

use OrviSoft\Cloudburst\Plugin\DTO\AbstractDTO;

abstract class AbstractPaymentDetails extends AbstractDTO
{
    protected $txId;
    protected $status;
    protected $statusLabel;

    public function setTxId($txId)
    {
        $this->txId = $this->enforceUnicode($txId);
        return $this;
    }

    public function getTxId()
    {
        return $this->txId;
    }

    public function setStatus($status)
    {
        $this->status = $this->enforceUnicode($status);
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatusLabel($statusLabel)
    {
        $this->statusLabel = $this->enforceUnicode($statusLabel);
        return $this;
    }

    public function getStatusLabel()
    {
        return $this->statusLabel;
    }
}
