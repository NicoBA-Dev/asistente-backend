<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Reminder;
use App\Models\Event;

class User extends Authenticatable
{
  use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

  protected $fillable = [
    'name',
    'email',
    'password_hash',
    'timezone',
  ];

  protected $hidden = [
    'password_hash',
  ];

  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
    ];
  }

  protected static function booted()
  {
    static::deleted(function ($user) {
      // Usamos each() para que se dispare el evento 'deleted' de cada evento
      $user->events()->each(fn($event) => $event->delete());
    });
  }
  public function getAuthPassword()
  {
    return $this->password_hash;
  }
  public function reminders()
  {
    // Un usuario tiene recordatorios a través de sus eventos
    return $this->hasManyThrough(Reminder::class, Event::class);
  }
  public function events()
  {
    return $this->hasMany(Event::class);
  }
}