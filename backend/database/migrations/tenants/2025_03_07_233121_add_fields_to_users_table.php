<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('membership', ['user', 'admin'])->default('user')->after('password'); // Add membership column with default value
            $table->string('avatar')->nullable()->after('password');
            $table->integer('points')->default(0)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'points']);
        });
    }
};