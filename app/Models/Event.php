<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
  use HasFactory, HasUuids, SoftDeletes;

  protected $fillable = [
    'user_id',
    'title',
    'category',
    'event_date_utc',
    'end_date_utc',
    'status',
    'mongo_log_id'
  ];

  protected $casts = [
    'event_date_utc' => 'datetime',
    'end_date_utc' => 'datetime',
  ];

  protected static function booted()
  {
    static::deleted(function ($event) {
      // Borramos los recordatorios físicamente
      $event->reminders()->delete();
    });
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function contacts(): BelongsToMany
  {
    return $this->belongsToMany(Contact::class, 'event_contacts')
      ->withPivot('role');
  }

  public function reminders(): HasMany
  {
    return $this->hasMany(Reminder::class);
  }


}