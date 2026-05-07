<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReminders extends Command
{
  // El nombre del comando que ejecutaremos en consola
  protected $signature = 'app:process-reminders';

  // Una breve descripción
  protected $description = 'Busca recordatorios pendientes y dispara las notificaciones.';

  public function handle()
  {
    $this->info("Iniciando escaneo de recordatorios...");

    // 1. Buscamos recordatorios donde la hora de notificar ya pasó o es ahora mismo
    $reminders = Reminder::where('notify_at', '<=', now())
      ->whereHas('event', function ($query) {
        // Filtramos eventos que NO estén cancelados
        $query->where('status', '!=', 'cancelled');
      })
      ->whereHas('event.user', function ($query) {
        // Filtramos usuarios activos (Eloquent maneja el deleted_at automáticamente por el SoftDeletes)
        $query->whereNull('deleted_at');
      })
      ->with(['event', 'event.user']) // Cargamos relaciones para usarlas
      ->get();

    if ($reminders->isEmpty()) {
      $this->info("No hay recordatorios pendientes.");
      return;
    }

    foreach ($reminders as $reminderData) {
      $reminder = \App\Models\Reminder::find($reminderData->id);
      if (!$reminder)
        continue;

      $event = $reminder->event;
      $user = $event->user;

      $mensaje = "🔔 ALERTA PARA {$user->name}: Tu reunión '{$event->title}' comienza en {$reminder->minutes_before} minutos.";

      Log::info($mensaje);
      $this->line($mensaje);

      $reminder->update(['is_sent' => true]);
    }

    $this->info("Escaneo finalizado. Procesados: " . $reminders->count());
  }
}