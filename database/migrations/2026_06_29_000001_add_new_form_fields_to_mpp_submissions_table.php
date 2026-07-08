<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('mpp_submissions', function (Blueprint $table) {
      $table->string('form_version', 20)->default('old')->after('submission_type');
      $table->foreignId('vacancy_id')->nullable()->after('form_version')->constrained('vacancies')->nullOnDelete();
      $table->string('golongan')->nullable()->after('vacancy_id');

      $table->json('status_pegawai')->nullable()->after('golongan');
      $table->string('lokasi_pekerjaan', 50)->nullable()->after('status_pegawai');
      $table->date('tanggal_mulai_bekerja')->nullable()->after('lokasi_pekerjaan');
      $table->unsignedInteger('jumlah_diminta')->nullable()->after('tanggal_mulai_bekerja');

      $table->string('alasan_penambahan_manpower', 50)->nullable()->after('jumlah_diminta');
      $table->string('nama_karyawan_diganti')->nullable()->after('alasan_penambahan_manpower');
      $table->date('tanggal_keluar')->nullable()->after('nama_karyawan_diganti');
      $table->text('alasan_penggantian')->nullable()->after('tanggal_keluar');

      $table->unsignedInteger('jumlah_karyawan_ada')->nullable()->after('alasan_penggantian');
      $table->string('kesesuaian_man_power_plan', 50)->nullable()->after('jumlah_karyawan_ada');
      $table->text('alasan_kesesuaian')->nullable()->after('kesesuaian_man_power_plan');

      $table->json('pendidikan_requirements')->nullable()->after('alasan_kesesuaian');
      $table->text('keahlian_khusus')->nullable()->after('pendidikan_requirements');
      $table->string('jenis_kelamin', 30)->nullable()->after('keahlian_khusus');
      $table->string('status_perkawinan', 30)->nullable()->after('jenis_kelamin');
      $table->text('pengalaman_kerja')->nullable()->after('status_perkawinan');

      $table->json('uraian_jabatan')->nullable()->after('pengalaman_kerja');
      $table->json('fasilitas_dibutuhkan')->nullable()->after('uraian_jabatan');
      $table->json('approvals')->nullable()->after('fasilitas_dibutuhkan');

      $table->index('form_version');
      $table->index('alasan_penambahan_manpower');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('mpp_submissions', function (Blueprint $table) {
      $table->dropIndex(['form_version']);
      $table->dropIndex(['alasan_penambahan_manpower']);

      $table->dropConstrainedForeignId('vacancy_id');

      $table->dropColumn([
        'form_version',
        'golongan',
        'status_pegawai',
        'lokasi_pekerjaan',
        'tanggal_mulai_bekerja',
        'jumlah_diminta',
        'alasan_penambahan_manpower',
        'nama_karyawan_diganti',
        'tanggal_keluar',
        'alasan_penggantian',
        'jumlah_karyawan_ada',
        'kesesuaian_man_power_plan',
        'alasan_kesesuaian',
        'pendidikan_requirements',
        'keahlian_khusus',
        'jenis_kelamin',
        'status_perkawinan',
        'pengalaman_kerja',
        'uraian_jabatan',
        'fasilitas_dibutuhkan',
        'approvals',
      ]);
    });
  }
};
