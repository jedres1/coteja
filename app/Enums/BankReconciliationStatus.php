<?php

namespace App\Enums;

enum BankReconciliationStatus: string
{
    case Open     = 'abierta';
    case Firm     = 'en_firme';
    case Voided   = 'anulada';

    public function isEditable(): bool
    {
        return $this === self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open   => 'Abierta',
            self::Firm   => 'En firme',
            self::Voided => 'Anulada',
        };
    }
}
