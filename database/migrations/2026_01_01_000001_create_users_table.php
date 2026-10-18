<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null for Google-only accounts
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_path')->nullable();
            $table->text('bio')->nullable();

            // Location
            $table->string('city')->nullable();
            $table->string('province', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('location_label')->nullable(); // e.g. "Edmonton, AB"
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_permission', 20)->default('while_using'); // while_using | always | never

            // Notification preferences
            $table->boolean('notify_new_items')->default(true);
            $table->boolean('notify_messages')->default(true);
            $table->boolean('notify_saved_items')->default(true);
            $table->boolean('notify_rental_updates')->default(true);

            // Reputation (denormalised from reviews)
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('purpose', 30); // verify_email | reset_password
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('users');
    }
};
