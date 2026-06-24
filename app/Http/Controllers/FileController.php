<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function serveFile($filePath)
    {
        // Prevent directory traversal attacks
        if (str_contains($filePath, '..')) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        // Find the candidate by the file path in either cv or flk column
        $candidate = Candidate::where('cv', $filePath)
            ->orWhere('flk', $filePath)
            ->first();

        if (!$candidate) {
            Log::warning('File access attempt failed: No candidate found for file path.', ['file_path' => $filePath]);
            abort(404);
        }

        // Authorize using the gate
        if (Gate::denies('view-candidate-documents', $candidate)) {
            $user = auth()->user();
            Log::error('File Access Denied', [
                'user_id' => $user->id,
                'user_roles' => $user->getRoleNames(),
                'user_department_id' => $user->department_id,
                'candidate_id' => $candidate->id,
                'candidate_department_id' => $candidate->department_id,
                'file_path' => $filePath,
            ]);
            abort(403, 'You do not have permission to view this file.');
        }

        return Storage::disk('public')->response($filePath);
    }
}
