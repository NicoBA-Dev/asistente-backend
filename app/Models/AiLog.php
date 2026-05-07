<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AiLog extends Model
{
  protected $connection = 'mongodb'; // Fuerza el uso de la conexión Mongo
  protected $collection = 'ai_logs';

  protected $fillable = [
    'sync_status',
    'retry_count',
    'last_retry_at',
    'client_context',
    'ai_audit',
    'postgres_event_id',
    'linked_at',
  ];

  protected $casts = [
    'last_retry_at' => 'datetime',
    'linked_at' => 'datetime',
    'ai_audit' => 'array',
    'client_context' => 'array',
  ];
}