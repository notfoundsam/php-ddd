<?php

namespace Fuel\Migrations;

use Fuel\Core\DBUtil;

class Create_partner_users
{
    public function up()
    {
        DBUtil::create_table('partner_users', [
            'id' => ['type' => 'bigint', 'unsigned' => true, 'auto_increment' => true],
            'email' => ['type' => 'varchar', 'constraint' => 255],
            'password' => ['type' => 'varchar', 'constraint' => 255],
            'email_verified_at' => ['type' => 'datetime', 'null' => true],
            'last_login_at' => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ], ['id'], false, 'InnoDB', 'utf8mb4_unicode_ci');

        DBUtil::create_index('partner_users', 'email', 'uq_partner_email', 'UNIQUE');
    }

    public function down()
    {
        DBUtil::drop_table('partner_users');
    }
}
