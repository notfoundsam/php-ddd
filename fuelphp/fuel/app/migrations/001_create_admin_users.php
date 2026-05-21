<?php

namespace Fuel\Migrations;

use Fuel\Core\DBUtil;

class Create_admin_users
{
    public function up()
    {
        DBUtil::create_table('admin_users', [
            'id' => ['type' => 'bigint', 'unsigned' => true, 'auto_increment' => true],
            'email' => ['type' => 'varchar', 'constraint' => 255],
            'password' => ['type' => 'varchar', 'constraint' => 255],
            'email_verified_at' => ['type' => 'datetime', 'null' => true],
            'last_login_at' => ['type' => 'datetime', 'null' => true],
            'created_at' => ['type' => 'datetime'],
            'updated_at' => ['type' => 'datetime'],
        ], ['id'], false, 'InnoDB', 'utf8mb4_unicode_ci');

        DBUtil::create_index('admin_users', 'email', 'uq_admin_email', 'UNIQUE');
    }

    public function down()
    {
        DBUtil::drop_table('admin_users');
    }
}
