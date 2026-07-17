<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_invoice_settings', function (Blueprint $table) {
            $table->text('sol_user')->nullable()->change();
        });

        DB::table('electronic_invoice_settings')
            ->whereNotNull('sol_user')
            ->where('sol_user', '<>', '')
            ->orderBy('id')
            ->each(function ($setting) {
                try {
                    Crypt::decryptString($setting->sol_user);
                } catch (\Throwable) {
                    DB::table('electronic_invoice_settings')
                        ->where('id', $setting->id)
                        ->update(['sol_user' => Crypt::encryptString($setting->sol_user)]);
                }
            });
    }

    public function down(): void
    {
        // Se mantiene cifrado para no volver a exponer credenciales sensibles.
    }
};
