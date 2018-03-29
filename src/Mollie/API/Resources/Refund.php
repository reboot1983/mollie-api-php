<?php

namespace Mollie\Api\Resources;

use Mollie\Api\Types\RefundStatus;

class Refund
{
    /**
     * Id of the payment method.
     *
     * @var string
     */
    public $id;

    /**
     * The $amount that was refunded.
     *
     * @var float
     */
    public $amount;

    /**
     * UTC datetime the payment was created in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $createdAt;

    /**
     * The refund's description, if available.
     *
     * @var string|null
     */
    public $description;

    /**
     * The payment id that was refunded.
     *
     * @var string
     */
    public $paymentId;

    /**
     * The settlement amount
     *
     * @var object
     */
    public $settlementAmount;

    /**
     * The refund status
     *
     * @var string
     */
    public $status;

    /**
     * @var object[]
     */
    public $_links;

    /**
     * Is this refund queued?
     *
     * @return bool
     */
    public function isQueued()
    {
        return $this->status === RefundStatus::STATUS_QUEUED;
    }

    /**
     * Is this refund pending?
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === RefundStatus::STATUS_PENDING;
    }

    /**
     * Is this refund processing?
     *
     * @return bool
     */
    public function isProcessing()
    {
        return $this->status === RefundStatus::STATUS_PROCESSING;
    }

    /**
     * Is this refund transferred to consumer?
     *
     * @return bool
     */
    public function isTransferred()
    {
        return $this->status === RefundStatus::STATUS_REFUNDED;
    }
}