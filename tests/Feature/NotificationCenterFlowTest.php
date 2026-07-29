<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CoordinatorReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationCenterFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('coordinator');
    }

    public function test_user_can_fetch_cached_notification_summary_and_mark_item_as_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole('coordinator');

        $user->notify(new CoordinatorReviewNotification([
            'title' => 'Revision pendiente',
            'message' => 'Hay un reporte nuevo por revisar.',
            'url' => route('dashboard'),
        ]));

        $summary = $this->actingAs($user)
            ->getJson(route('notifications.summary'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->json();

        $this->assertSame('Revision pendiente', $summary['items'][0]['title']);

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('items', []);

        $this->assertNotNull($notification->refresh()->read_at);
    }
}
