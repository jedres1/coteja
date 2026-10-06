<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewPurchaseInvoicesNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly array $summary) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'purchase_invoices_imported',
            'imported'  => $this->summary['imported'],
            'duplicates'=> $this->summary['duplicates'] ?? 0,
            'filtered'  => $this->summary['filtered'] ?? 0,
            'message'   => "Se importaron {$this->summary['imported']} factura(s) de compra desde el buzón. Pendientes de aprobación.",
        ];
    }
}
