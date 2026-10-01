<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies the real MySQL schema produced by the migrations
 * (RefreshDatabase runs migrate:fresh, so a green run = migrations work).
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        foreach ([
            'users', 'departments', 'tickets', 'ticket_categories', 'ticket_comments',
            'ticket_attachments', 'ticket_statuses', 'ticket_priorities',
            'ticket_assignments', 'notifications', 'personal_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}]");
        }
    }

    public function test_tables_have_the_specified_columns(): void
    {
        $expected = [
            'users' => ['id', 'name', 'email', 'email_verified_at', 'password', 'department_id', 'role', 'employee_id', 'phone', 'avatar', 'is_active', 'remember_token', 'created_at', 'updated_at', 'deleted_at'],
            'departments' => ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'ticket_categories' => ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'ticket_priorities' => ['id', 'name', 'description', 'level', 'color', 'sla_response_minutes', 'sla_resolution_minutes', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'ticket_statuses' => ['id', 'name', 'slug', 'description', 'color', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'tickets' => ['id', 'ticket_number', 'user_id', 'department_id', 'category_id', 'priority_id', 'status_id', 'title', 'description', 'resolution', 'resolved_at', 'closed_at', 'created_at', 'updated_at'],
            'ticket_comments' => ['id', 'ticket_id', 'user_id', 'body', 'is_internal', 'created_at', 'updated_at'],
            'ticket_attachments' => ['id', 'ticket_id', 'comment_id', 'uploaded_by', 'original_name', 'file_name', 'file_path', 'mime_type', 'file_size', 'created_at', 'updated_at'],
            'ticket_assignments' => ['id', 'ticket_id', 'assigned_to', 'assigned_by', 'assigned_at', 'unassigned_at', 'note', 'created_at', 'updated_at'],
            'notifications' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing column [{$table}.{$column}]");
            }
        }
    }

    public function test_notifications_table_uses_laravel_uuid_structure(): void
    {
        $id = collect(Schema::getColumns('notifications'))->firstWhere('name', 'id');
        $this->assertSame('char(36)', $id['type']);
    }

    public function test_required_foreign_keys_exist_with_intended_delete_rules(): void
    {
        // table.column => [referenced table, delete rule]
        $expected = [
            'users.department_id' => ['departments', 'RESTRICT'],
            'tickets.user_id' => ['users', 'RESTRICT'],
            'tickets.department_id' => ['departments', 'RESTRICT'],
            'tickets.category_id' => ['ticket_categories', 'RESTRICT'],
            'tickets.priority_id' => ['ticket_priorities', 'RESTRICT'],
            'tickets.status_id' => ['ticket_statuses', 'RESTRICT'],
            'ticket_comments.ticket_id' => ['tickets', 'CASCADE'],
            'ticket_comments.user_id' => ['users', 'RESTRICT'],
            'ticket_attachments.ticket_id' => ['tickets', 'CASCADE'],
            'ticket_attachments.comment_id' => ['ticket_comments', 'CASCADE'],
            'ticket_attachments.uploaded_by' => ['users', 'RESTRICT'],
            'ticket_assignments.ticket_id' => ['tickets', 'CASCADE'],
            'ticket_assignments.assigned_to' => ['users', 'RESTRICT'],
            'ticket_assignments.assigned_by' => ['users', 'RESTRICT'],
        ];

        $rows = DB::select(
            'SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, r.DELETE_RULE dr
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL'
        );

        $actual = [];
        foreach ($rows as $row) {
            $actual["{$row->t}.{$row->c}"] = [$row->rt, $row->dr];
        }

        foreach ($expected as $key => $rule) {
            $this->assertArrayHasKey($key, $actual, "Missing foreign key [{$key}]");
            $this->assertSame($rule, $actual[$key], "Wrong reference/delete rule for [{$key}]");
        }
    }

    public function test_required_indexes_exist(): void
    {
        // table => columns that must be the LEADING column of some index
        $expected = [
            'users' => ['role', 'department_id', 'employee_id'],
            'tickets' => ['ticket_number', 'user_id', 'department_id', 'category_id', 'priority_id', 'status_id', 'created_at'],
            'ticket_comments' => ['ticket_id', 'user_id'],
            'ticket_attachments' => ['ticket_id', 'comment_id', 'uploaded_by'],
            'ticket_assignments' => ['ticket_id', 'assigned_to', 'assigned_by'],
        ];

        foreach ($expected as $table => $columns) {
            $leading = collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'][0])->all();
            foreach ($columns as $column) {
                $this->assertContains($column, $leading, "No index leads with [{$table}.{$column}]");
            }
        }

        // is_active is covered as the 2nd column of the (role, is_active) composite.
        $this->assertTrue(
            collect(Schema::getIndexes('users'))->contains(fn ($i) => $i['columns'] === ['role', 'is_active'])
        );
    }

    public function test_unique_indexes_exist(): void
    {
        $unique = [
            'users' => [['email'], ['employee_id']],
            'departments' => [['name']],
            'ticket_categories' => [['name']],
            'ticket_priorities' => [['name'], ['level']],
            'ticket_statuses' => [['name'], ['slug']],
            'tickets' => [['ticket_number']],
        ];

        foreach ($unique as $table => $sets) {
            $indexes = collect(Schema::getIndexes($table));
            foreach ($sets as $columns) {
                $this->assertTrue(
                    $indexes->contains(fn ($i) => $i['unique'] && $i['columns'] === $columns),
                    "Missing unique index on [{$table}] (".implode(',', $columns).')'
                );
            }
        }
    }

    public function test_soft_delete_columns_only_on_the_decided_tables(): void
    {
        foreach (['users', 'departments', 'ticket_categories', 'ticket_priorities'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "[{$table}] should soft delete");
        }
        foreach (['tickets', 'ticket_comments', 'ticket_attachments', 'ticket_assignments', 'ticket_statuses', 'notifications'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'), "[{$table}] must not soft delete");
        }
    }
}
