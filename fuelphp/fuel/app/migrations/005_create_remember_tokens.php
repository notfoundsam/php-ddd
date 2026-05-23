<?php

namespace Fuel\Migrations;

use Fuel\Core\DBUtil;

class Create_remember_tokens
{
    public function up()
    {
        DBUtil::create_table('remember_tokens', [
            'selector' => ['type' => 'varchar', 'constraint' => 24],
            'audience' => ['type' => 'varchar', 'constraint' => 16],
            'user_id' => ['type' => 'bigint', 'unsigned' => true],
            'validator_hash' => ['type' => 'char', 'constraint' => 64],
            'expires_at' => ['type' => 'datetime'],
            'created_at' => ['type' => 'datetime'],
            'last_used_at' => ['type' => 'datetime', 'null' => true],
        ], ['selector'], false, 'InnoDB', 'utf8mb4_unicode_ci');

        DBUtil::create_index('remember_tokens', ['audience', 'user_id'], 'idx_remember_user');
        DBUtil::create_index('remember_tokens', 'expires_at', 'idx_remember_expires');
    }

    public function down()
    {
        DBUtil::drop_table('remember_tokens');
    }
}
