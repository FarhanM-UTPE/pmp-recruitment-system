@extends('layouts.app')

@section('title', 'Statistik & Analytics')
@section('page-title', 'Statistik & Analytics')
@section('page-subtitle', 'Analisis mendalam tentang proses dan efektivitas rekrutmen')

@section('content')
<div class="bg-white rounded-xl p-4 sm:p-6 border border-gray-200 shadow-sm mb-6">
    <h2 class="text-base text-gray-600">@yield('page-subtitle')</h2>
    <div class="mt-4">
        <form method="GET" id="filterForm" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div>
                <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Sumber</label>
                <select name="source" id="source" class="border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="">Semua Sumber</option>
                    @foreach($sources as $src)
                        <option value="{{ $src }}" {{ $source == $src ? 'selected' : '' }}>{{ $src }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm flex items-center gap-2">
                <i class="fas fa-filter"></i>
                Terapkan
            </button>
        </form>
    </div>
</div>

<div class="space-y-8">
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Total Aplikasi</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($kpiData['total_applications']) }}</p>
        </div>
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Total Diterima</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ number_format($kpiData['total_hired']) }}</p>
        </div>
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Rata-rata Waktu Rekrut (Hari)</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($kpiData['avg_time_to_hire']) }}</p>
        </div>
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Tingkat Konversi Global</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $kpiData['conversion_rate'] }}%</p>
        </div>
    </div>

    <!-- Charts Row 1: Funnel and Source -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <div class="lg:col-span-3 bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Funnel Rekrutmen</h3>
            <div style="height: 350px;"><canvas id="funnelChart"></canvas></div>
        </div>
        <div class="lg:col-span-2 bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Efektivitas Sumber</h3>
            <div style="height: 350px;"><canvas id="sourceChart"></canvas></div>
        </div>
    </div>

    <!-- Charts Row 2: Monthly -->
    <div class="grid grid-cols-1 gap-6">
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tren Aplikasi Bulanan</h3>
            <div style="height: 300px;"><canvas id="monthlyChart"></canvas></div>
        </div>
    </div>

    <!-- Charts Row 3: Gender and University -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Gender</h3>
            <div style="height: 300px;"><canvas id="genderChart"></canvas></div>
        </div>
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Universitas (Top 10)</h3>
            <div style="height: 300px;"><canvas id="universityChart"></canvas></div>
        </div>
    </div>

    <!-- Universities Pass Rate Pie Chart -->
    <div class="bg-white rounded-xl p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Universitas dengan Kelulusan Tertinggi</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div style="height: 350px; lg:col-span-1;"><canvas id="universityPassRateChart"></canvas></div>
            <div class="lg:col-span-2">
                <div class="overflow-y-auto max-h-80">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-700">Universitas</th>
                                <th class="px-4 py-2 text-center font-semibold text-gray-700">Lulus</th>
                                <th class="px-4 py-2 text-center font-semibold text-gray-700">Total</th>
                                <th class="px-4 py-2 text-center font-semibold text-gray-700">Tingkat Lulus</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($universityPassRateData as $data)
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $data['institution'] }}</td>
                                    <td class="px-4 py-2 text-center text-green-600 font-semibold">{{ $data['passed_count'] }}</td>
                                    <td class="px-4 py-2 text-center text-gray-600">{{ $data['total_candidates'] }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $data['pass_rate'] >= 50 ? 'bg-green-100 text-green-800' : ($data['pass_rate'] >= 25 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                            {{ $data['pass_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">Tidak ada data universitas</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Analysis Chart -->
    <div class="bg-white rounded-xl p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Analisis Alur Waktu per Tahapan</h3>
        <div style="height: 350px;"><canvas id="timelineAnalysisChart"></canvas></div>


    </div>

    <!-- Stage Pass Rate Analysis -->
    <div class="bg-white rounded-xl p-6 border border-gray-200 mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Tingkat Lulus per Tahapan</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-4 mt-4">
            @foreach($passRateAnalysis as $stage)
                <!-- Stage Card -->
                <div class="bg-white rounded-lg p-4 border {{ $stage['total'] > 0 ? 'border-gray-200' : 'border-dashed' }} {{ $stage['total'] == 0 ? 'opacity-60' : '' }}">
                    <div class="flex justify-between items-start">
                        <h4 class="font-bold text-gray-800 text-base">{{ $stage['name'] }}</h4>
                        <span class="text-xs font-semibold bg-gray-200 text-gray-800 px-2 py-1 rounded-full">{{ number_format($stage['total']) }} Total</span>
                    </div>
                    
                    @if($stage['total'] > 0)
                        <div class="mt-3 space-y-2 text-sm">
                            @if(isset($stage['passed']))
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Lulus</span>
                                    <span class="font-medium text-green-600">{{ number_format($stage['passed']) }} <span class="text-xs text-gray-400 font-normal">({{ $stage['pass_rate'] }}%)</span></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Tidak Lulus</span>
                                    <span class="font-medium text-red-600">{{ number_format($stage['failed']) }}  <span class="text-xs text-gray-400 font-normal">({{ $stage['tidak_lulus_pass_rate'] }}%)</span></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Dalam Proses</span>
                                    <span class="font-medium text-blue-600">{{ number_format($stage['in_progress']) }}  <span class="text-xs text-gray-400 font-normal">({{ $stage['dalam_proses_pass_rate'] }}%)</span></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Canceled</span>
                                    <span class="font-medium text-black-600">{{ number_format($stage['canceled']) }}  <span class="text-xs text-gray-400 font-normal">({{ $stage['canceled_pass_rate'] }}%)</span></span>
                                </div>
                            @else
                                <div class="text-sm text-gray-500 italic pt-2">Semua dianggap 'Dalam Proses'.</div>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mt-2">Tidak ada kandidat.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const funnelData = @json($funnelData);
    const sourceData = @json($sourceData);
    const monthlyData = @json($monthlyData);
    const genderData = @json($genderData);
    const universityData = @json($universityData);
    const timelineAnalysisData = @json($timelineAnalysis);

    // 1. Timeline Analysis Chart
    const timelineCtx = document.getElementById('timelineAnalysisChart');
    if (timelineCtx) {
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: timelineAnalysisData.map(d => d.stage_name),
                datasets: [
                    {
                        label: 'Paling Cepat (Hari)',
                        data: timelineAnalysisData.map(d => d.min_days),
                        borderColor: 'rgba(34, 197, 94, 1)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.3,
                        pointStyle: 'rectRot',
                        pointRadius: 5,
                    },
                    {
                        label: 'Paling Lama (Hari)',
                        data: timelineAnalysisData.map(d => d.max_days),
                        borderColor: 'rgba(239, 68, 68, 1)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.3,
                        pointStyle: 'crossRot',
                        pointRadius: 5,
                    },
                    {
                        label: 'Rata-rata (Hari)',
                        data: timelineAnalysisData.map(d => d.avg_days),
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: 0,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(evt, elements) {
                    if (elements.length > 0) {
                        const element = elements[0];
                        const datasetIndex = element.datasetIndex;
                        const index = element.index;
                        const datasetLabel = this.data.datasets[datasetIndex].label;
                        const data = timelineAnalysisData[index];

                        let candidateId = null;
                        if (datasetLabel === 'Paling Cepat (Hari)') {
                            candidateId = data.min_days_candidate_id;
                        } else if (datasetLabel === 'Paling Lama (Hari)') {
                            candidateId = data.max_days_candidate_id;
                        }

                        if (candidateId) {
                            window.location.href = `/candidates/${candidateId}`;
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hari' }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                const data = timelineAnalysisData[index];
                                return `Tahap: ${data.stage_name}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Funnel Chart
    new Chart(document.getElementById('funnelChart'), {
        type: 'bar',
        data: {
            labels: funnelData.map(d => d.stage),
            datasets: [{
                label: 'Jumlah Kandidat',
                data: funnelData.map(d => d.count),
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y;
                            }
                            const conversion = funnelData[context.dataIndex].conversion;
                            return [label, `Konversi: ${conversion}%`];
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 2. Source Effectiveness Chart
    new Chart(document.getElementById('sourceChart'), {
        type: 'bar',
        data: {
            labels: sourceData.map(d => d.source),
            datasets: [
                {
                    label: 'Total Aplikasi',
                    data: sourceData.map(d => d.total_applications),
                    backgroundColor: 'rgba(200, 200, 200, 0.6)',
                },
                {
                    label: 'Diterima',
                    data: sourceData.map(d => d.hired_count),
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                 tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.x !== null) {
                                label += context.parsed.x;
                            }
                            if(context.dataset.label === 'Diterima'){
                                const hireRate = sourceData[context.dataIndex].hire_rate;
                                return [label, `Tingkat Sukses: ${hireRate}%`];
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true },
                y: { grid: { display: false } }
            }
        }
    });

    // 3. Monthly Applications Chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [{
                label: 'Aplikasi Masuk',
                data: monthlyData.map(d => d.count),
                borderColor: 'rgba(239, 68, 68, 1)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // 4. Gender Distribution Chart
    const rawGenderData = @json($genderData);
    const genderMapping = {
        'Laki-laki': { count: 0, color: 'rgba(59, 130, 246, 0.8)' },
        'Perempuan': { count: 0, color: 'rgba(236, 72, 153, 0.8)' },
        'Tidak Diketahui': { count: 0, color: 'rgba(156, 163, 175, 0.8)' }
    };

    for (const key in rawGenderData) {
        const count = rawGenderData[key];
        if (key === 'L' || key === 'Laki-laki') {
            genderMapping['Laki-laki'].count += count;
        } else if (key === 'P' || key === 'Perempuan') {
            genderMapping['Perempuan'].count += count;
        } else {
            genderMapping['Tidak Diketahui'].count += count;
        }
    }

    const finalGenderData = Object.values(genderMapping).map(d => d.count).filter(count => count > 0);
    const finalGenderLabels = Object.keys(genderMapping).filter((key, index) => Object.values(genderMapping).map(d => d.count)[index] > 0);
    const finalGenderColors = Object.values(genderMapping).map(d => d.color).filter((color, index) => Object.values(genderMapping).map(d => d.count)[index] > 0);
    const totalGender = finalGenderData.reduce((sum, value) => sum + value, 0);

    new Chart(document.getElementById('genderChart'), {
        type: 'doughnut',
        data: {
            labels: finalGenderLabels,
            datasets: [{
                data: finalGenderData,
                backgroundColor: finalGenderColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.raw !== null) {
                                label += context.raw;
                            }
                            const percentage = totalGender > 0 ? (context.raw / totalGender * 100).toFixed(2) : 0;
                            return `${label} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // 5. University Distribution Chart
    const totalUniversity = Object.values(universityData).reduce((sum, value) => sum + value, 0);
    new Chart(document.getElementById('universityChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(universityData),
            datasets: [{
                data: Object.values(universityData),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)',
                    'rgba(83, 102, 255, 0.8)',
                    'rgba(255, 99, 255, 0.8)',
                    'rgba(100, 255, 100, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.raw !== null) {
                                label += context.raw;
                            }
                            const percentage = (context.raw / totalUniversity * 100).toFixed(2);
                            return `${label} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // 6. University Pass Rate Pie Chart
    const universityPassRateData = @json($universityPassRateData ?? []);
    const colors = [
        'rgba(34, 197, 94, 0.8)',
        'rgba(59, 130, 246, 0.8)',
        'rgba(249, 115, 22, 0.8)',
        'rgba(168, 85, 247, 0.8)',
        'rgba(236, 72, 153, 0.8)',
        'rgba(6, 182, 212, 0.8)',
        'rgba(251, 146, 60, 0.8)',
        'rgba(79, 70, 229, 0.8)',
        'rgba(14, 165, 233, 0.8)',
        'rgba(244, 114, 182, 0.8)',
        'rgba(34, 197, 94, 0.6)',
        'rgba(59, 130, 246, 0.6)',
        'rgba(249, 115, 22, 0.6)',
        'rgba(168, 85, 247, 0.6)',
        'rgba(236, 72, 153, 0.6)'
    ];

    if (document.getElementById('universityPassRateChart')) {
        new Chart(document.getElementById('universityPassRateChart'), {
            type: 'pie',
            data: {
                labels: universityPassRateData.map(d => d.institution),
                datasets: [{
                    data: universityPassRateData.map(d => d.passed_count),
                    backgroundColor: colors.slice(0, universityPassRateData.length),
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const data = universityPassRateData[context.dataIndex];
                                return `${data.institution}: ${data.passed_count} (${data.pass_rate}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush