<?php

namespace App\Services\Interface;

interface PaymentServiceInterface
{
    public function handleNewPayment($payment, $subdominio);

    public function refundPayment($paymentId);
}
