<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'date' => 'required|date',
                'time' => 'nullable|string',
                'description' => 'nullable|string|max:1000',
                'location' => 'nullable|string|max:255',
            ], [
                'title.required' => 'Judul event wajib diisi.',
                'title.max' => 'Judul event maksimal 255 karakter.',
                'date.required' => 'Tanggal event wajib diisi.',
                'date.date' => 'Format tanggal tidak valid.',
                'description.max' => 'Deskripsi maksimal 1000 karakter.',
                'location.max' => 'Lokasi maksimal 255 karakter.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data yang dimasukkan tidak valid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::create([
                'title' => $request->title,
                'date' => $request->date,
                'time' => $request->time,
                'description' => $request->description,
                'location' => $request->location,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event berhasil ditambahkan!',
                'data' => $event->load('creator')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating event: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan event. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $event->load('creator')
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing event: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Event tidak ditemukan.'
            ], 404);
        }
    }

    /**
     * Update the specified event in storage.
     */
    public function update(Request $request, Event $event)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'date' => 'required|date',
                'time' => 'nullable|string',
                'description' => 'nullable|string|max:1000',
                'location' => 'nullable|string|max:255',
            ], [
                'title.required' => 'Judul event wajib diisi.',
                'title.max' => 'Judul event maksimal 255 karakter.',
                'date.required' => 'Tanggal event wajib diisi.',
                'date.date' => 'Format tanggal tidak valid.',
                'description.max' => 'Deskripsi maksimal 1000 karakter.',
                'location.max' => 'Lokasi maksimal 255 karakter.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data yang dimasukkan tidak valid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event->update([
                'title' => $request->title,
                'date' => $request->date,
                'time' => $request->time,
                'description' => $request->description,
                'location' => $request->location,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event berhasil diperbarui!',
                'data' => $event->load('creator')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating event: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui event.'
            ], 500);
        }
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(Event $event)
    {
        try {
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event berhasil dihapus!'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus event.'
            ], 500);
        }
    }

    /**
     * Get events for a specific date range.
     */
    public function getEventsByDateRange(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tanggal tidak valid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $events = Event::with('creator')
                ->when($startDate, function ($query, $startDate) {
                    return $query->where('date', '>=', $startDate);
                })
                ->when($endDate, function ($query, $endDate) {
                    return $query->where('date', '<=', $endDate);
                })
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => $events->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching events by date range: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data event.'
            ], 500);
        }
    }

    /**
     * Get events for today.
     */
    public function getTodayEvents()
    {
        try {
            $today = now()->toDateString();

            $events = Event::with('creator')
                ->whereDate('date', $today)
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => $events->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching today events: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data event hari ini.'
            ], 500);
        }
    }

    /**
     * Get upcoming events (next 7 days).
     */
    public function getUpcomingEvents()
    {
        try {
            $today = now()->toDateString();
            $nextWeek = now()->addDays(7)->toDateString();

            $events = Event::with('creator')
                ->whereBetween('date', [$today, $nextWeek])
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events,
                'count' => $events->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data event mendatang.'
            ], 500);
        }
    }

    /**
     * UPDATED: Get calendar events with proper timeline integration
     */
    public function getCalendarEvents(Request $request)
    {
        $user = Auth::user();
        Log::info('Getting calendar events for user', ['user_id' => $user->id, 'role' => $user->roles->pluck('name')]);

        // 1. Get custom events from events table
        $customEvents = Event::all()->map(function ($event) {
            return [
                'id' => 'custom_' . $event->id,
                'title' => $event->title,
                'date' => Carbon::parse($event->date)->format('Y-m-d'),
                'time' => $event->time,
                'description' => $event->description ?: 'Event khusus',
                'location' => $event->location,
                'is_custom' => true,
                'url' => '#',
                'type' => 'custom_event',
                'created_by' => $event->creator->name ?? 'Unknown'
            ];
        });

        // 2. Get candidate test events from next_test_date
        $candidateTestEvents = collect();

        // Base query for candidates with upcoming tests
        $candidatesQuery = Candidate::with(['department'])
            ->whereNotNull('next_test_date')
            ->whereNotNull('next_test_stage')
            ->where('next_test_date', '>=', now()->toDateString())
            ->whereIn('overall_status', ['PROSES', 'DALAM PROSES', 'PENDING']);

        // Apply department filter for department role users
        if ($user->hasRole('department') && !empty($user->department_id)) {
            $candidatesQuery->where('department_id', $user->department_id);
        }

        $candidatesWithTests = $candidatesQuery->get();

        Log::info('Found candidates with tests', ['count' => $candidatesWithTests->count()]);

        // Stage display mapping
        $stageDisplayMap = [
            'cv_review' => 'CV Review',
            'psikotes' => 'Psikotes',
            'hc_interview' => 'HC Interview',
            'user_interview' => 'User Interview',
            'interview_bod' => 'Interview BOD/GM',
            'offering_letter' => 'Offering Letter',
            'mcu' => 'Medical Check Up',
            'hiring' => 'Hiring'
        ];

        foreach ($candidatesWithTests as $candidate) {
            $testDate = Carbon::parse($candidate->next_test_date)->format('Y-m-d');
            $stageName = $stageDisplayMap[$candidate->next_test_stage] ??
                Str::title(str_replace('_', ' ', $candidate->next_test_stage));

            $candidateTestEvents->push([
                'id' => 'candidate_test_' . $candidate->id,
                'title' => $candidate->nama . ' - ' . $stageName,
                'date' => $testDate,
                'time' => null, // Could be extracted if you have time info
                'description' => "Tes {$stageName} untuk {$candidate->vacancy}" .
                    ($candidate->department ? " - {$candidate->department->name}" : ''),
                'location' => null,
                'is_custom' => false,
                'url' => route('candidates.show', $candidate->id),
                'type' => 'candidate_test',
                'candidate_id' => $candidate->id,
                'stage' => $candidate->next_test_stage,
                'applicant_id' => $candidate->applicant_id
            ]);
        }

        // 3. BONUS: Get timeline events from actual stage dates (optional enhancement)
        $timelineEvents = collect();

        // Get recent stage completions (last 30 days) to show in calendar as reference
        $recentCandidates = Candidate::with(['department'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->whereNotNull('overall_status')
            ->get();

        foreach ($recentCandidates as $candidate) {
            // Check each stage completion date
            $stages = [
                'cv_review_date' => 'CV Review',
                'psikotes_date' => 'Psikotes',
                'hc_interview_date' => 'HC Interview',
                'user_interview_date' => 'User Interview',
                'bodgm_interview_date' => 'Interview BOD/GM',
                'offering_letter_date' => 'Offering Letter',
                'mcu_date' => 'Medical Check Up',
                'hiring_date' => 'Hiring'
            ];

            foreach ($stages as $dateField => $stageName) {
                if (
                    $candidate->$dateField &&
                    Carbon::parse($candidate->$dateField)->gte(now()->subDays(30))
                ) {

                    $eventDate = Carbon::parse($candidate->$dateField)->format('Y-m-d');

                    $timelineEvents->push([
                        'id' => 'timeline_' . $candidate->id . '_' . str_replace('_date', '', $dateField),
                        'title' => "✓ {$candidate->nama} - {$stageName}",
                        'date' => $eventDate,
                        'time' => Carbon::parse($candidate->$dateField)->format('H:i'),
                        'description' => "Tahapan {$stageName} selesai untuk {$candidate->vacancy}" .
                            ($candidate->department ? " - {$candidate->department->name}" : ''),
                        'location' => null,
                        'is_custom' => false,
                        'url' => route('candidates.show', $candidate->id),
                        'type' => 'timeline_completed',
                        'candidate_id' => $candidate->id,
                        'stage' => str_replace('_date', '', $dateField),
                        'applicant_id' => $candidate->applicant_id
                    ]);
                }
            }
        }

        // Merge all events
        $allEvents = $customEvents->merge($candidateTestEvents)->merge($timelineEvents);

        Log::info('Calendar events summary', [
            'custom_events' => $customEvents->count(),
            'candidate_tests' => $candidateTestEvents->count(),
            'timeline_events' => $timelineEvents->count(),
            'total' => $allEvents->count()
        ]);

        return response()->json($allEvents);
    }

    /**
     * BONUS: Get specific candidate's timeline events for calendar integration
     */
    public function getCandidateTimelineEvents($candidateId)
    {
        try {
            $candidate = Candidate::with('department')->findOrFail($candidateId);

            // Check access permission
            $user = Auth::user();
            if (
                $user->hasRole('department') &&
                $user->department_id &&
                $candidate->department_id != $user->department_id
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke kandidat ini.'
                ], 403);
            }

            $events = collect();

            // Stage mapping
            $stages = [
                'cv_review_date' => ['CV Review', 'cv_review_status'],
                'psikotes_date' => ['Psikotes', 'psikotes_result'],
                'hc_interview_date' => ['HC Interview', 'hc_interview_status'],
                'user_interview_date' => ['User Interview', 'user_interview_status'],
                'bodgm_interview_date' => ['Interview BOD/GM', 'bod_interview_status'],
                'offering_letter_date' => ['Offering Letter', 'offering_letter_status'],
                'mcu_date' => ['Medical Check Up', 'mcu_status'],
                'hiring_date' => ['Hiring', 'hiring_status']
            ];

            foreach ($stages as $dateField => [$stageName, $statusField]) {
                if ($candidate->$dateField) {
                    $status = $candidate->$statusField ?? 'Unknown';
                    $isCompleted = in_array($status, ['LULUS', 'DISARANKAN', 'DITERIMA', 'HIRED']);

                    $events->push([
                        'id' => 'candidate_' . $candidate->id . '_' . str_replace('_date', '', $dateField),
                        'title' => ($isCompleted ? '✓ ' : '⏳ ') . $candidate->nama . ' - ' . $stageName,
                        'date' => Carbon::parse($candidate->$dateField)->format('Y-m-d'),
                        'time' => Carbon::parse($candidate->$dateField)->format('H:i'),
                        'description' => "Status: {$status} - {$candidate->vacancy}",
                        'is_custom' => false,
                        'url' => route('candidates.show', $candidate->id),
                        'type' => $isCompleted ? 'timeline_completed' : 'timeline_pending',
                        'candidate_id' => $candidate->id,
                        'stage' => str_replace('_date', '', $dateField),
                        'status' => $status
                    ]);
                }
            }

            // Add next test if available
            if ($candidate->next_test_date && $candidate->next_test_stage) {
                $stageName = Str::title(str_replace('_', ' ', $candidate->next_test_stage));

                $events->push([
                    'id' => 'next_test_' . $candidate->id,
                    'title' => '📅 ' . $candidate->nama . ' - ' . $stageName,
                    'date' => Carbon::parse($candidate->next_test_date)->format('Y-m-d'),
                    'time' => null,
                    'description' => "Tes selanjutnya: {$stageName} - {$candidate->vacancy}",
                    'is_custom' => false,
                    'url' => route('candidates.show', $candidate->id),
                    'type' => 'next_test',
                    'candidate_id' => $candidate->id,
                    'stage' => $candidate->next_test_stage
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $events,
                'candidate' => [
                    'id' => $candidate->id,
                    'nama' => $candidate->nama,
                    'applicant_id' => $candidate->applicant_id,
                    'vacancy' => $candidate->vacancy,
                    'current_stage' => $candidate->current_stage,
                    'overall_status' => $candidate->overall_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting candidate timeline events: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data timeline kandidat.'
            ], 500);
        }
    }

    public function debugCalendarEvents()
    {
        $candidates = Candidate::whereNotNull('next_test_date')
            ->get([
                'id',
                'nama',
                'next_test_date',
                'next_test_stage',
                'overall_status',
                'current_stage'
            ]);

        return response()->json([
            'candidates_with_next_test' => $candidates,
            'count' => $candidates->count(),
            'sample_dates' => $candidates->take(5)->map(function ($c) {
                return [
                    'nama' => $c->nama,
                    'next_test_date' => $c->next_test_date,
                    'formatted_date' => $c->next_test_date ? Carbon::parse($c->next_test_date)->format('Y-m-d') : null,
                    'next_test_stage' => $c->next_test_stage
                ];
            })
        ]);
    }
}