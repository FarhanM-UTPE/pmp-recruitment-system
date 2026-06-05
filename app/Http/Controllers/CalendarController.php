<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Services\GoogleCalendarService;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    protected $zoomService;
    protected $googleCalendarService;

    public function __construct(ZoomService $zoomService, GoogleCalendarService $googleCalendarService)
    {
        $this->zoomService = $zoomService;
        $this->googleCalendarService = $googleCalendarService;
    }

    public function getCalendarData(Request $request)
    {
        $start = $request->query('start') ? Carbon::parse($request->query('start')) : now()->subMonth();
        $end = $request->query('end') ? Carbon::parse($request->query('end')) : now()->addMonth();
        $candidateId = $request->query('candidate_id');

        // Fetch data
        $zoomMeetings = $this->zoomService->getMeetings('me', $start, $end);
        $googleEvents = $this->googleCalendarService->getEvents($start, $end);

        // Process and merge
        $mergedData = $this->mergeCalendarData($zoomMeetings, $googleEvents);

        // Filter by candidate if requested
        if ($candidateId) {
            $mergedData = array_filter($mergedData, function ($event) use ($candidateId) {
                return isset($event['extendedProps']['candidate_id']) && $event['extendedProps']['candidate_id'] == $candidateId;
            });
        }

        return response()->json(array_values($mergedData));
    }

    private function mergeCalendarData(array $zoomMeetings, array $googleEvents): array
    {
        $events = [];

        foreach ($zoomMeetings as $meeting) {
            $startTime = Carbon::parse($meeting['start_time'])->setTimezone(config('app.timezone'));
            $events[] = [
                'id' => 'zoom-' . $meeting['id'],
                'title' => $meeting['topic'],
                'start' => $startTime->toIso8601String(),
                'end' => $startTime->copy()->addMinutes($meeting['duration'])->toIso8601String(),
                'extendedProps' => [
                    'isZoom' => true,
                    'zoomMeetingId' => $meeting['id'],
                    'zoomPassword' => $meeting['password'] ?? 'N/A',
                    'joinUrl' => $meeting['join_url'],
                    'description' => $meeting['agenda'] ?? '',
                    'candidate_id' => $this->extractCandidateId($meeting['topic'] . ' ' . ($meeting['agenda'] ?? '')),
                ],
                'className' => 'zoom-only-event'
            ];
        }

        foreach ($googleEvents as $gEvent) {
            $zoomLink = $this->extractZoomLink($gEvent->getDescription());
            $meetingId = $this->extractMeetingId($zoomLink);

            // Avoid duplicates if the event is already from Zoom
            if ($meetingId && isset($events['zoom-' . $meetingId])) {
                continue;
            }

            $start = $gEvent->getStart()->getDateTime() ?? $gEvent->getStart()->getDate();
            $end = $gEvent->getEnd()->getDateTime() ?? $gEvent->getEnd()->getDate();

            $events[] = [
                'id' => 'google-' . $gEvent->getId(),
                'title' => $gEvent->getSummary(),
                'start' => Carbon::parse($start)->toIso8601String(),
                'end' => Carbon::parse($end)->toIso8601String(),
                'extendedProps' => [
                    'isZoom' => false,
                    'description' => $gEvent->getDescription() ?? '',
                    'location' => $gEvent->getLocation() ?? '',
                    'candidate_id' => $this->extractCandidateId($gEvent->getSummary() . ' ' . $gEvent->getDescription()),
                ],
                'className' => 'google-calendar-event'
            ];
        }

        return $events;
    }

    private function extractZoomLink(?string $description): ?string
    {
        if (!$description)
            return null;
        preg_match('/(https://[a-zA-Z0-9.-]+\.zoom\.us/j/[a-zA-Z0-9?=_.-]+)/', $description, $matches);
        return $matches[0] ?? null;
    }

    private function extractMeetingId(?string $url): ?string
    {
        if (!$url)
            return null;
        preg_match('/j\/(\d+)/i', $url, $matches);
        return $matches[1] ?? null;
    }

    private function extractCandidateId(?string $text): ?int
    {
        if (!$text)
            return null;
        // Example: Looks for [CANDIDATE-123] in event title or description
        if (preg_match('/\[CANDIDATE-(\d+)\]/i', $text, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
