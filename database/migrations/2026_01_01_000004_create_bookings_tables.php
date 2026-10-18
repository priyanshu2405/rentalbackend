<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g. NR1042
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->time('pickup_time')->nullable();
            $table->time('return_time')->nullable();
            $table->unsignedSmallInteger('days');
            $table->boolean('delivery')->default(false);
            $table->string('delivery_address')->nullable();
            $table->text('message')->nullable();

            // Money snapshot (CAD)
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('rental_subtotal', 10, 2);
            $table->decimal('service_fee', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('refund_amount', 10, 2)->default(0);

            /*
             | requested → accepted (awaiting payment) → confirmed (paid) → active (picked up)
             |           → returned (renter returned) → completed (owner confirmed)
             | side exits: declined, cancelled, expired
             */
            $table->string('status', 20)->default('requested')->index();
            $table->string('cancellation_policy', 20)->default('flexible');

            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();

            $table->index(['item_id', 'start_date', 'end_date']);
        });

        Schema::create('booking_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->unsignedSmallInteger('extra_days');
            $table->decimal('rental_amount', 10, 2);
            $table->decimal('service_fee', 10, 2);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | declined | payment_failed | cancelled
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        // Pickup and return condition checks.
        Schema::create('condition_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // pickup | return
            $table->boolean('works')->nullable();
            $table->boolean('accessories_included')->nullable();
            $table->boolean('scratches_noted')->nullable();
            $table->json('accessories_checklist')->nullable(); // [{name, returned}]
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'type']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10); // renter (renter reviewing owner/item) | owner (owner reviewing renter)
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('condition_checks');
        Schema::dropIfExists('booking_extensions');
        Schema::dropIfExists('bookings');
    }
};
