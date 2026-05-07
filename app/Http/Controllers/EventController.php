<?php
namespace App\Http\Controllers;
use App\Models\{Event, AiLog, Reminder};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Traits\HasSchedulingConflicts;

class EventController extends Controller
{
  use HasSchedulingConflicts;
  public function index(Request $request)
  {
    // NUEVO: Agregamos 'reminders' a la carga de relaciones
    $events = Event::with(['contacts', 'reminders'])->where('user_id', $request->user()->id)->orderBy('event_date_utc', 'asc')->get();
    return response()->json(['data' => $events], 200);
  }

  private function getStatus($userId, $start, $end, $eventId = null)
  {
    $q = Event::where('user_id', $userId)->where('status', '!=', 'cancelled')
      ->where(fn($q) => $q->where('event_date_utc', '<', $end)->where('end_date_utc', '>', $start));
    if ($eventId)
      $q->where('id', '!=', $eventId);
    return $q->exists() ? 'conflict' : 'pending';
  }

  public function store(Request $request)
  {
    $fields = $request->validate(['title' => 'required|string|max:200', 'category' => 'nullable|string', 'event_date_utc' => 'required|date', 'duration_minutes' => 'nullable|integer|min:15', 'contact_ids' => 'nullable|array', 'reminder_minutes' => 'nullable|integer|min:0']);
    try {
      DB::beginTransaction();
      $aiLog = AiLog::create(['sync_status' => 'manual_entry', 'ai_audit' => []]);

      $start = Carbon::parse($fields['event_date_utc']);
      $end = (clone $start)->addMinutes($fields['duration_minutes'] ?? 60);
      $status = $this->getStatus($request->user()->id, $start, $end);

      $event = Event::create(['user_id' => $request->user()->id, 'title' => $fields['title'], 'category' => $fields['category'] ?? 'Otro', 'event_date_utc' => $start, 'end_date_utc' => $end, 'status' => $status, 'mongo_log_id' => (string) ($aiLog->_id ?? $aiLog->id)]);
      if (!empty($fields['contact_ids']))
        $event->contacts()->attach($fields['contact_ids']);

      // NUEVO: Creación del recordatorio
      if (!empty($fields['reminder_minutes']) && $fields['reminder_minutes'] > 0) {
        $notifyAt = (clone $start)->subMinutes($fields['reminder_minutes']);
        $event->reminders()->create(['minutes_before' => $fields['reminder_minutes'], 'method' => 'push', 'notify_at' => $notifyAt]);
      }

      DB::commit();
      return response()->json(['data' => $event->load(['contacts', 'reminders']), 'has_conflict' => $status === 'conflict'], 201);
    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json(['error' => 'Error', 'details' => $e->getMessage()], 500);
    }
  }

  public function show(Request $request, string $id)
  {
    $event = Event::with(['contacts', 'reminders'])->where('user_id', $request->user()->id)->where('id', $id)->first();
    return $event ? response()->json(['data' => $event], 200) : response()->json(['error' => 'No encontrado.'], 404);
  }

  public function update(Request $request, string $id)
  {
    $event = Event::where('user_id', $request->user()->id)->where('id', $id)->first();
    if (!$event)
      return response()->json(['error' => 'No encontrado.'], 404);

    $fields = $request->validate([
      'title' => 'string|max:200',
      'category' => 'nullable|string',
      'event_date_utc' => 'date',
      'duration_minutes' => 'nullable|integer|min:15',
      'status' => 'string',
      'contact_ids' => 'nullable|array',
      'reminder_minutes' => 'nullable|integer|min:0'
    ]);

    try {
      DB::beginTransaction(); // 🚀 Iniciamos la transacción

      $start = isset($fields['event_date_utc']) ? Carbon::parse($fields['event_date_utc']) : clone $event->event_date_utc;
      $end = isset($fields['duration_minutes']) ? (clone $start)->addMinutes($fields['duration_minutes']) : (isset($fields['event_date_utc']) ? (clone $start)->addMinutes(60) : clone $event->end_date_utc);
      $status = $fields['status'] ?? $this->getStatus($request->user()->id, $start, $end, $event->id);

      $event->update([
        'title' => $fields['title'] ?? $event->title,
        'category' => $fields['category'] ?? $event->category,
        'event_date_utc' => $start,
        'end_date_utc' => $end,
        'status' => $status
      ]);

      if (isset($fields['contact_ids']))
        $event->contacts()->sync($fields['contact_ids']);

      // Actualización de minutos de recordatorio (si el usuario los cambia manualmente)
      if (array_key_exists('reminder_minutes', $fields)) {
        $event->reminders()->delete();
        if ($fields['reminder_minutes'] > 0) {
          $notifyAt = (clone $start)->subMinutes($fields['reminder_minutes']);
          $event->reminders()->create([
            'minutes_before' => $fields['reminder_minutes'],
            'method' => 'push',
            'notify_at' => $notifyAt
          ]);
        }
      }

      // Sincronización automática de hora (si la reunión se movió pero los minutos del recordatorio son los mismos)
      if ($event->wasChanged('event_date_utc')) {
        foreach ($event->reminders as $reminder) {
          $reminder->update([
            'notify_at' => Carbon::parse($event->event_date_utc)->subMinutes($reminder->minutes_before),
            'is_sent' => false
          ]);
        }
      }

      DB::commit(); // 🚀 Todo salió bien, guardamos cambios
      return response()->json(['data' => $event->load(['contacts', 'reminders']), 'has_conflict' => $status === 'conflict'], 200);

    } catch (\Exception $e) {
      DB::rollBack(); // 🚀 Si algo falló, deshacemos todo para evitar datos corruptos
      return response()->json(['error' => 'Error al actualizar', 'details' => $e->getMessage()], 500);
    }
  }

  public function destroy(Request $request, string $id)
  {
    $event = Event::where('user_id', $request->user()->id)->where('id', $id)->first();
    if (!$event)
      return response()->json(['error' => 'No encontrado.'], 404);

    $start = $event->event_date_utc;
    $end = $event->end_date_utc;
    $userId = $request->user()->id;

    $event->delete();

    // 🚀 IMPORTANTE: Añade esto para que el borrado manual también limpie conflictos
    $this->refreshNeighbors($userId, $start, $end);

    return response()->json(['message' => 'Eliminado.'], 200);
  }

  public function getPendingReminders(Request $request)
  {
    return $request->user()->reminders()
      ->where('notify_at', '<=', now())
      ->where('is_sent', false)
      ->whereHas('event', function ($query) {
        // Solo notificar si el evento no ha terminado todavía
        $query->where('end_date_utc', '>', now());
      })
      ->with('event')
      ->get();
  }

  public function markAsRead($id)
  {
    $reminder = \App\Models\Reminder::findOrFail($id);
    $reminder->update(['is_sent' => true]);
    return response()->json(['status' => 'success']);
  }
}