@extends('layouts.app')

@push('header-filters')
  <button onclick="history.back()"
    class="text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 border border-gray-300">
    <i class="fas fa-arrow-left text-sm"></i>
    <span>Kembali</span>
  </button>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Buat Pengajuan MPP</h1>
      <p class="mt-2 text-gray-600">Form digital pengajuan MPP</p>
    </div>

    @if ($errors->any())
      <div class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-disc pl-5 space-y-1">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="bg-white rounded-lg shadow">
      <form method="POST" action="{{ route('mpp-submissions.store-new') }}" class="p-6 space-y-8">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="department_id" class="block text-sm font-medium text-gray-700 mb-2">Departemen <span
                class="text-red-500">*</span></label>
            <select id="department_id" name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-md"
              required>
              <option value="">Pilih Departemen</option>
              @foreach ($departments as $dept)
                <option value="{{ $dept['id'] }}" @selected(old('department_id') == $dept['id'])>{{ $dept['name'] }}
                </option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Tahun <span
                class="text-red-500">*</span></label>
            <select id="year" name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
              @foreach ($years as $year)
                <option value="{{ $year }}" @selected(old('year', now()->year) == $year)>{{ $year }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <section class="rounded-lg border border-gray-200 p-4 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">Jabatan Yang Diperlukan</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="vacancy_id" class="block text-sm font-medium text-gray-700 mb-2">1. Nama Jabatan <span
                  class="text-red-500">*</span></label>
              <select id="vacancy_id" name="vacancy_id" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                required>
                <option value="">Pilih Nama Jabatan</option>
              </select>
              <div id="custom-jabatan-wrapper" class="mt-3 hidden">
                <label for="custom_jabatan_name" class="block text-sm font-medium text-gray-700 mb-2">Nama Jabatan
                  Lainnya <span class="text-red-500">*</span></label>
                <input type="text" id="custom_jabatan_name" name="custom_jabatan_name"
                  value="{{ old('custom_jabatan_name') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                  placeholder="Isi nama jabatan yang belum ada di master data">
                <p class="mt-1 text-xs text-gray-500">Admin akan menambahkan jabatan ini ke master data, lalu bisa
                  di-attach ke MPP.</p>
              </div>
            </div>
            <div>
              <label for="golongan" class="block text-sm font-medium text-gray-700 mb-2">2. Golongan</label>
              <input type="text" id="golongan" name="golongan" value="{{ old('golongan') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Opsional">
            </div>
          </div>

          <div>
            <p class="text-sm font-medium text-gray-700 mb-2">3. Status Pegawai <span class="text-red-500">*</span></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
              @php($statusPegawai = old('status_pegawai'))
              @foreach (['sementara_3_bulan' => 'Sementara 3 Bulan', 'sementara_6_bulan' => 'Sementara 6 Bulan', 'sementara_12_bulan' => 'Sementara 12 Bulan', 'sementara_18_bulan' => 'Sementara 18 Bulan'] as $value => $label)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="status_pegawai" value="{{ $value }}" @checked($statusPegawai === $value)
                    required>
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div>
            <p class="text-sm font-medium text-gray-700 mb-2">4. Lokasi Pekerjaan <span class="text-red-500">*</span>
            </p>
            <div class="flex gap-6">
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="radio" name="lokasi_pekerjaan" value="head_office"
                  @checked(old('lokasi_pekerjaan') === 'head_office') required>
                <span>Head Office</span>
              </label>
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="radio" name="lokasi_pekerjaan" value="cabang" @checked(old('lokasi_pekerjaan') === 'cabang')
                  required>
                <span>Cabang</span>
              </label>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="tanggal_mulai_bekerja" class="block text-sm font-medium text-gray-700 mb-2">3. Tanggal Mulai
                Bekerja <span class="text-red-500">*</span></label>
              <input type="date" id="tanggal_mulai_bekerja" name="tanggal_mulai_bekerja"
                value="{{ old('tanggal_mulai_bekerja') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                required>
            </div>
            <div>
              <label for="jumlah_diminta" class="block text-sm font-medium text-gray-700 mb-2">4. Jumlah Yang Diminta
                <span class="text-red-500">*</span></label>
              <input type="number" min="1" id="jumlah_diminta" name="jumlah_diminta" value="{{ old('jumlah_diminta') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
            </div>
          </div>
        </section>

        <section class="rounded-lg border border-gray-200 p-4 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">Alasan Penambahan Manpower</h2>

          <div>
            <label for="alasan_penambahan_manpower" class="block text-sm font-medium text-gray-700 mb-2">Pilihan Alasan
              <span class="text-red-500">*</span></label>
            <select id="alasan_penambahan_manpower" name="alasan_penambahan_manpower"
              class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
              <option value="">Pilih Alasan</option>
              <option value="penggantian_karyawan"
                @selected(old('alasan_penambahan_manpower') === 'penggantian_karyawan')>Penggantian Karyawan</option>
              <option value="penambahan_karyawan_baru"
                @selected(old('alasan_penambahan_manpower') === 'penambahan_karyawan_baru')>Penambahan Karyawan Baru
              </option>
            </select>
          </div>

          <div id="section-penggantian" class="space-y-4 hidden">
            <div>
              <label for="nama_karyawan_diganti" class="block text-sm font-medium text-gray-700 mb-2">1. Nama Karyawan
                Yang Diganti</label>
              <input type="text" id="nama_karyawan_diganti" name="nama_karyawan_diganti"
                value="{{ old('nama_karyawan_diganti') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
              <label for="tanggal_keluar" class="block text-sm font-medium text-gray-700 mb-2">2. Tanggal Keluar</label>
              <input type="date" id="tanggal_keluar" name="tanggal_keluar" value="{{ old('tanggal_keluar') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
              <label for="alasan_penggantian" class="block text-sm font-medium text-gray-700 mb-2">3. Alasan
                Penggantian</label>
              <textarea id="alasan_penggantian" name="alasan_penggantian" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md">{{ old('alasan_penggantian') }}</textarea>
            </div>
          </div>

          <div id="section-penambahan" class="space-y-4 hidden">
            <div>
              <label for="jumlah_karyawan_ada" class="block text-sm font-medium text-gray-700 mb-2">1. Jumlah Karyawan
                Yang Ada</label>
              <input type="number" min="0" id="jumlah_karyawan_ada" name="jumlah_karyawan_ada"
                value="{{ old('jumlah_karyawan_ada') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">2. Kesesuaian Man Power Plan</p>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="kesesuaian_man_power_plan" value="sesuai_mpp"
                    @checked(old('kesesuaian_man_power_plan') === 'sesuai_mpp')>
                  <span>Sesuai Man Power Plan</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="kesesuaian_man_power_plan" value="di_luar_mpp"
                    @checked(old('kesesuaian_man_power_plan') === 'di_luar_mpp')>
                  <span>Di Luar Man Power Plan</span>
                </label>
              </div>
            </div>
            <div id="alasan-kesesuaian-wrapper" class="hidden">
              <label for="alasan_kesesuaian" class="block text-sm font-medium text-gray-700 mb-2">Alasan</label>
              <textarea id="alasan_kesesuaian" name="alasan_kesesuaian" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md">{{ old('alasan_kesesuaian') }}</textarea>
            </div>
          </div>
        </section>

        <section class="rounded-lg border border-gray-200 p-4 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">Persyaratan Jabatan</h2>

          <div>
            <p class="text-sm font-medium text-gray-700 mb-2">1. Pendidikan Terakhir</p>
            @php($pendidikan = old('pendidikan', []))
            <div class="space-y-3">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="checkbox" name="pendidikan[]" value="smk" @checked(in_array('smk', $pendidikan, true))>
                  <span>SMK</span>
                </label>
                <label for="jurusan_smk" class="text-sm text-gray-700">Jurusan</label>
                <input type="text" id="jurusan_smk" name="jurusan_smk" value="{{ old('jurusan_smk') }}"
                  class="px-3 py-2 border border-gray-300 rounded-md">
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="checkbox" name="pendidikan[]" value="d3" @checked(in_array('d3', $pendidikan, true))>
                  <span>D3</span>
                </label>
                <label for="jurusan_d3" class="text-sm text-gray-700">Jurusan</label>
                <input type="text" id="jurusan_d3" name="jurusan_d3" value="{{ old('jurusan_d3') }}"
                  class="px-3 py-2 border border-gray-300 rounded-md">
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="checkbox" name="pendidikan[]" value="s1" @checked(in_array('s1', $pendidikan, true))>
                  <span>S1</span>
                </label>
                <label for="jurusan_s1" class="text-sm text-gray-700">Jurusan</label>
                <input type="text" id="jurusan_s1" name="jurusan_s1" value="{{ old('jurusan_s1') }}"
                  class="px-3 py-2 border border-gray-300 rounded-md">
              </div>
            </div>
          </div>

          <div>
            <label for="keahlian_khusus" class="block text-sm font-medium text-gray-700 mb-2">2. Keahlian Khusus</label>
            <textarea id="keahlian_khusus" name="keahlian_khusus" rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md">{{ old('keahlian_khusus') }}</textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">3. Jenis Kelamin <span class="text-red-500">*</span></p>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="jenis_kelamin" value="laki_laki"
                    @checked(old('jenis_kelamin') === 'laki_laki') required>
                  <span>Laki-laki</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="jenis_kelamin" value="perempuan"
                    @checked(old('jenis_kelamin') === 'perempuan') required>
                  <span>Perempuan</span>
                </label>
              </div>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700 mb-2">4. Status Perkawinan <span class="text-red-500">*</span>
              </p>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="status_perkawinan" value="kawin"
                    @checked(old('status_perkawinan') === 'kawin') required>
                  <span>Kawin</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input type="radio" name="status_perkawinan" value="tidak_kawin"
                    @checked(old('status_perkawinan') === 'tidak_kawin') required>
                  <span>Tidak Kawin</span>
                </label>
              </div>
            </div>
          </div>

          <div>
            <label for="pengalaman_kerja" class="block text-sm font-medium text-gray-700 mb-2">5. Pengalaman
              Kerja</label>
            <textarea id="pengalaman_kerja" name="pengalaman_kerja" rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md">{{ old('pengalaman_kerja') }}</textarea>
          </div>
        </section>

        <section class="rounded-lg border border-gray-200 p-4 space-y-4">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900">Uraian Jabatan</h2>
            <button type="button" id="btn-add-uraian"
              class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Tambah Uraian</button>
          </div>
          <div id="uraian-container" class="space-y-3">
            @php($uraianJabatan = old('uraian_jabatan', ['', '', '']))
            @foreach ($uraianJabatan as $index => $uraian)
              <div class="flex items-start gap-2 uraian-row">
                <input type="text" name="uraian_jabatan[]" value="{{ $uraian }}"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-md" placeholder="Uraian {{ $index + 1 }}"
                  required>
                <button type="button"
                  class="btn-remove-uraian px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">Hapus</button>
              </div>
            @endforeach
          </div>
        </section>

        <section class="rounded-lg border border-gray-200 p-4 space-y-4">
          <h2 class="text-lg font-semibold text-gray-900">Fasilitas Yang Dibutuhkan</h2>
          @php($fasilitas = old('fasilitas_dibutuhkan', []))
          @php($fasilitasLainnya = old('fasilitas_lainnya'))
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach (['computer' => 'Computer', 'meja_dan_kursi_kerja' => 'Meja dan Kursi Kerja', 'seragam_apd' => 'Seragam & APD', 'safety_shoes' => 'Safety Shoes', 'extra_fooding' => 'Extra Fooding', 'safety_helmet' => 'Safety Helmet'] as $value => $label)
              <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="fasilitas_dibutuhkan[]" value="{{ $value }}" @checked(in_array($value, $fasilitas, true))>
                <span>{{ $label }}</span>
              </label>
            @endforeach
            <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
              <input type="checkbox" id="fasilitas_others_checkbox" name="fasilitas_dibutuhkan[]" value="others"
                @checked(in_array('others', $fasilitas, true))>
              <span>Others</span>
            </label>
          </div>
          <div id="fasilitas-others-wrapper" class="{{ in_array('others', $fasilitas, true) ? '' : 'hidden' }}">
            <label for="fasilitas_lainnya" class="block text-sm font-medium text-gray-700 mb-2">Others (Custom)</label>
            <input type="text" id="fasilitas_lainnya" name="fasilitas_lainnya" value="{{ $fasilitasLainnya }}"
              class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Contoh: Kendaraan operasional">
          </div>
        </section>

        @if (auth()->user()->hasRole('admin'))
          <section class="rounded-lg border border-blue-200 bg-blue-50/40 p-4 space-y-4">
            <h2 class="text-lg font-semibold text-blue-900">Role Attachment (Admin)</h2>
            <p class="text-sm text-blue-800">Opsional: tentukan user untuk setiap role approval pada pengajuan MPP baru.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              @foreach (($approvalRoleLabels ?? []) as $index => $roleLabel)
                <div>
                  <label for="approval_attachment_{{ $index }}"
                    class="block text-sm font-medium text-gray-700 mb-2">{{ $index + 1 }}. {{ $roleLabel }}</label>
                  <select id="approval_attachment_{{ $index }}" name="approval_attachments[{{ $index }}][approver_user_id]"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">Pilih User (Opsional)</option>
                    @foreach (($roleAttachmentUsers ?? []) as $account)
                      <option value="{{ $account['id'] }}"
                        @selected(old("approval_attachments.$index.approver_user_id") == $account['id'])>
                        {{ $account['name'] }} ({{ $account['role'] }})
                      </option>
                    @endforeach
                  </select>
                  <input type="hidden" name="approval_attachments[{{ $index }}][role]" value="{{ $roleLabel }}">
                </div>
              @endforeach
            </div>
          </section>
        @endif

        <div class="flex gap-4">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Simpan &
            Kirim</button>
          <a href="{{ route('mpp-submissions.index') }}"
            class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">Batal</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const positions = @json($positions);
    const departmentSelect = document.getElementById('department_id');
    const vacancySelect = document.getElementById('vacancy_id');
    const customJabatanWrapper = document.getElementById('custom-jabatan-wrapper');
    const customJabatanInput = document.getElementById('custom_jabatan_name');
    const oldVacancyId = '{{ old('vacancy_id') }}';
    const oldCustomJabatanName = '{{ old('custom_jabatan_name') }}';
    let initialVacancyHydration = true;

    const alasanSelect = document.getElementById('alasan_penambahan_manpower');
    const sectionPenggantian = document.getElementById('section-penggantian');
    const sectionPenambahan = document.getElementById('section-penambahan');
    const alasanKesesuaianWrapper = document.getElementById('alasan-kesesuaian-wrapper');
    const kesesuaianRadios = document.querySelectorAll('input[name="kesesuaian_man_power_plan"]');
    const fasilitasOthersCheckbox = document.getElementById('fasilitas_others_checkbox');
    const fasilitasOthersWrapper = document.getElementById('fasilitas-others-wrapper');
    const fasilitasOthersInput = document.getElementById('fasilitas_lainnya');

    function updateVacancyOptions() {
      const deptId = departmentSelect.value;
      vacancySelect.innerHTML = '<option value="">Pilih Nama Jabatan</option>';

      if (!deptId) {
        return;
      }

      const deptPositions = positions.find((item) => String(item.department_id) === String(deptId));
      const availablePositions = deptPositions ? deptPositions.positions : [];

      availablePositions.forEach((position) => {
        const option = document.createElement('option');
        option.value = position.id;
        option.textContent = position.name;

        if (initialVacancyHydration && String(oldVacancyId) === String(position.id)) {
          option.selected = true;
        }

        vacancySelect.appendChild(option);
      });

      const otherOption = document.createElement('option');
      otherOption.value = 'other';
      otherOption.textContent = 'Other (Jabatan tidak ada)';
      if (initialVacancyHydration && oldVacancyId === 'other') {
        otherOption.selected = true;
      }
      vacancySelect.appendChild(otherOption);

      toggleCustomJabatanInput();
    }

    function toggleCustomJabatanInput() {
      const isOther = vacancySelect.value === 'other';
      customJabatanWrapper.classList.toggle('hidden', !isOther);
      customJabatanInput.required = isOther;

      if (!isOther && !initialVacancyHydration) {
        customJabatanInput.value = '';
      }

      if (isOther && initialVacancyHydration && oldCustomJabatanName) {
        customJabatanInput.value = oldCustomJabatanName;
      }
    }

    function toggleReasonSections() {
      const value = alasanSelect.value;
      sectionPenggantian.classList.toggle('hidden', value !== 'penggantian_karyawan');
      sectionPenambahan.classList.toggle('hidden', value !== 'penambahan_karyawan_baru');
    }

    function toggleAlasanKesesuaian() {
      let selected = null;
      kesesuaianRadios.forEach((radio) => {
        if (radio.checked) {
          selected = radio.value;
        }
      });
      alasanKesesuaianWrapper.classList.toggle('hidden', selected !== 'di_luar_mpp');
    }

    function toggleFasilitasOthers() {
      if (!fasilitasOthersCheckbox || !fasilitasOthersWrapper || !fasilitasOthersInput) {
        return;
      }

      const isChecked = fasilitasOthersCheckbox.checked;
      fasilitasOthersWrapper.classList.toggle('hidden', !isChecked);
      fasilitasOthersInput.required = isChecked;

      if (!isChecked) {
        fasilitasOthersInput.value = '';
      }
    }

    alasanSelect.addEventListener('change', toggleReasonSections);
    kesesuaianRadios.forEach((radio) => radio.addEventListener('change', toggleAlasanKesesuaian));
    departmentSelect.addEventListener('change', function () {
      vacancySelect.value = '';
      updateVacancyOptions();
    });
    vacancySelect.addEventListener('change', toggleCustomJabatanInput);
    if (fasilitasOthersCheckbox) {
      fasilitasOthersCheckbox.addEventListener('change', toggleFasilitasOthers);
    }

    const uraianContainer = document.getElementById('uraian-container');
    const addUraianBtn = document.getElementById('btn-add-uraian');

    function attachRemoveHandlers() {
      const removeButtons = document.querySelectorAll('.btn-remove-uraian');
      removeButtons.forEach((button) => {
        button.onclick = function () {
          if (uraianContainer.querySelectorAll('.uraian-row').length <= 1) {
            return;
          }
          button.closest('.uraian-row').remove();
        };
      });
    }

    addUraianBtn.addEventListener('click', function () {
      const totalRows = uraianContainer.querySelectorAll('.uraian-row').length;
      const wrapper = document.createElement('div');
      wrapper.className = 'flex items-start gap-2 uraian-row';
      wrapper.innerHTML = '<input type="text" name="uraian_jabatan[]" class="flex-1 px-3 py-2 border border-gray-300 rounded-md" placeholder="Uraian ' + (totalRows + 1) + '" required><button type="button" class="btn-remove-uraian px-3 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">Hapus</button>';
      uraianContainer.appendChild(wrapper);
      attachRemoveHandlers();
    });

    toggleReasonSections();
    toggleAlasanKesesuaian();
    updateVacancyOptions();
    initialVacancyHydration = false;
    toggleCustomJabatanInput();
    toggleFasilitasOthers();
    attachRemoveHandlers();
  })();
</script>
@endsection