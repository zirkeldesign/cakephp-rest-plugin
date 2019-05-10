<?php
/**
 * Plugin: Rest, Activation Class
 *
 * PHP version 5
 *
 * @category    Config
 * @package     Croogo
 * @subpackage  REST
 * @version     1.0
 * @author      Daniel Sturm <d.sturm@zirkeldesign.de>
 */
class RestActivation
{

    /**
     * Schema directory
     *
     * @var string
     */
    private $SchemaDir;

    /**
     * DB connection
     *
     * @var object
     */
    private $db;

    /**
     * Uses
     *
     * @var array
     */
    public $uses = [
        'Session'
    ];

    /**
     * Plugin name
     *
     * @var string
     */
    public $pluginName = 'Rest';

    /**
     * Constructor
     *
     * @return
     */
    public function __construct()
    {
        $this->SchemaDir = APP . 'Plugin' . DS . $this->pluginName . DS . 'Config' . DS . 'Schema';
        $this->db = &ConnectionManager::getDataSource('default');
    }

    /**
     * onActivate will be called if this returns true
     *
     * @param  object $controller Controller
     *
     * @return bool
     */
    public function beforeActivation(&$controller)
    {
        App::uses('CakeSchema', 'Model');

        include_once($this->SchemaDir . DS . 'schema.php');

        $tables = $this->db->listSources();

        $CakeSchema = new CakeSchema();
        $SubSchema = new RestSchema();

        $errors = [];

        foreach ($SubSchema->tables as $table => $config) {
            if (in_array($table, ['i18n'])) {
                continue;
            }
            $sub_table_name = $this->db->config['prefix'] . Inflector::tableize($table);
            if (!in_array($sub_table_name, $tables)) {
                if (!$this->db->execute($this->db->createSchema($SubSchema, $table))) {
                    $errors[] = $table;
                }
            } elseif (CakePlugin::loaded($this->pluginName)) {
                // add columns to existing table if neccessary.
                $OldSchema = new CakeSchema(['plugin' => $this->pluginName]);
                $old_schema = $OldSchema->read();

                $alter = $SubSchema->compare($old_schema);
                unset($alter[$table]['drop'], $alter[$table]['change']);

                if (!$this->db->execute($this->db->alterSchema($alter))) {
                    return false;
                }
            }
        }

        // Ignore the cache since the tables wont be inside the cache at this point.
        $fileName = TMP . 'cache' . DS . 'models/cake_model_' . ConnectionManager::getSourceName($this->db) . '_' . $this->db->config["database"] . '_list';
        if (file_exists($fileName)) {
            unlink($fileName);
        }
        #$this->db->sources(true);

        if (count($errors)) {
            $this->Session->flash(join(',<br />', $errors));

            return false;
        } else {
            return true;
        }
    }

    /**
     * Called after activating the plugin in ExtensionsPluginsController::admin_toggle()
     *
     * @param object $controller Controller
     * @return void
     */
    public function onActivation(&$controller)
    {
        // ACL: set ACOs with permissions
        $controller->Croogo->addAco('Logs');
        // RestController
        $controller->Croogo->addAco('Logs/admin_index');
        // RestController::admin_index()
        $controller->Croogo->addAco('Logs/index', [
            'registered',
            'public'
        ]);
    }

    /**
     * onDeactivate will be called if this returns true
     *
     * @param  object $controller Controller
     * @return bool
     */
    public function beforeDeactivation(&$controller)
    {
        return true;
    }

    /**
     * Called after deactivating the plugin in ExtensionsPluginsController::admin_toggle()
     *
     * @param object $controller Controller
     * @return void
     */
    public function onDeactivation(&$controller)
    {
        // ACL: remove ACOs with permissions
        $controller->Croogo->removeAco('Logs');
    }
}

/* end of file CourseActivation.php */
