<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (
            Schema::hasTable('user_priviliges') &&
            !Schema::hasTable('user_privileges')
        ) {
            Schema::rename('user_priviliges', 'user_privileges');
        }
    }

    public function down()
    {
        if (
            Schema::hasTable('user_privileges') &&
            !Schema::hasTable('user_priviliges')
        ) {
            Schema::rename('user_privileges', 'user_priviliges');
        }
    }
};
