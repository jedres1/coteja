<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingDteCorrelative extends Model
{
    protected $fillable = [
        'customer_id',
        'document_type',
        'year',
        'establishment',
        'point_of_sale',
        'next_number',
    ];
}
