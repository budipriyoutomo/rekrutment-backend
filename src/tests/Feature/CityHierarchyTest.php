<?php

namespace Tests\Feature;

use App\Domains\Application\Models\Application;
use App\Domains\MasterData\Actions\SyncCityHierarchyAction;
use App\Domains\MasterData\Models\MasterData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CityHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $type, string $name, array $override = []): MasterData
    {
        return MasterData::create(array_merge([
            'type' => $type,
            'name' => $name,
            'is_active' => true,
        ], $override));
    }

    private function legacyData(): void
    {
        $this->item('position', 'Bandung - Cook');
        $this->item('position', 'Bandung - Barista');
        $this->item('position', 'Jakarta - Driver');
        $this->item('position', 'Tanpa Kota');
        $this->item('location', 'Bandung - KARNIVOR - JL. RIAU');
        $this->item('location', 'Bandung - CENTRAL KITCHEN - GEDEBAGE');
        $this->item('location', 'Jakarta - HEAD OFFICE JAKARTA');
    }

    private function city(string $name): MasterData
    {
        return MasterData::where('type', 'city')->where('name', $name)->firstOrFail();
    }

    private function named(string $type, string $name): MasterData
    {
        return MasterData::where('type', $type)->where('name', $name)->firstOrFail();
    }

    // ------------------------------------------------------------------ backfill

    public function test_sync_creates_cities_parents_and_initial_mapping_without_deleting(): void
    {
        $this->legacyData();
        $before = MasterData::whereIn('type', ['position', 'location'])->orderBy('id')->pluck('name', 'id');

        $result = app(SyncCityHierarchyAction::class)->execute();

        $this->assertSame(2, $result['cities_created']);
        $this->assertSame(6, $result['parents_set']);
        $this->assertSame(1, $result['without_city']);
        // Bandung: 2 lokasi × 2 posisi, Jakarta: 1 × 1
        $this->assertSame(5, $result['mappings_created']);

        $after = MasterData::whereIn('type', ['position', 'location'])->orderBy('id')->pluck('name', 'id');
        $this->assertEquals($before, $after, 'Nama & jumlah item lama harus tetap');

        $bandung = $this->city('Bandung');
        $this->assertSame($bandung->id, $this->named('location', 'Bandung - KARNIVOR - JL. RIAU')->parent_id);
        $this->assertNull($this->named('position', 'Tanpa Kota')->parent_id);
        $this->assertSame(
            ['Bandung - Barista', 'Bandung - Cook'],
            $this->named('location', 'Bandung - KARNIVOR - JL. RIAU')->positions()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_sync_is_idempotent_and_keeps_curated_mapping(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');
        $riau->positions()->sync([$this->named('position', 'Bandung - Cook')->id]);

        $result = app(SyncCityHierarchyAction::class)->execute();

        $this->assertSame(0, $result['cities_created']);
        $this->assertSame(0, $result['mappings_created']);
        $this->assertSame(2, MasterData::where('type', 'city')->count());
        $this->assertSame(['Bandung - Cook'], $riau->positions()->pluck('name')->all());
    }

    // ------------------------------------------------------------------ apply options

    public function test_apply_options_returns_active_tree_and_hides_empty_branches(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        // Lokasi tanpa mapping disembunyikan, posisi nonaktif disembunyikan
        $this->named('location', 'Bandung - CENTRAL KITCHEN - GEDEBAGE')->positions()->sync([]);
        $this->named('position', 'Bandung - Barista')->update(['is_active' => false]);
        // Kota nonaktif disembunyikan seluruhnya
        $this->city('Jakarta')->update(['is_active' => false]);

        $this->getJson('/api/master-data/apply-options')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bandung')
            ->assertJsonCount(1, 'data.0.locations')
            ->assertJsonPath('data.0.locations.0.name', 'Bandung - KARNIVOR - JL. RIAU')
            ->assertJsonPath('data.0.locations.0.label', 'KARNIVOR - JL. RIAU')
            ->assertJsonCount(1, 'data.0.locations.0.positions')
            ->assertJsonPath('data.0.locations.0.positions.0.name', 'Bandung - Cook')
            ->assertJsonPath('data.0.locations.0.positions.0.label', 'Cook');
    }

    // ------------------------------------------------------------------ admin CRUD & mapping

    public function test_create_location_with_city_prefixes_name(): void
    {
        $this->actingAsAdmin();
        $bandung = $this->item('city', 'Bandung');
        $this->item('city', 'Jakarta');

        $this->postJson('/api/master-data', ['type' => 'location', 'name' => 'OUTLET BARU', 'parent_id' => $bandung->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bandung - OUTLET BARU')
            ->assertJsonPath('data.parentId', $bandung->id);

        // Ganti kota: prefix kota lama diganti, bukan ditumpuk
        $id = $this->named('location', 'Bandung - OUTLET BARU')->id;
        $this->putJson("/api/master-data/{$id}", [
            'type' => 'location', 'name' => 'Bandung - OUTLET BARU', 'parent_id' => $this->city('Jakarta')->id,
        ])->assertOk()->assertJsonPath('data.name', 'Jakarta - OUTLET BARU');
    }

    public function test_parent_must_be_a_city(): void
    {
        $this->actingAsAdmin();
        $position = $this->item('position', 'Cook');

        $this->postJson('/api/master-data', ['type' => 'location', 'name' => 'X', 'parent_id' => $position->id])
            ->assertStatus(422);
    }

    public function test_city_with_children_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $this->deleteJson('/api/master-data/'.$this->city('Bandung')->id)->assertStatus(422);
        $this->assertDatabaseHas('master_data', ['type' => 'city', 'name' => 'Bandung']);
    }

    public function test_sync_location_positions(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');
        $cook = $this->named('position', 'Bandung - Cook');
        $global = $this->named('position', 'Tanpa Kota');

        $this->putJson("/api/master-data/locations/{$riau->id}/positions", ['position_ids' => [$cook->id, $global->id]])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/master-data/locations/{$riau->id}/positions")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sync_rejects_position_from_other_city(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');
        $driver = $this->named('position', 'Jakarta - Driver');

        $this->putJson("/api/master-data/locations/{$riau->id}/positions", ['position_ids' => [$driver->id]])
            ->assertStatus(422);
        $this->assertSame(2, $riau->positions()->count());
    }

    public function test_mapping_endpoints_require_permission(): void
    {
        $this->actingAsUserWithPermissions(['applicants']);
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();
        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');

        $this->putJson("/api/master-data/locations/{$riau->id}/positions", ['position_ids' => []])
            ->assertStatus(403);
    }

    public function test_position_saves_locations_and_lists_them(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $bandung = $this->city('Bandung');
        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');
        $ck = $this->named('location', 'Bandung - CENTRAL KITCHEN - GEDEBAGE');

        $this->postJson('/api/master-data', [
            'type' => 'position', 'name' => 'Waiter', 'parent_id' => $bandung->id, 'location_ids' => [$riau->id],
        ])->assertOk();
        $waiter = $this->named('position', 'Bandung - Waiter');
        $this->assertSame([$riau->id], $waiter->locations()->pluck('master_data.id')->all());

        // Satu posisi bisa aktif di lebih dari satu lokasi
        $this->putJson("/api/master-data/{$waiter->id}", [
            'type' => 'position', 'name' => 'Waiter', 'parent_id' => $bandung->id, 'location_ids' => [$riau->id, $ck->id],
        ])->assertOk();
        $this->assertSame(2, $waiter->locations()->count());

        $row = collect($this->getJson('/api/master-data?type=position')->assertOk()->json('data'))
            ->firstWhere('id', $waiter->id);
        $this->assertSame(2, $row['locationsCount']);
        $this->assertEqualsCanonicalizing(
            ['Bandung - KARNIVOR - JL. RIAU', 'Bandung - CENTRAL KITCHEN - GEDEBAGE'],
            array_column($row['locations'], 'name')
        );

        // Tanpa location_ids: mapping tidak disentuh (klien lama)
        $this->putJson("/api/master-data/{$waiter->id}", [
            'type' => 'position', 'name' => 'Waiter', 'parent_id' => $bandung->id,
        ])->assertOk();
        $this->assertSame(2, $waiter->locations()->count());

        // Kosongkan
        $this->putJson("/api/master-data/{$waiter->id}", [
            'type' => 'position', 'name' => 'Waiter', 'parent_id' => $bandung->id, 'location_ids' => [],
        ])->assertOk();
        $this->assertSame(0, $waiter->locations()->count());
    }

    public function test_position_rejects_location_from_other_city(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $cook = $this->named('position', 'Bandung - Cook');
        $hq = $this->named('location', 'Jakarta - HEAD OFFICE JAKARTA');

        $this->putJson("/api/master-data/{$cook->id}", [
            'type' => 'position', 'name' => 'Cook', 'parent_id' => $this->city('Bandung')->id, 'location_ids' => [$hq->id],
        ])->assertStatus(422)->assertJsonValidationErrors('location_ids');
        $this->assertSame(2, $cook->locations()->count());

        // Posisi umum boleh di lokasi kota mana pun
        $global = $this->named('position', 'Tanpa Kota');
        $riau = $this->named('location', 'Bandung - KARNIVOR - JL. RIAU');
        $this->putJson("/api/master-data/{$global->id}", [
            'type' => 'position', 'name' => 'Tanpa Kota', 'parent_id' => null, 'location_ids' => [$hq->id, $riau->id],
        ])->assertOk();
        $this->assertSame(2, $global->locations()->count());
    }

    public function test_changing_position_city_detaches_other_city_locations(): void
    {
        $this->actingAsAdmin();
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $cook = $this->named('position', 'Bandung - Cook');
        $this->putJson("/api/master-data/{$cook->id}", [
            'type' => 'position', 'name' => 'Cook', 'parent_id' => $this->city('Jakarta')->id,
        ])->assertOk()->assertJsonPath('data.name', 'Jakarta - Cook');

        $this->assertSame(0, $cook->locations()->count());
    }

    // ------------------------------------------------------------------ submit /apply

    private function submit(array $additional, array $extra = [])
    {
        Storage::fake(config('filesystems.upload_disk'));

        $payload = array_merge([
            'personalInfo' => ['fullName' => 'Rina Quick', 'gender' => 'Perempuan'],
            'contactInfo' => ['email' => 'rina@example.com', 'phone' => '08123456789'],
            'education' => [], 'certifications' => [], 'workExperience' => [],
            'parentInfo' => [], 'spouseInfo' => [],
            'additionalInfo' => array_merge(['quickApply' => true], $additional),
        ], $extra);

        return $this->post('/api/applications/quick', [
            'payload' => json_encode($payload),
            'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ], ['Accept' => 'application/json']);
    }

    public function test_submit_accepts_mapped_combination_and_keeps_text_values(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $this->submit([
            'city' => 'Bandung',
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            'positionApplied' => 'Bandung - Cook',
        ])->assertOk();

        $info = Application::firstOrFail()->additional_info;
        $this->assertSame('Bandung - Cook', $info['positionApplied']);
        $this->assertSame('Bandung - KARNIVOR - JL. RIAU', $info['workLocation']);
        $this->assertSame('Bandung', $info['city']);
    }

    public function test_submit_rejects_unmapped_position(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();
        $this->named('location', 'Bandung - KARNIVOR - JL. RIAU')->positions()
            ->sync([$this->named('position', 'Bandung - Barista')->id]);

        $this->submit([
            'city' => 'Bandung',
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            'positionApplied' => 'Bandung - Cook',
        ])->assertStatus(422)->assertJsonValidationErrors('additionalInfo.positionApplied');

        $this->assertSame(0, DB::table('applications')->count());
    }

    public function test_submit_rejects_location_outside_city(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();

        $this->submit([
            'city' => 'Jakarta',
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            'positionApplied' => 'Jakarta - Driver',
        ])->assertStatus(422)->assertJsonValidationErrors('additionalInfo.workLocation');
    }

    public function test_submit_without_city_keeps_legacy_behaviour(): void
    {
        $this->legacyData();
        app(SyncCityHierarchyAction::class)->execute();
        $this->named('location', 'Bandung - KARNIVOR - JL. RIAU')->positions()->sync([]);

        $this->submit([
            'workLocation' => 'Bandung - KARNIVOR - JL. RIAU',
            'positionApplied' => 'Bandung - Cook',
        ])->assertOk();
    }
}
