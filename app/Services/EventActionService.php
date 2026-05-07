<?php

namespace App\Services;

use App\Models\{Event, Contact};
use App\Traits\HasSchedulingConflicts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventActionService
{
  use HasSchedulingConflicts;

  public function __construct(protected EventSearchService $search)
  {
  }

  public function execute($aiData, $userId, $mongoId)
  {
    $intent = $aiData['intencion'] ?? 'CREAR';
    $params = $aiData['parametros'] ?? [];

    return match ($intent) {
      'ELIMINAR' => $this->handleDelete($userId, $params),
      'MODIFICAR' => $this->handleUpdate($userId, $params),
      default => $this->handleCreate($userId, $params, $mongoId),
    };
  }

  private function handleCreate($userId, $params, $mongoId)
  {
    $start = Carbon::parse($params['fecha_evento'] ?? now());
    $end = (clone $start)->addMinutes($params['duracion_minutos'] ?? 60);
    $status = $this->getEventStatus($userId, $start, $end);

    $event = Event::create([
      'user_id' => $userId,
      'title' => $params['titulo'] ?? 'Nueva Reunión',
      'category' => $params['categoria'] ?? 'Otro',
      'event_date_utc' => $start,
      'end_date_utc' => $end,
      'status' => $status,
      'mongo_log_id' => $mongoId
    ]);

    // 🚀 NUEVO: Si este evento nació con conflicto, marcar también a los otros
    if ($status === 'conflict') {
      Event::where('user_id', $userId)
        ->where('id', '!=', $event->id)
        ->where('status', '!=', 'cancelled')
        ->where(function ($q) use ($start, $end) {
          $q->where('event_date_utc', '<', $end)
            ->where('end_date_utc', '>', $start);
        })
        ->update(['status' => 'conflict']);
    }

    $this->attachAssets($event, $params, $userId);
    return ['message' => 'Creada.', 'event' => $event, 'has_conflict' => $status === 'conflict'];
  }

  private function handleUpdate($userId, $params)
  {
    $event = $this->search->find($userId, $params['fecha_referencia'] ?? null, $params['palabras_clave'] ?? null);
    if (!$event)
      throw new \Exception("Evento no encontrado para modificar.");

    $start = isset($params['nuevos_datos']['fecha_evento']) ? Carbon::parse($params['nuevos_datos']['fecha_evento']) : $event->event_date_utc;
    $end = (clone $start)->addMinutes(60);
    $status = $this->getEventStatus($userId, $start, $end, $event->id);

    $event->update([
      'title' => $params['nuevos_datos']['titulo'] ?? $event->title,
      'event_date_utc' => $start,
      'end_date_utc' => $end,
      'status' => $status
    ]);

    // 🚀 NUEVO: Sincronizar recordatorios con la nueva hora
    foreach ($event->reminders as $reminder) {
      $reminder->update([
        'notify_at' => Carbon::parse($event->event_date_utc)->subMinutes($reminder->minutes_before),
        'is_sent' => false // Reseteamos para que la notificación se vuelva a disparar
      ]);
    }

    return ['message' => 'Reprogramada.', 'event' => $event, 'has_conflict' => $status === 'conflict'];
  }

  private function handleDelete($userId, $params)
  {
    $event = $this->search->find($userId, $params['fecha_referencia'] ?? null, $params['palabras_clave'] ?? null);
    if (!$event)
      throw new \Exception("Evento no encontrado para eliminar.");

    $start = $event->event_date_utc;
    $end = $event->end_date_utc;

    $event->delete();

    $this->refreshNeighbors($userId, $start, $end);

    return ['message' => 'Eliminada y agenda actualizada.', 'event' => $event];
  }

  private function attachAssets($event, $params, $userId)
  {
    if (!empty($params['contactos'])) {
      foreach ($params['contactos'] as $name) {
        $c = Contact::firstOrCreate(['user_id' => $userId, 'name' => trim($name)]);
        $event->contacts()->attach($c->id);
      }
    }
    if (($params['recordatorio_minutos'] ?? 0) > 0) {
      $event->reminders()->create([
        'minutes_before' => $params['recordatorio_minutos'],
        'method' => 'push',
        'notify_at' => Carbon::parse($event->event_date_utc)->subMinutes($params['recordatorio_minutos'])
      ]);
    }
  }
}