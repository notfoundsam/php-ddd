<?php

namespace Fuel\Migrations;

use Fuel\Core\DBUtil;

class Create_user_role_tables
{
    public function up()
    {
        DBUtil::create_table('admin_user_roles', [
            'admin_user_id' => ['type' => 'bigint', 'unsigned' => true],
            'role' => ['type' => 'varchar', 'constraint' => 64],
            'granted_at' => ['type' => 'datetime'],
        ], ['admin_user_id', 'role'], false, 'InnoDB', 'utf8mb4_unicode_ci', [
            [
                'constraint' => 'fk_admin_user_roles_user',
                'key' => 'admin_user_id',
                'reference' => ['table' => 'admin_users', 'column' => 'id'],
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ]);

        DBUtil::create_table('partner_user_roles', [
            'partner_user_id' => ['type' => 'bigint', 'unsigned' => true],
            'role' => ['type' => 'varchar', 'constraint' => 64],
            'granted_at' => ['type' => 'datetime'],
        ], ['partner_user_id', 'role'], false, 'InnoDB', 'utf8mb4_unicode_ci', [
            [
                'constraint' => 'fk_partner_user_roles_user',
                'key' => 'partner_user_id',
                'reference' => ['table' => 'partner_users', 'column' => 'id'],
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ]);

        DBUtil::create_table('customer_user_roles', [
            'customer_user_id' => ['type' => 'bigint', 'unsigned' => true],
            'role' => ['type' => 'varchar', 'constraint' => 64],
            'granted_at' => ['type' => 'datetime'],
        ], ['customer_user_id', 'role'], false, 'InnoDB', 'utf8mb4_unicode_ci', [
            [
                'constraint' => 'fk_customer_user_roles_user',
                'key' => 'customer_user_id',
                'reference' => ['table' => 'customer_users', 'column' => 'id'],
                'on_delete' => 'CASCADE',
                'on_update' => 'CASCADE',
            ],
        ]);
    }

    public function down()
    {
        DBUtil::drop_table('customer_user_roles');
        DBUtil::drop_table('partner_user_roles');
        DBUtil::drop_table('admin_user_roles');
    }
}
