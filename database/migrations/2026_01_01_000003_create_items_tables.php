<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable(); // Material icon name the app can map
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Step 2 – details
            $table->string('title')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('condition', 20)->nullable(); // like_new | good | fair | well_used
            $table->text('description')->nullable();
            $table->json('accessories')->nullable();
            $table->json('tags')->nullable();
            $table->text('rental_rules')->nullable();

            // Step 3 – pricing
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->unsignedSmallInteger('min_days')->default(1);
            $table->unsignedSmallInteger('max_days')->default(14);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->string('cancellation_policy', 20)->default('flexible');

            // Step 4 – availability
            $table->time('pickup_window_start')->nullable();
            $table->time('pickup_window_end')->nullable();
            $table->unsignedSmallInteger('advance_notice_hours')->default(0);

            // Step 5 – pickup & delivery
            $table->string('pickup_address')->nullable(); // exact, shared only after booking is paid
            $table->string('area')->nullable();           // public neighbourhood, e.g. "Mill Woods"
            $table->string('city')->nullable();
            $table->string('province', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('offers_delivery')->default(false);
            $table->unsignedSmallInteger('delivery_radius_km')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);

            // Lifecycle: draft → pending → active ↔ paused ; pending → rejected
            $table->string('status', 20)->default('draft')->index();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
            $table->index(['status', 'category_id']);
        });

        Schema::create('item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Dates the owner blocked on the availability calendar.
        Schema::create('item_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unique(['item_id', 'date']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('item_blocked_dates');
        Schema::dropIfExists('item_photos');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
