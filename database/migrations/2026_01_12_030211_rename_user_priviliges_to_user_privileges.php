<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::rename('user_priviliges', 'user_privileges');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::rename('user_privileges', 'user_priviliges');
    }
};
