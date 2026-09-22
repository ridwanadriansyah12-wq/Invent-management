<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Item;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ItemsImport implements ToCollection
{
    private $defaultCategoryId;
    
    public $importedCount = 0;
    public $updatedCount = 0;
    public $duplicateInFileCount = 0;
    public $errorCount = 0;
    
    private $processedCodes = [];

    public function __construct()
    {
        // Get or create a default category
        $category = Category::firstOrCreate(
            ['name' => 'Uncategorized'],
            ['description' => 'Imported from Excel']
        );
        $this->defaultCategoryId = $category->id;
    }

    public function collection(Collection $rows)
    {
        $headerIndex = -1;
        $headers = [];

        // 1. Cari baris mana yang berisi judul kolom (Header Row)
        foreach ($rows as $index => $row) {
            $rowArray = $row->toArray();
            
            // Bersihkan format cell
            $cellValues = array_map(function($cell) {
                return strtolower(trim((string)$cell));
            }, $rowArray);
            
            // Cek apakah baris ini adalah header (mengandung kata kunci SAP/ERP atau kata umum)
            $isHeader = false;
            foreach ($cellValues as $cell) {
                if (str_contains($cell, 'material') || str_contains($cell, 'kode') || str_contains($cell, 'code') || str_contains($cell, 'item')) {
                    $isHeader = true;
                    break;
                }
            }

            if ($isHeader) {
                $headerIndex = $index;
                
                // Buat mapping header (slugify)
                foreach ($cellValues as $i => $val) {
                    $slug = Str::slug($val, '_');
                    
                    // Handle edge cases from screenshots
                    if (str_contains($slug, 'material') && str_contains($slug, 'desc')) $slug = 'material_description';
                    elseif (str_contains($slug, 'material') || str_contains($slug, 'kode') || str_contains($slug, 'code')) $slug = 'material';
                    
                    if (str_contains($slug, 'physical_stock') || str_contains($slug, 'on_hand') || str_contains($slug, 'stok')) $slug = 'physical_stock_on_hand';
                    if (str_contains($slug, 'safety_stock') || str_contains($slug, 'min')) $slug = 'safety_stock_min';
                    if (str_contains($slug, 'rop') || str_contains($slug, 'reorder')) $slug = 'rop';
                    if (str_contains($slug, 'max')) $slug = 'max_stock';
                    
                    $headers[$i] = $slug;
                }
                break;
            }
        }

        if ($headerIndex === -1) {
            $firstRowPreview = isset($rows[0]) ? implode(' | ', $rows[0]->toArray()) : 'File Kosong';
            throw new \Exception("Gagal menemukan baris judul! Pastikan file Excel Anda memiliki kolom bernama 'Material'. \nBaris pertama yang terbaca oleh sistem: [ " . $firstRowPreview . " ]");
        }

        // 2. Loop datanya dan simpan
        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) continue; // Skip baris header dan metadata di atasnya
            
            // Mapping numeric index ke associative array
            $rowData = [];
            foreach ($row->toArray() as $i => $val) {
                if (isset($headers[$i]) && $headers[$i] !== '') {
                    $rowData[$headers[$i]] = $val;
                }
            }

            // FILTER: Skip baris jika kode material kosong
            $code = $rowData['material'] ?? null;
            if (empty($code)) {
                // Jangan hitung sebagai error jika seluruh baris kosong
                if (!empty(array_filter($rowData))) {
                    $this->errorCount++;
                }
                continue;
            }

            // FILTER: Data Ganda di dalam file Excel yang sama
            if (in_array($code, $this->processedCodes)) {
                $this->duplicateInFileCount++;
                continue;
            }
            $this->processedCodes[] = $code;

            // Simpan ke database
            $item = Item::firstOrNew(['code' => $code]);
            $isNew = !$item->exists;

            $item->category_id    = $this->defaultCategoryId;
            $item->name           = $rowData['material_description'] ?? ($item->name ?: 'Unknown Item');
            $item->unit           = $rowData['base_unit'] ?? ($item->unit ?: 'pcs');

            // FILTER: Pastikan nilai stok numerik (jika string kotor, paksa jadi float/0)
            $stockOnHand = floatval($rowData['physical_stock_on_hand'] ?? 0);
            // Jangan biarkan stok minus
            $item->stock_on_hand  = max(0, $stockOnHand); 
            
            $item->stock_on_order = isset($rowData['on_order_po_running']) ? max(0, floatval($rowData['on_order_po_running'])) : $item->stock_on_order;
            $item->stock_reserved = isset($rowData['reserved_qty']) ? max(0, floatval($rowData['reserved_qty'])) : $item->stock_reserved;
            
            $item->safety_stock   = isset($rowData['safety_stock_min']) ? max(0, floatval($rowData['safety_stock_min'])) : $item->safety_stock;
            $item->rop            = isset($rowData['rop']) ? max(0, floatval($rowData['rop'])) : $item->rop;
            $item->max_stock      = isset($rowData['max_stock']) ? max(0, floatval($rowData['max_stock'])) : $item->max_stock;

            // Default fallback untuk kolom migrasi wajib jika item baru
            if ($isNew) {
                $item->lead_time_days = 7;
                $item->coverage_period = 30;
                $this->importedCount++;
            } else {
                $this->updatedCount++;
            }

            $item->save();
        }
    }
}
