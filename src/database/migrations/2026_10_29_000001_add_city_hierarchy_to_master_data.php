<?php

use App\Domains\MasterData\Actions\SyncCityHierarchyAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki Kota → Lokasi Kerja → Posisi untuk dropdown bertingkat /apply:
 *  - master_data.parent_id → lokasi/posisi tahu kotanya (item bertipe `city`)
 *  - master_data_location_positions → posisi apa saja yang tersedia di sebuah lokasi
 *
 * Hanya MENAMBAH kolom, tabel, dan baris: item kota dibuat dari prefix nama
 * yang sudah ada ("Bandung - Cook"), parent_id hanya diisi bila masih kosong,
 * dan mapping awal = semua posisi sekota ↔ semua lokasi sekota. Tidak ada
 * nama, item, atau data pelamar yang diubah/dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_data', function (Blueprint $table) {
            $table->foreignUuid('parent_id')->nullable()->after('type')
                ->constrained('master_data')->nullOnDelete();
        });

        Schema::create('master_data_location_positions', function (Blueprint $table) {
            $table->foreignUuid('location_id')->constrained('master_data')->cascadeOnDelete();
            $table->foreignUuid('position_id')->constrained('master_data')->cascadeOnDelete();

            $table->primary(['location_id', 'position_id']);
            $table->index('position_id');
        });

        app(SyncCityHierarchyAction::class)->execute();
    }

    public function down(): void
    {
        // Item bertipe `city` sengaja dibiarkan: rollback tidak menghapus master data.
        Schema::dropIfExists('master_data_location_positions');

        Schema::table('master_data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
