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
        Schema::connection('cvs_mysql')->create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('empno')->index(); // Employee number who performed the action
            $table->string('action'); // CREATE, UPDATE, DELETE, LOGIN, LOGOUT, etc.
            $table->string('module'); // masterlist, user_access, queue_manager, etc.
            $table->string('table_name')->nullable(); // Affected table name
            $table->string('record_id')->nullable(); // ID of affected record
            $table->json('old_values')->nullable(); // Previous values (for updates)
            $table->json('new_values')->nullable(); // New values (for creates/updates)
            $table->text('description')->nullable(); // Human readable description
            $table->string('ip_address')->nullable(); // User's IP address
            $table->text('user_agent')->nullable(); // User's browser/device info
            $table->string('session_id')->nullable(); // Session identifier
            $table->timestamp('performed_at'); // When the action was performed
            $table->timestamps(); // created_at and updated_at
            
            // Indexes for better performance
            $table->index(['empno', 'performed_at']);
            $table->index(['module', 'performed_at']);
            $table->index(['action', 'performed_at']);
            $table->index(['table_name', 'record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('cvs_mysql')->dropIfExists('audit_trails');
    }
};
