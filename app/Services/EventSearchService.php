<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventSearchService
{
  public function find($userId, $dateStr = null, $keyword = null)
  {
    $query = Event::where('user_id', $userId)
      ->where('status', '!=', 'cancelled');

    // 1. Lógica de Tiempo Inteligente
    if ($dateStr) {
      $targetDate = Carbon::parse($dateStr);
      $query->whereBetween('event_date_utc', [
        $targetDate->copy()->subDay(),
        $targetDate->copy()->addDay()
      ]);
    } elseif (!$keyword) {
      // Solo restringimos a "eventos futuros" si NO hay una palabra clave.
      // Si hay palabra clave, buscamos en TODO el historial.
      $query->where('event_date_utc', '>=', now()->subDay());
    }

    // 2. Búsqueda por Título o Contactos (Inmune a ruidos)
    if ($keyword) {
      // Limpiamos palabras comunes como "cita con" o "reunión" para enfocarnos en el nombre
      $cleanKeyword = str_ireplace(['cita con', 'reunion con', 'reunión con', 'ver a'], '', $keyword);
      $searchWords = array_filter(explode(' ', trim($cleanKeyword)), fn($w) => strlen($w) > 2);

      $query->where(function ($q) use ($searchWords) {
        foreach ($searchWords as $word) {
          $q->orWhere('title', 'LIKE', "%{$word}%")
            ->orWhereHas('contacts', function ($c) use ($word) {
              $c->where('name', 'LIKE', "%{$word}%");
            });
        }
      });
    }

    $events = $query->get();
    return $this->prioritize($events, $dateStr);
  }

  private function prioritize($events, $dateStr)
  {
    if ($events->isEmpty())
      return null;

    if ($dateStr) {
      $targetDate = Carbon::parse($dateStr);
      return $events->sortBy(
        fn($e) => abs($e->event_date_utc->diffInSeconds($targetDate))
      )->first();
    }

    return $events->sortBy('event_date_utc')->first();
  }
}