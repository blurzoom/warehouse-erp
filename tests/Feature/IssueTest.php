<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_issue_without_supplier_id(): void
    {
        $warehouse = Warehouse::factory()->create();

        $issue = Issue::factory()
            ->for($warehouse)
            ->create();

        $this->assertDatabaseHas('issues', [
            'number' => $issue->number,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);
        $this->assertFalse(Schema::hasColumn('issues', 'supplier_id'));
    }

    public function test_issue_requires_existing_warehouse(): void
    {
        $this->expectException(QueryException::class);

        Issue::factory()->create([
            'warehouse_id' => 999999,
        ]);
    }

    public function test_issue_number_must_be_unique(): void
    {
        $issue = Issue::factory()->create();

        $this->expectException(QueryException::class);

        Issue::factory()->create([
            'number' => $issue->number,
        ]);
    }

    public function test_warehouse_deletion_is_restricted_when_it_has_issues(): void
    {
        $issue = Issue::factory()->create();

        $this->expectException(QueryException::class);

        $issue->warehouse->delete();
    }

    public function test_issue_deletion_is_restricted_when_it_has_items(): void
    {
        $issue = Issue::factory()->create();
        IssueItem::factory()->for($issue)->create();

        $this->expectException(QueryException::class);

        $issue->delete();
    }
}
