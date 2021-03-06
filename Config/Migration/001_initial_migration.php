<?php
class M4e01559381044995b3ed4801cbdd56cb extends CakeMigration
{

    /**
     * Migration description
     *
     * @var string
     * @access public
     */
    public $description = '';

    /**
     * Actions to be performed
     *
     * @var array $migration
     * @access public
     */
    public $migration = [
        'up' => [
            'create_table' => [
                'rest_logs' => [
                    'id' => ['type' => 'integer', 'null' => false, 'default' => null, 'length' => 20, 'key' => 'primary'],
                    'class' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 40, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'username' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 40, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'controller' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 40, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'action' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 40, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'model_id' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 40, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'ip' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 16, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'requested' => ['type' => 'datetime', 'null' => false, 'default' => null, 'key' => 'index'],
                    'apikey' => ['type' => 'string', 'null' => false, 'default' => null, 'length' => 64, 'key' => 'index', 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'httpcode' => ['type' => 'integer', 'null' => false, 'default' => null, 'length' => 3],
                    'error' => ['type' => 'string', 'null' => false, 'default' => null, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'ratelimited' => ['type' => 'boolean', 'null' => false, 'default' => null],
                    'data_in' => ['type' => 'text', 'null' => false, 'default' => null, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'meta' => ['type' => 'text', 'null' => false, 'default' => null, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'data_out' => ['type' => 'text', 'null' => false, 'default' => null, 'collate' => 'utf8_unicode_ci', 'charset' => 'utf8'],
                    'responded' => ['type' => 'datetime', 'null' => false, 'default' => null],
                    'created' => ['type' => 'datetime', 'null' => false, 'default' => null],
                    'modified' => ['type' => 'datetime', 'null' => false, 'default' => null],
                    'indexes' => [
                        'PRIMARY' => ['column' => 'id', 'unique' => 1],
                        'logged_in_ratelimit' => ['column' => ['apikey', 'requested'], 'unique' => 0],
                        'logged_out_ratelimit' => ['column' => ['requested', 'ip'], 'unique' => 0],
                    ],
                    'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB'],
                ],
            ],
        ],
        'down' => [
            'drop_table' => [
                'rest_logs'
            ],
        ],
    ];

    /**
     * Before migration callback
     *
     * @param string $direction, up or down direction of migration process
     * @return boolean Should process continue
     * @access public
     */
    public function before($direction)
    {
        return true;
    }

    /**
     * After migration callback
     *
     * @param string $direction, up or down direction of migration process
     * @return boolean Should process continue
     * @access public
     */
    public function after($direction)
    {
        return true;
    }
}
