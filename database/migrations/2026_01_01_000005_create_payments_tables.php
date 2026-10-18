<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('fake');
            $table->string('provider_token')->nullable(); // e.g. Stripe pm_xxx – never raw card numbers
            $table->string('brand', 20);                 // VISA, MASTERCARD...
            $table->string('last4', 4);
            $table->unsignedTinyInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_extension_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);   // charge | refund
            $table->string('status', 20); // succeeded | failed | pending
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('CAD');
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_holder');
            $table->string('institution_number', 3);
            $table->string('transit_number', 5);
            $table->text('account_number'); // encrypted cast
            $table->string('account_last4', 4);
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending'); // pending | available | paid | cancelled
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
