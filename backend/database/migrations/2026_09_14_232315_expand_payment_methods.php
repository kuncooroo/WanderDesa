<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expand payment.method from cash|digital to cash|qris|debit|e_wallet.
 * Legacy `digital` rows are remapped to `e_wallet`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->where('method', 'digital')
            ->update(['method' => 'e_wallet']);
    }

    public function down(): void
    {
        DB::table('payments')
            ->whereIn('method', ['qris', 'debit', 'e_wallet'])
            ->update(['method' => 'digital']);
    }
};
