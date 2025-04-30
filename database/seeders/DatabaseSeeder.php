<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        
        Status::create([
            'name'=>'Ожидает подтверждение',
            'key'=>'pending',
            'color'=>'#a39e0ca6',
        ]);
        Status::create( [
            'name'=>'Актив',
            'key'=>'active',
            'color'=>'#0284C7',
        ]);
        Status::create([
            'name'=>'Завершенный',
            'key'=>'completed',
            'color'=>'#28B446',
        ]);
        Status::create([
            'name'=>'Отмененный',
            'key'=>'cancelled',
            'color'=>'#ff1414',
        ]);
        Status::create([
            'name'=>'Пропущено',
            'key'=>'not_arrived',
            'color'=>'#ff1414',
        ]);

        $regions=[
            "Тахтакопир районы",
            "Караозек районы",
            "Шымбай районы",
            "Кегейли районы",
            "Бозатау районы",
            "Нокис районы",
            "Хожели районы",
            "Нокис каласы", 
            "Конырат районы",
            "Мойнак районы",
            "Канлыкол районы",
            "Шоманай районы",
            "Беруний районы",
            "Торткол районы",
            "Елликкала районы",
            "Амударья районы",
        ];

        foreach ($regions as $key => $region) {
            Region::create([
                'name'=>$region
            ]);
        }

        $branches=[
            'Нокис филиал',
            'Конырат филиал',
            'Беруний филиал',
        ];
        foreach ($branches as $key => $branch) {
            Branch::create([
                'name'=>$branch
            ]);
        }
        
        User::create([
            'name' => 'Admin User',
            'login' => 'gai',
            'branch_id' => 1,
            'password' => Hash::make('g2023@@'),
        ]);
    }
}
