<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        
        User::create([
            'name' => 'Admin User',
            'login' => 'admin',
            'password' => Hash::make('admin'),
        ]);
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
    }
}
