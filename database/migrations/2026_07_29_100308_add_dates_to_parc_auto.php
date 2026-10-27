<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parc_auto', function (Blueprint $table) {
            $table->date('date_ct')->nullable();
            $table->date('date_rev')->nullable();
            $table->date('date_pol')->nullable();    
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parc_auto', function (Blueprint $table) {
            $table->dropColumn(['date_ct', 'date_rev', 'date_pol']);
        });
    }
};
