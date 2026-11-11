<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class NafasDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'NafasDevelopmentSeeder is allowed only in local or testing environments.'
            );
        }

        $catalog = app(NafasDemoSeeder::class);

        $catalog->seedGoalsAndSupportMethods();

        $this->call(FactorSeeder::class);

        $catalog->seedCoreAccounts();
        $catalog->seedContentCatalog();

        $this->call(ContentImageSeeder::class);
        $this->call(AudioTrackSeeder::class);
        $this->call(SosSeeder::class);

        $this->call(NafasDemoActivitySeeder::class);
    }
}
