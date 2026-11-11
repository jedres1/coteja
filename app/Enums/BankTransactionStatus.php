<?php

namespace App\Enums;

enum BankTransactionStatus: string
{
    case Active = 'active';
    case Voided = 'voided';
}
