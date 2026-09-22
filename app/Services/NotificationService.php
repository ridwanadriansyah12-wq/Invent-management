<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Notification;

class NotificationService
{
    /**
     * Check item stock levels and create appropriate notifications.
     * Called after every transaction.
     */
    public function checkAndNotify(Item $item): void
    {
        $this->checkOutOfStock($item);
        $this->checkReorder($item);
        $this->checkLowStock($item);
        $this->checkOverstock($item);
    }

    private function checkOutOfStock(Item $item): void
    {
        if ($item->stock_on_hand > 0) return;

        // Avoid duplicate unread notifications
        $exists = Notification::where('item_id', $item->id)
            ->where('type', 'out_of_stock')
            ->where('is_read', false)
            ->exists();

        if ($exists) return;

        Notification::create([
            'item_id'     => $item->id,
            'type'        => 'out_of_stock',
            'title'       => 'Stok Habis: ' . $item->name,
            'message'     => "Stok barang [{$item->code}] {$item->name} telah habis (0 {$item->unit}). Segera lakukan pengadaan.",
            'target_role' => 'all',
        ]);
    }

    private function checkReorder(Item $item): void
    {
        if ($item->stock_on_hand <= 0 || $item->rop <= 0) return;
        if ($item->inventory_position > $item->rop) return;

        $exists = Notification::where('item_id', $item->id)
            ->where('type', 'reorder')
            ->where('is_read', false)
            ->exists();

        if ($exists) return;

        $orderQty = $item->recommended_order_qty;

        Notification::create([
            'item_id'     => $item->id,
            'type'        => 'reorder',
            'title'       => 'Reorder: ' . $item->name,
            'message'     => "IP barang [{$item->code}] {$item->name} = {$item->inventory_position} {$item->unit} telah mencapai/melewati ROP ({$item->rop}). Rekomendasikan order: {$orderQty} {$item->unit}.",
            'target_role' => 'procurement',
        ]);
    }

    private function checkLowStock(Item $item): void
    {
        if ($item->stock_on_hand <= 0) return;
        if ($item->stock_on_hand > $item->safety_stock || $item->safety_stock <= 0) return;

        $exists = Notification::where('item_id', $item->id)
            ->where('type', 'low_stock')
            ->where('is_read', false)
            ->exists();

        if ($exists) return;

        Notification::create([
            'item_id'     => $item->id,
            'type'        => 'low_stock',
            'title'       => 'Stok Kritis: ' . $item->name,
            'message'     => "Stok [{$item->code}] {$item->name} ({$item->stock_on_hand} {$item->unit}) sudah di bawah Safety Stock ({$item->safety_stock} {$item->unit}).",
            'target_role' => 'gudang',
        ]);
    }

    private function checkOverstock(Item $item): void
    {
        if ($item->max_stock <= 0) return;
        if ($item->stock_on_hand <= $item->max_stock) return;

        $exists = Notification::where('item_id', $item->id)
            ->where('type', 'overstock')
            ->where('is_read', false)
            ->exists();

        if ($exists) return;

        Notification::create([
            'item_id'     => $item->id,
            'type'        => 'overstock',
            'title'       => 'Overstock: ' . $item->name,
            'message'     => "Stok [{$item->code}] {$item->name} ({$item->stock_on_hand} {$item->unit}) melebihi batas maksimum ({$item->max_stock} {$item->unit}).",
            'target_role' => 'procurement',
        ]);
    }

    /**
     * Notify about received PO.
     */
    public function notifyPOReceived(string $poNumber, string $itemName, float $qty, string $unit): void
    {
        Notification::create([
            'item_id'     => null,
            'type'        => 'po_received',
            'title'       => 'PO Diterima: ' . $poNumber,
            'message'     => "Purchase Order {$poNumber} untuk {$itemName} sebanyak {$qty} {$unit} telah diterima.",
            'target_role' => 'all',
        ]);
    }
}
