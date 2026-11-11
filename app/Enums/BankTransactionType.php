<?php

namespace App\Enums;

enum BankTransactionType: string
{
    case Deposit          = 'deposito';
    case Payment          = 'pago';
    case TransferIn       = 'transferencia_entrada';
    case TransferOut      = 'transferencia_salida';
    case Check            = 'cheque';
    case DebitNote        = 'nota_debito';
    case CreditNote       = 'nota_credito';
    case OpeningAdjust    = 'ajuste_apertura';
    case Reversal         = 'reversion';
    case Other            = 'otro';

    /** +1 increases balance, -1 decreases, 0 depends on amount sign */
    public function sign(): int
    {
        return match ($this) {
            self::Deposit, self::TransferIn, self::CreditNote => 1,
            self::Payment, self::Check, self::TransferOut, self::DebitNote => -1,
            self::OpeningAdjust, self::Reversal, self::Other => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Deposit       => 'Depósito',
            self::Payment       => 'Pago',
            self::TransferIn    => 'Transferencia entrada',
            self::TransferOut   => 'Transferencia salida',
            self::Check         => 'Cheque',
            self::DebitNote     => 'Nota de débito',
            self::CreditNote    => 'Nota de crédito',
            self::OpeningAdjust => 'Ajuste de apertura',
            self::Reversal      => 'Reversión',
            self::Other         => 'Otro',
        };
    }
}
