@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Detail Pengajuan MPP</h1>
                <p class="mt-2 text-gray-600">{{ $mppSubmission->department->name }}</p>
            </div>
            <a href="{{ url('/mpp-submissions') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 cursor-pointer border-none inline-block no-underline">
                ← Kembali
            </a>
        </div>

        <!-- Floating Toast Notifications -->
        <div id="toast-container" class="fixed z-50 flex flex-col gap-3 items-end" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999;">
            @if ($message = Session::get('success'))
            <div class="toast-notification bg-white border-l-4 border-green-500 shadow-xl rounded-md p-4 w-80 transform transition-all duration-500 ease-out translate-x-full opacity-0">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-green-500 text-lg"></i>
                        <p class="text-sm font-medium text-gray-800">{{ $message }}</p>
                    </div>
                    <button onclick="this.closest('.toast-notification').remove()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Loading Indicator -->
                <div class="w-full bg-gray-200 h-1 mt-3 rounded-full overflow-hidden">
                    <div class="toast-progress bg-green-500 h-full" style="width: 100%; transition: width 3s linear;"></div>
                </div>
            </div>
            @endif

            @if ($message = Session::get('error'))
            <div class="toast-notification bg-white border-l-4 border-red-500 shadow-xl rounded-md p-4 w-80 transform transition-all duration-500 ease-out translate-x-full opacity-0">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                        <p class="text-sm font-medium text-gray-800">{{ $message }}</p>
                    </div>
                    <button onclick="this.closest('.toast-notification').remove()" class="text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Loading Indicator -->
                <div class="w-full bg-gray-200 h-1 mt-3 rounded-full overflow-hidden">
                    <div class="toast-progress bg-red-500 h-full" style="width: 100%; transition: width 3s linear;"></div>
                </div>
            </div>
            @endif
        </div>

        <!-- Added ID here for DOM Replacement -->
        <div id="main-content-wrapper" style="display:contents;">
            <div class="space-y-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Informasi Pengajuan</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Departemen</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->department->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Dibuat Oleh</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->createdByUser->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Tanggal Dibuat</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $mppSubmission->created_at->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <!-- Vacancies Table -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Posisi & Dokumen</h2>
                <div class="space-y-4">
                    @forelse ($mppSubmission->vacancies as $vacancy)
                        @php
                            $requiredDocType = $vacancy->pivot->vacancy_status === 'OSPKWT' ? 'A1' : 'B1';
                            $document = $vacancy->getDocument($requiredDocType);
                            $isDepartmentUser = auth()->user()->hasRole('kepala departemen');
                            $isTeamHC = auth()->user()->hasAnyRole(['team_hc', 'team_hc_2']);
                        @endphp
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Left Side: Position Info -->
                                <div class="md:col-span-2">
                                    <h3 class="text-lg font-bold text-gray-900">{{ $vacancy->name }}</h3>
                                    <div class="flex items-center gap-4 mt-2">
                                        <span class="text-sm text-gray-600">
                                            <i class="fas fa-users mr-1"></i>
                                            {{ $vacancy->pivot->needed_count ?? '-' }} orang
                                        </span>
                                        <span class="px-2 py-1 rounded text-xs font-medium
                                            @if($vacancy->pivot->vacancy_status === 'OSPKWT') bg-blue-100 text-blue-800
                                            @elseif($vacancy->pivot->vacancy_status === 'OS') bg-purple-100 text-purple-800 @endif
                                            ">
                                            {{ $vacancy->pivot->vacancy_status }}
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        @if($vacancy->pivot->proposal_status === 'approved')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Disetujui</span>
                                        @elseif($vacancy->pivot->proposal_status === 'rejected')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800" title="{{ $vacancy->pivot->rejection_reason }}">Ditolak</span>
                                        @elseif($vacancy->pivot->proposal_status === 'pending_hc2_approval')
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">Menunggu Approval HC2</span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">Menunggu Approval HC1</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Right Side: Documents & Actions -->
                                <div class="space-y-3">
                                    <!-- Document Info -->
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Dokumen {{ $requiredDocType }}:</span>
                                        <div class="mt-1 flex items-center gap-2">
                                            @if ($document)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ {{ $document->status }}</span>
                                                <a href="{{ route('vacancy-documents.preview', [$vacancy, $document]) }}" class="text-blue-600 hover:text-blue-900 text-xs font-medium" target="_blank">[Preview]</a>
                                                @if (($isDepartmentUser && auth()->user()->department_id === $vacancy->department_id && $document->status === 'pending') || $isTeamHC)
                                                <form action="{{ route('vacancy-documents.destroy', [$vacancy, $document]) }}" method="POST" class="inline action-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-xs font-medium" onclick="return confirm('Yakin ingin menghapus dokumen ini?')">[Hapus]</button>
                                                </form>
                                                @endif
                                            @else
                                                @if (($isDepartmentUser && auth()->user()->department_id === $vacancy->department_id) || $isTeamHC)
                                                    <a href="{{ route('vacancy-documents.upload', $vacancy) }}?mpp_submission_id={{ $mppSubmission->id }}" class="inline-flex items-center gap-1 text-green-600 hover:text-green-800 text-sm font-medium">
                                                        <i class="fas fa-upload"></i> Upload Dokumen
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-200 text-gray-700">✗ Belum ada</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Approval Actions -->
                                    @can('approve-mpp-submission')
                                        @if(auth()->user()->hasRole('team_hc') && $vacancy->pivot->proposal_status === 'pending')
                                        <div class="flex gap-2 pt-2 border-t border-gray-200">
                                            <form action="{{ route('mpp-submissions.approve-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors">Approve (HC1)</button>
                                            </form>
                                            <form action="{{ route('mpp-submissions.reject-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" class="rejection-reason-input">
                                                <button type="submit" class="w-full px-3 py-1.5 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition-colors">Reject</button>
                                            </form>
                                        </div>
                                        @elseif(auth()->user()->hasRole('team_hc_2') && $vacancy->pivot->proposal_status === 'pending_hc2_approval')
                                        <div class="flex gap-2 pt-2 border-t border-gray-200">
                                            <form action="{{ route('mpp-submissions.approve-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <button type="submit" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition-colors">Approve (HC2)</button>
                                            </form>
                                            <form action="{{ route('mpp-submissions.reject-vacancy', [$mppSubmission, $vacancy]) }}" method="POST" class="w-full action-form">
                                                @csrf
                                                <input type="hidden" name="rejection_reason" class="rejection-reason-input">
                                                <button type="submit" class="w-full px-3 py-1.5 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition-colors">Reject</button>
                                            </form>
                                        </div>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <i class="fas fa-folder-open text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">Tidak ada posisi yang diajukan</h3>
                            <p class="text-gray-500">Belum ada posisi yang ditambahkan ke pengajuan MPP ini.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Approval History -->
            @if ($mppSubmission->approvalHistories->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Riwayat Aktivitas</h2>
                <div class="space-y-4">
                    @foreach ($mppSubmission->approvalHistories->sortByDesc('created_at') as $history)
                    <div class="flex gap-4 pb-4 border-b last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                <span class="text-sm font-medium text-blue-800">{{ substr($history->user->name, 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="flex-grow">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $history->user->name }}
                                <span class="text-gray-600">{{ strtoupper($history->action) }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ $history->created_at->format('d M Y H:i') }}</p>
                            @if ($history->notes)
                            <p class="text-sm text-gray-700 mt-1">{{ $history->notes }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            </div>
        </div>

    </div>
</div>

<script>
function initToasts() {
    const toasts = document.querySelectorAll('.toast-notification');
    toasts.forEach(toast => {
        const progressBar = toast.querySelector('.toast-progress');
        
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        });
        
        if (progressBar) {
            setTimeout(() => {
                progressBar.style.width = '0%';
            }, 300); 
        }

        setTimeout(() => {
            toast.classList.remove('translate-x-0', 'opacity-100');
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, 3300);
    });
}

document.addEventListener("DOMContentLoaded", function() {
    initToasts();

    document.addEventListener('submit', async function(e) {
        if (!e.target.classList.contains('action-form')) return;
        
        e.preventDefault();
        const form = e.target;
        
        if (form.action.includes('reject')) {
            const reason = prompt("Masukkan alasan penolakan:");
            if (!reason || reason.trim() === "") return; 
            form.querySelector('.rejection-reason-input').value = reason;
        }

        try {
            const methodInput = form.querySelector('input[name="_method"]');
            const fetchMethod = methodInput ? methodInput.value : 'POST';

            const response = await fetch(form.action, {
                method: fetchMethod,
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (response.ok) {
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                document.getElementById('main-content-wrapper').innerHTML = doc.getElementById('main-content-wrapper').innerHTML;
                
                document.getElementById('toast-container').innerHTML = doc.getElementById('toast-container').innerHTML;
                
                initToasts();
            } else {
                form.submit(); 
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            form.submit();
        }
    });
});
</script>
@endsection