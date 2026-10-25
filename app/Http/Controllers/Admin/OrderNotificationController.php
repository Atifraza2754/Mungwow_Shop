<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderNotificationController extends Controller
{
    /**
     * Check for new incoming orders since the last known order ID.
     */
    public function checkNew(Request $request): JsonResponse
    {
        $lastId = (int) $request->query('last_id', 0);
        $currentMaxId = (int) (Order::max('id') ?? 0);

        // If client doesn't have a last_id yet (first load), initialize with current max
        if ($lastId <= 0) {
            return response()->json([
                'latest_id'  => $currentMaxId,
                'has_new'    => false,
                'new_orders' => [],
            ]);
        }

        // Fetch any new orders placed after $lastId
        $newOrders = Order::query()
            ->select('id', 'order_no', 'customer_name', 'customer_phone', 'total', 'status', 'created_at')
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit(10)
            ->get();

        $formatted = $newOrders->map(function ($order) {
            return [
                'id'             => $order->id,
                'order_no'       => $order->order_no,
                'customer_name'  => $order->customer_name ?: 'Customer',
                'customer_phone' => $order->customer_phone,
                'total'          => number_format((float) $order->total, 2),
                'status'         => $order->status,
                'time'           => $order->created_at ? $order->created_at->format('h:i A') : now()->format('h:i A'),
                'view_url'       => url('/admin/orders/' . $order->id . '/edit'),
            ];
        });

        $hasNew = $formatted->isNotEmpty();
        $latestId = $hasNew ? (int) $newOrders->max('id') : max($lastId, $currentMaxId);

        return response()->json([
            'latest_id'  => $latestId,
            'has_new'    => $hasNew,
            'new_orders' => $formatted,
        ]);
    }
}
