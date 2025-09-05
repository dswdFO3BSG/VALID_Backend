<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert MFA Management module
        DB::connection('cvs_mysql')->table('user_modules')->insert([
            'module_id' => 8, // Assuming next available ID
            'label' => 'MFA Management',
            'icon' => 'pi pi-shield',
            'to' => '/settings/mfa-management',
            'main_menu' => '1',
            'order' => 8,
            'parent' => 6, // Assuming Settings is parent with ID 6
            'parent_to' => '/settings',
            'parent_label' => 'Settings',
            'parent_icon' => 'pi pi-cog',
            'status' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('cvs_mysql')->table('user_modules')
            ->where('module_id', 8)
            ->delete();
    }
};
