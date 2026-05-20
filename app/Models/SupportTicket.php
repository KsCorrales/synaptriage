<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'subject',
        'body',
        'category',
        'customer_tier',
        'response_time_expectation',
        'priority',
        'predicted_priority',
        'confidence_score',
        'triage_status',
    ];

    protected $casts = [
        'confidence_score'          => 'float',
        'response_time_expectation' => 'integer',
    ];

    public function isPending(): bool
    {
        return $this->triage_status === 'pending';
    }

    public function isComplete(): bool
    {
        return $this->triage_status === 'complete';
    }
}