<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('footer_settings')
            ->where('id', 1)
            ->update(['address' => 'Anamnagar, Kathmandu']);

        Cache::forget('footer_settings');
    }

    public function down(): void
    {
    }
};
