<?php
Class RestComponent extends Object{
    public $components = array('RequestHandler');
    public $Controller;

    public $codes = array(
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
    );

    protected $_settings = array(
        // Passed as Component options
        'extensions' => array('xml', 'json'),

        // Passed as Both Helper & Component options
        'debug' => '0',

        // Passed as Helper options
        'method' => null,
    );

    public function initialize (&$Controller, $settings=array()) {
        $this->Controller = &$Controller;
        $this->_settings = am($this->_settings, $settings);

        // Make it an integer always
        $this->_settings['debug'] = (int)$this->_settings['debug'];

        // Setup the controller so it can use
        // the view inside this plugin
        $this->Controller->plugin = 'rest';
        $this->Controller->viewPath = 'generic';
    }

    public function startup (&$Controller) {

    }
    
    public function beforeRender (&$Controller) {
        $this->Controller = &$Controller;

        $this->Controller->helpers['Rest.RestXml'] = $this->_settings;
        
        if (in_array($this->Controller->params['url']['ext'], $this->_settings['extensions'])) {
            // Set debug
            Configure::write('debug', $this->_settings['debug']);
            $this->Controller->set('debug', $this->_settings['debug']);
        }
    }
}
?>