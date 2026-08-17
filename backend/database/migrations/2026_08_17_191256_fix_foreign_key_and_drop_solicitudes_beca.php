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
        // 1. Corregir la llave foránea en documentos_solicitud
        Schema::table('documentos_solicitud', function (Blueprint $table) {
            $table->dropForeign('documentos_solicitud_solicitud_id_foreign');
            $table->foreign('solicitud_id')
                  ->references('id')
                  ->on('solicitudes')
                  ->onDelete('cascade');
        });

        // 2. Eliminar la tabla huérfana
        Schema::dropIfExists('solicitudes_beca');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('solicitudes_beca', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('convocatoria_id');
            $table->unsignedBigInteger('carrera_id');
            $table->string('grupo')->nullable();
            $table->enum('estatus', ['pendiente', 'aceptado', 'rechazado'])->default('pendiente');
            $table->text('comentario_revision')->nullable();
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->dateTime('revisado_at')->nullable();
            $table->timestamps();
        });

        Schema::table('documentos_solicitud', function (Blueprint $table) {
            $table->dropForeign(['solicitud_id']);
            $table->foreign('solicitud_id')
                  ->references('id')
                  ->on('solicitudes_beca')
                  ->onDelete('cascade');
        });
    }
};