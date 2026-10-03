<?php

namespace Tests\Feature\Api;

use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use App\Notifications\TicketEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class RestApiTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    public function test_token_login_me_and_logout_revoke_only_the_current_token(): void
    {
        $response = $this->postJson('/api/auth/login', ['email' => $this->employeeA->email, 'password' => 'password'])
            ->assertCreated()->assertJsonPath('data.user.id', $this->employeeA->id);
        $plainToken = $response->json('data.access_token');
        $this->assertNotEmpty($plainToken);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $plainToken]);

        $tokenId = $this->employeeA->tokens()->firstOrFail()->id;
        $this->apiToken($plainToken)->getJson('/api/auth/user')->assertOk()->assertJsonPath('data.email', $this->employeeA->email)->assertJsonMissingPath('data.password');
        $this->apiToken($plainToken)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->apiToken($plainToken)->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_api_requires_a_token_and_refuses_invalid_credentials_and_inactive_accounts(): void
    {
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'nope'])->assertUnprocessable();
        $inactive = $this->makeUser(UserRole::Employee, $this->employeeA->department)->forceFill(['is_active' => false]);
        $inactive->save();
        $this->postJson('/api/auth/login', ['email' => $inactive->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_employee_listing_and_ticket_detail_are_limited_to_own_records_and_filtered_paginated(): void
    {
        $this->apiToken($this->employeeA->createToken('test')->plainTextToken)
            ->getJson('/api/tickets?per_page=1&sort=oldest')
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ticketA->id);
        $this->getJson('/api/tickets/'.$this->ticketB->id)->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->getJson('/api/tickets/'.$this->ticketA->id)->assertOk()
            ->assertJsonPath('data.ticket_number', $this->ticketA->ticket_number)
            ->assertJsonStructure(['data' => ['sla' => ['status', 'response_due_at', 'resolution_due_at']]])
            ->assertJsonMissing(['file_path' => $this->attA->file_path]);
        $this->getJson('/api/tickets?status=not-a-status')->assertUnprocessable()
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
        $this->apiToken($this->supportOne->createToken('staff')->plainTextToken)
            ->getJson('/api/tickets/'.$this->ticketA->id)->assertOk()->assertJsonMissingPath('data.requester.email');
    }

    public function test_support_can_view_colleague_ticket_but_modification_and_assignment_still_use_policies(): void
    {
        $this->apiToken($this->supportOne->createToken('test')->plainTextToken);
        $this->getJson('/api/tickets/'.$this->ticketB->id)->assertOk();
        $this->patchJson('/api/tickets/'.$this->ticketA->id, ['title' => 'Authorized support edit'])->assertOk()
            ->assertJsonPath('data.title', 'Authorized support edit');
        $this->patchJson('/api/tickets/'.$this->ticketB->id, ['title' => 'forbidden'])->assertForbidden();
        $this->patchJson('/api/tickets/'.$this->ticketA->id.'/status', ['status' => TicketStatusSlug::Assigned->value])->assertOk();
        $this->postJson('/api/tickets/'.$this->ticketB->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertForbidden();
        $this->apiToken($this->admin->createToken('test')->plainTextToken)->patchJson('/api/tickets/'.$this->ticketB->id.'/status', ['status' => TicketStatusSlug::Assigned->value])->assertOk();
    }

    public function test_ticket_create_update_comment_and_status_use_existing_services_and_request_authorization(): void
    {
        Storage::fake('local');
        $this->apiToken($this->employeeA->createToken('test')->plainTextToken);
        $this->postJson('/api/tickets', [
            'title' => 'API printer issue', 'description' => 'Printer is offline',
            'category_id' => $this->ticketA->category_id, 'priority_id' => $this->ticketA->priority_id,
        ])->assertCreated()->assertJsonPath('data.title', 'API printer issue');

        $this->patchJson('/api/tickets/'.$this->ticketA->id, ['title' => 'not allowed'])->assertForbidden();
        $this->postJson('/api/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Public API reply'])->assertCreated();
        $this->postJson('/api/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Employee internal note', 'is_internal' => true])->assertForbidden();
        $this->postJson('/api/tickets', ['title' => 'bad'])->assertUnprocessable();

        $this->apiToken($this->supportOne->createToken('test')->plainTextToken);
        $this->postJson('/api/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Internal note', 'is_internal' => true])->assertCreated();
        $this->getJson('/api/tickets/'.$this->ticketA->id.'/comments')->assertOk()->assertJsonFragment(['body' => 'Internal note']);
        $this->apiToken($this->employeeA->createToken('another')->plainTextToken)
            ->getJson('/api/tickets/'.$this->ticketA->id.'/comments')->assertOk()->assertJsonMissing(['body' => 'Internal note']);
    }

    public function test_assignment_endpoint_keeps_assignment_permissions_and_workflow(): void
    {
        $this->apiToken($this->supportOne->createToken('test')->plainTextToken);
        $this->postJson('/api/tickets/'.$this->ticketA2->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertCreated();
        $this->deleteJson('/api/tickets/'.$this->ticketA2->id.'/assignments/current')->assertOk();
        $this->assertSame('open', $this->ticketA2->fresh()->status->slug);

        $this->apiToken($this->employeeA->createToken('employee')->plainTextToken)
            ->postJson('/api/tickets/'.$this->ticketA2->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertForbidden();
    }

    public function test_comment_and_attachment_resources_enforce_idor_private_storage_and_validation(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put($this->attA->file_path, 'private');
        $token = $this->employeeA->createToken('test')->plainTextToken;
        $this->apiToken($token)->getJson('/api/tickets/'.$this->ticketA->id.'/attachments')->assertOk()
            ->assertJsonPath('data.0.name', 'report.pdf')->assertJsonMissing(['file_path' => $this->attA->file_path]);
        $this->getJson('/api/tickets/'.$this->ticketA->id.'/attachments/'.$this->attA->id)->assertOk();
        $this->getJson('/api/tickets/'.$this->ticketA->id.'/attachments/'.$this->attB->id)->assertNotFound();
        $this->getJson('/api/tickets/'.$this->ticketA->id.'/comments/'.$this->internalA->id)->assertForbidden();
        $this->getJson('/api/tickets/'.$this->ticketA->id.'/comments/'.$this->publicB->id)->assertNotFound();
        $this->postJson('/api/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('bad.exe', 20)])
            ->assertUnprocessable();
        $this->postJson('/api/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('proof.pdf', 20)])
            ->assertCreated()->assertJsonMissing(['file_path']);

        $this->apiToken($this->supportTwo->createToken('support')->plainTextToken)
            ->getJson('/api/tickets/'.$this->ticketA->id.'/attachments/'.$this->attA->id)->assertOk();
    }

    public function test_notifications_sla_and_lookup_endpoints_are_authorized_and_sensitive_fields_are_hidden(): void
    {
        $this->supportOne->notify(new TicketEventNotification('Safe title', 'Safe message', $this->ticketA->id, $this->ticketA->ticket_number));
        $notification = $this->supportOne->notifications()->firstOrFail();
        $this->apiToken($this->supportOne->createToken('test')->plainTextToken);
        $this->getJson('/api/notifications')->assertOk()->assertJsonFragment(['title' => 'Safe title']);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->patchJson('/api/notifications/'.$notification->id.'/read')->assertOk();
        $this->patchJson('/api/notifications/read-all')->assertOk();
        $this->apiToken($this->employeeA->createToken('employee')->plainTextToken)
            ->patchJson('/api/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->getJson('/api/lookups')->assertOk()->assertJsonStructure(['data' => ['departments', 'categories', 'priorities', 'statuses']]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $this->supportOne->id]);
    }

    private function apiToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
