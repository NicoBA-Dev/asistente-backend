<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
  use HasFactory, HasUuids;

  protected $fillable = [
    'event_id',
    'minutes_before',
    'method',
    'notify_at',
    'is_sent'
  ];

  protected $casts = [
    'notify_at' => 'datetime',
  ];

  public function event(): BelongsTo
  {
    return $this->belongsTo(Event::class);
  }
}