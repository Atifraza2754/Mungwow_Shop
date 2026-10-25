<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    public function test_guest_cannot_access_order_notification_endpoint(): void
    {
        $response = $this->getJson(route('admin.orders.checkNew'));
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_check_orders(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('admin.orders.checkNew', ['last_id' => 0]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'latest_id',
            'has_new',
            'new_orders',
        ]);
    }

    public function test_detects_new_order_when_placed(): void
    {
        $user = User::first() ?? User::factory()->create();
        $initialMax = (int) (Order::max('id') ?? 0);

        // Place a mock order
        $order = Order::create([
            'customer_name'    => 'Ali Khan',
            'customer_phone'   => '03001234567',
            'delivery_address' => 'Gulshan e Iqbal Block 5',
            'status'           => Order::STATUS_PENDING,
            'total'            => 1250.00,
        ]);

        $response = $this->actingAs($user)->getJson(route('admin.orders.checkNew', ['last_id' => $initialMax]));

        $response->assertStatus(200);
        $response->assertJson([
            'has_new' => true,
        ]);

        $this->assertEquals($order->id, $response->json('new_orders.0.id'));
        $this->assertEquals('Ali Khan', $response->json('new_orders.0.customer_name'));

        // Clean up test order
        $order->forceDelete();
    }
}
