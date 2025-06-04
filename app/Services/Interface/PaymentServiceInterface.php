<?php

namespace App\Services\Interface;

interface PaymentServiceInterface
{
    public function handleNewPayment($payment);

    public function refundPayment($paymentId);
}
