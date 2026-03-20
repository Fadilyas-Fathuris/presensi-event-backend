<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Reuni',          'description' => 'Event reuni alumni pesantren'],
            ['category_name' => 'Pengajian',       'description' => 'Event pengajian dan kajian Islam'],
            ['category_name' => 'Seminar',         'description' => 'Event seminar dan workshop'],
            ['category_name' => 'Bakti Sosial',    'description' => 'Event kegiatan sosial kemasyarakatan'],
            ['category_name' => 'Olahraga',        'description' => 'Event turnamen dan kegiatan olahraga'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['category_name' => $category['category_name']],
                $category
            );
        }

        $this->command->info('Categories seeded successfully!');
    }
}