<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_model_declares_an_explicit_non_empty_fillable_list(): void
    {
        $models = [
            User::class, Department::class, Ticket::class, TicketCategory::class,
            TicketComment::class, TicketAttachment::class, TicketStatus::class,
            TicketPriority::class, TicketAssignment::class,
        ];

        foreach ($models as $class) {
            /** @var Model $model */
            $model = new $class;
            $this->assertNotEmpty($model->getFillable(), "{$class} must declare \$fillable");
            // With an explicit $fillable, Eloquent keeps the default guard of ['*'].
            $this->assertSame(['*'], $model->getGuarded(), "{$class} must not use \$guarded = []");
        }
    }

    public function test_no_model_uses_empty_guarded(): void
    {
        foreach (File::files(app_path('Models')) as $file) {
            $this->assertStringNotContainsString(
                '$guarded = []',
                $file->getContents(),
                $file->getFilename().' must not disable mass-assignment protection'
            );
        }
    }

    public function test_privileged_user_fields_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'role' => 'admin',
            'is_active' => false,
            'email_verified_at' => now(),
        ])->fresh();

        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
    }

    public function test_system_managed_ticket_fields_cannot_be_mass_assigned(): void
    {
        $ticket = new Ticket([
            'ticket_number' => 'INC-2026-999999',
            'user_id' => 999,
            'status_id' => 999,
            'resolved_at' => now(),
            'closed_at' => now(),
        ]);

        $this->assertNull($ticket->ticket_number);
        $this->assertNull($ticket->user_id);
        $this->assertNull($ticket->status_id);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
    }

    public function test_passwords_are_hashed_and_hidden(): void
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertNotSame('plain-text-password', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_attachment_storage_details_are_not_serialised(): void
    {
        $attachment = new TicketAttachment([
            'original_name' => 'a.pdf', 'file_name' => 'abc123.pdf', 'file_path' => 'tickets/1/abc123.pdf',
        ]);

        $array = $attachment->toArray();
        $this->assertArrayNotHasKey('file_path', $array);
        $this->assertArrayNotHasKey('file_name', $array);
        $this->assertSame('a.pdf', $array['original_name']);
    }
}
