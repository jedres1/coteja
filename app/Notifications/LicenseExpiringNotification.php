<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LicenseExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly License $license, public readonly int $daysLeft) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'license_expiring',
            'license_id' => $this->license->id,
            'license_key'=> $this->license->license_key,
            'customer'   => $this->license->customer?->name ?? 'N/A',
            'company'    => $this->license->company?->business_name ?? 'N/A',
            'expires_at' => $this->license->expires_at?->toDateString(),
            'days_left'  => $this->daysLeft,
            'message'    => "La licencia {$this->license->license_key} vence en {$this->daysLeft} día(s).",
        ];
    }
}
