<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_share_a_document_and_the_recipient_can_edit_it(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $document = Document::create(['owner_id' => $owner->id, 'title' => 'Plan', 'content' => '<p>First draft</p>']);

        $this->withSession(['active_user_id' => $owner->id])
            ->postJson(route('api.documents.share', ['document' => $document->id]), ['email' => $recipient->email, 'permission' => 'editor'])
            ->assertOk();

        $this->withSession(['active_user_id' => $recipient->id])
            ->getJson(route('api.documents.show', ['document' => $document->id]))
            ->assertOk()->assertJsonPath('status', 'true')->assertJsonPath('data.access', 'editor')->assertJsonPath('data.can_edit', true);

        $this->withSession(['active_user_id' => $recipient->id])
            ->putJson(route('api.documents.update', ['document' => $document->id]), ['content' => '<p>Updated together</p>'])
            ->assertOk();

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'content' => '<p>Updated together</p>']);
    }

    public function test_home_page_exposes_named_api_routes_to_the_vue_client(): void
    {
        $response = $this->get(route('home'))->assertOk();
        preg_match('/data-routes="([^"]+)"/', $response->getContent(), $matches);
        $routes = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);

        $this->assertSame(route('api.documents.index'), $routes['documents'] ?? null);
        $this->assertSame(
            route('api.documents.show', ['document' => '__DOCUMENT__']),
            $routes['document'] ?? null,
        );
    }

    public function test_viewer_can_read_but_cannot_change_document_content(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $document = Document::create(['owner_id' => $owner->id, 'title' => 'Notes', 'content' => '<p>Keep safe</p>']);
        $document->collaborators()->attach($viewer->id, ['permission' => 'viewer']);

        $this->withSession(['active_user_id' => $viewer->id])
            ->getJson(route('api.documents.show', ['document' => $document->id]))
            ->assertOk()->assertJsonPath('data.can_edit', false);

        $this->withSession(['active_user_id' => $viewer->id])
            ->putJson(route('api.documents.update', ['document' => $document->id]), ['content' => '<p>Changed</p>'])
            ->assertForbidden();
    }

    public function test_saved_content_keeps_formatting_but_drops_html_attributes(): void
    {
        $owner = User::factory()->create();
        $document = Document::create(['owner_id' => $owner->id, 'title' => 'Safe notes', 'content' => '']);

        $this->withSession(['active_user_id' => $owner->id])
            ->putJson(route('api.documents.update', ['document' => $document->id]), ['content' => '<p onclick="alert(1)"><strong>Keep this</strong></p><script>alert(2)</script>'])
            ->assertOk();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'content' => '<p><strong>Keep this</strong></p>alert(2)',
        ]);
    }
}
