<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
  use HasFactory, HasUuids, SoftDeletes;

  protected $fillable = ['user_id', 'name', 'company_name', 'phone'];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function events(): BelongsToMany
  {
    return $this->belongsToMany(Event::class, 'event_contacts')
      ->withPivot('role');
  }
}