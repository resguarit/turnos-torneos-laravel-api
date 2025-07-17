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
        Schema::table('jugadores', function (Blueprint $table) {
            // Ensure the foreign key constraint exists before trying to drop it
            // The name might vary depending on how it was created. Check your schema or previous migrations.
            // Common convention is 'table_column_foreign'
             if (Schema::hasColumn('jugadores', 'equipo_id')) {
                try {
                    // Attempt to drop the foreign key constraint if it exists
                    $table->dropForeign(['equipo_id']);
                } catch (\Exception $e) {
                    // Log or handle the case where the foreign key doesn't exist or has a different name
                    // You might need to manually specify the constraint name: $table->dropForeign('your_constraint_name');
                    logger("Could not drop foreign key 'equipo_id' on 'jugadores': " . $e->getMessage());
                }
                // Drop the column itself
                $table->dropColumn('equipo_id');
             }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->foreignId('equipo_id')->nullable()->after('fecha_nacimiento')->constrained('equipos')->onDelete('set null');
            // Optional: You might need logic here to repopulate equipo_id from the pivot table if rolling back
        });
    }
};
