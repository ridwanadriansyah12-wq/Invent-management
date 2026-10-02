<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prism:create-admin {--name= : Nama lengkap admin} {--email= : Email admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat akun Administrator PRISM Stock (aman untuk lingkungan production)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== PRISM Stock: Pembuatan Akun Administrator ===');

        $name = $this->option('name') ?: $this->ask('Masukkan Nama Lengkap');
        while (empty(trim($name))) {
            $this->error('Nama tidak boleh kosong.');
            $name = $this->ask('Masukkan Nama Lengkap');
        }

        $email = $this->option('email') ?: $this->ask('Masukkan Alamat Email');
        while (true) {
            $validator = Validator::make(['email' => $email], [
                'email' => ['required', 'email', 'unique:users,email'],
            ]);

            if ($validator->passes()) {
                break;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            $email = $this->ask('Masukkan Alamat Email');
        }

        $password = $this->secret('Masukkan Password (minimal 8 karakter)');
        while (strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');
            $password = $this->secret('Masukkan Password (minimal 8 karakter)');
        }

        $confirmPassword = $this->secret('Ulangi Password untuk Konfirmasi');
        while ($confirmPassword !== $password) {
            $this->error('Konfirmasi password tidak cocok.');
            $confirmPassword = $this->secret('Ulangi Password untuk Konfirmasi');
        }

        $user = User::create([
            'name'     => trim($name),
            'email'    => strtolower(trim($email)),
            'password' => Hash::make($password),
            'role'     => 'admin',
        ]);

        $this->info("✓ Akun administrator berhasil dibuat! ID: {$user->id} | Email: {$user->email} | Peran: {$user->role}");

        return Command::SUCCESS;
    }
}
