<?php
namespace App\Traits;

use App\Models\Event;

trait HasSchedulingConflicts
{
  protected function getEventStatus($userId, $start, $end, $eventId = null)
  {
    $query = Event::where('user_id', $userId)
      ->where('status', '!=', 'cancelled')
      ->where(function ($q) use ($start, $end) {
        $q->where('event_date_utc', '<', $end)
          ->where('end_date_utc', '>', $start);
      });

    if ($eventId)
      $query->where('id', '!=', $eventId);

    return $query->exists() ? 'conflict' : 'pending';
  }
  protected function refreshNeighbors($userId, $start, $end)
  {
    $neighbors = Event::where('user_id', $userId)
      ->where('status', 'conflict')
      ->where(function ($q) use ($start, $end) {
        $q->where('event_date_utc', '<', $end)
          ->where('end_date_utc', '>', $start);
      })
      ->get();
    /** @var \App\Models\Event $neighbor */

    foreach ($neighbors as $neighbor) {
      $newStatus = $this->getEventStatus($userId, $neighbor->event_date_utc, $neighbor->end_date_utc, $neighbor->id);
      $neighbor->update(['status' => $newStatus]);
    }
  }
}