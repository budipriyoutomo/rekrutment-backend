<?php

namespace Tests\Feature;

use App\Domains\MasterData\Models\MasterData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function masterDataPayload(array $override = []): array
    {
        return array_merge([
            'type'      => 'position',
            'name'      => 'Backend Developer',
            'is_active' => true,
        ], $override);
    }

    private function makeMasterData(array $override = []): MasterData
    {
        return MasterData::create($this->masterDataPayload($override));
    }

    // ------------------------------------------------------------------ types

    public function test_types_returns_all_enum_types(): void
    {
        $this->getJson('/api/master-data/types')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['value' => 'position'])
            ->assertJsonFragment(['value' => 'department'])
            ->assertJsonFragment(['value' => 'location']);
    }

    public function test_types_returns_correct_count(): void
    {
        $response = $this->getJson('/api/master-data/types');

        $response->assertStatus(200);
        $this->assertCount(7, $response->json('data'));
    }

    // ------------------------------------------------------------------ index

    public function test_index_requires_type_parameter(): void
    {
        $this->getJson('/api/master-data')
            ->assertStatus(422);
    }

    public function test_index_rejects_invalid_type(): void
    {
        $this->getJson('/api/master-data?type=invalid_type')
            ->assertStatus(422);
    }

    public function test_index_returns_items_of_given_type(): void
    {
        $this->makeMasterData(['name' => 'Backend Dev', 'type' => 'position']);
        $this->makeMasterData(['name' => 'Frontend Dev', 'type' => 'position']);
        $this->makeMasterData(['name' => 'HR Dept', 'type' => 'department']);

        $this->getJson('/api/master-data?type=position')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_only_active(): void
    {
        $this->makeMasterData(['name' => 'Active Dev', 'is_active' => true]);
        $this->makeMasterData(['name' => 'Inactive Dev', 'is_active' => false]);

        $response = $this->getJson('/api/master-data?type=position&active=true');
        $response->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    // ------------------------------------------------------------------ store

    public function test_store_creates_master_data(): void
    {
        $response = $this->postJson('/api/master-data', $this->masterDataPayload([
            'name' => 'Frontend Engineer',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Frontend Engineer');

        $this->assertDatabaseHas('master_data', ['name' => 'Frontend Engineer']);
    }

    public function test_store_requires_type_and_name(): void
    {
        $this->postJson('/api/master-data', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->postJson('/api/master-data', $this->masterDataPayload([
            'type' => 'invalid_type',
        ]))->assertStatus(422);
    }

    // ------------------------------------------------------------------ update

    public function test_update_modifies_master_data(): void
    {
        $item = $this->makeMasterData(['name' => 'Old Name']);

        $this->putJson("/api/master-data/{$item->id}", $this->masterDataPayload([
            'name' => 'New Name',
        ]))->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('master_data', ['id' => $item->id, 'name' => 'New Name']);
    }

    // ------------------------------------------------------------------ toggle

    public function test_toggle_flips_active_status(): void
    {
        $item = $this->makeMasterData(['is_active' => true]);

        $this->patchJson("/api/master-data/{$item->id}/toggle")
            ->assertStatus(200);

        $updated = MasterData::find($item->id);
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_toggle_twice_restores_original_status(): void
    {
        $item = $this->makeMasterData(['is_active' => true]);

        $this->patchJson("/api/master-data/{$item->id}/toggle");
        $this->patchJson("/api/master-data/{$item->id}/toggle");

        $updated = MasterData::find($item->id);
        $this->assertTrue((bool) $updated->is_active);
    }

    // ------------------------------------------------------------------ destroy

    public function test_destroy_deletes_master_data(): void
    {
        $item = $this->makeMasterData();

        $this->deleteJson("/api/master-data/{$item->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('master_data', ['id' => $item->id]);
    }
}
