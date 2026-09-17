<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')
                ->nullable()
                ->unique()
                ->after('id');

            $table->timestamp('phone_verified_at')
                ->nullable()
                ->after('phone_number');

            $table->string('email')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->dropColumn([
                'phone_number',
                'phone_verified_at',
            ]);

            $table->string('email')
                ->nullable(false)
                ->change();
        });
    }
};
