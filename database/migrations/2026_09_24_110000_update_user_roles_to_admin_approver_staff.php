<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 3 langkah migrasi aman untuk enum MySQL:
     * 1. Perluas enum dengan gabungan nilai lama dan baru
     * 2. Update pemetaan data: it -> admin, procurement -> approver, gudang -> staff
     * 3. Sempitkan enum menjadi ['admin', 'approver', 'staff'] dengan default 'staff'
     * 4. Validasi minimal ada 1 user admin
     */
    public function up(): void
    {
        // Langkah a: ALTER kolom role menjadi enum GABUNGAN sementara
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('it', 'procurement', 'gudang', 'admin', 'approver', 'staff') NOT NULL DEFAULT 'gudang'");

        // Langkah b: UPDATE data
        DB::table('users')->where('role', 'it')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'procurement')->update(['role' => 'approver']);
        DB::table('users')->where('role', 'gudang')->update(['role' => 'staff']);

        // Langkah c: ALTER lagi menjadi enum final
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'approver', 'staff') NOT NULL DEFAULT 'staff'");

        // Validasi: Pastikan minimal ada satu user admin setelah migrasi (jika tabel memiliki data)
        $totalUsers = DB::table('users')->count();
        if ($totalUsers > 0) {
            $adminCount = DB::table('users')->where('role', 'admin')->count();
            if ($adminCount < 1) {
                throw new \RuntimeException('Migrasi dibatalkan: Tidak ditemukan user dengan peran admin setelah pemetaan.');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Langkah a: ALTER menjadi enum gabungan sementara
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('it', 'procurement', 'gudang', 'admin', 'approver', 'staff') NOT NULL DEFAULT 'staff'");

        // Langkah b: Revert pemetaan data: admin -> it, approver -> procurement, staff -> gudang
        DB::table('users')->where('role', 'admin')->update(['role' => 'it']);
        DB::table('users')->where('role', 'approver')->update(['role' => 'procurement']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'gudang']);

        // Langkah c: Kembalikan ke enum lama
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('it', 'procurement', 'gudang') NOT NULL DEFAULT 'gudang'");
    }
};
