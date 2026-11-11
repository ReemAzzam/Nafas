<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
class MobileLocalSeeder extends Seeder
{
    public function run(): void
    {
        // Existing catalog seeders use updateOrCreate; do not create duplicate users.
        $this->call([
            NafasDemoSeeder::class,
            AudioTrackSeeder::class,
            ContentImageSeeder::class,
            FactorSeeder::class,
            SosSeeder::class,
        ]);
        // Local-only demo account opens Home directly without changing real signups.
        User::where('email', 'lina@nafas.test')->firstOrFail()->forceFill([
            'email_verified_at' => now(), 'onboarding_status' => 'skipped',
        ])->save();
        User::where('email', 'admin@nafas.test')->firstOrFail()->forceFill([
            'email_verified_at' => now(), 'role' => 'admin',
        ])->save();
    }
}
