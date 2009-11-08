<?php
Class RestComponent extends Object {
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

    protected $_active = false;
    protected $_errors = array();

    protected $_settings = array(
        // Passed as Component options
        'extensions' => array('xml', 'json'),
        'viewsFromPlugin' => true,

        // Passed as Both Helper & Component options
        'debug' => '0',
        
        // Passed as Helper options
        'view' => array(
            'restVars' => array(),
        ),
    );

    public function initialize (&$Controller, $settings = array()) {
        $this->_settings = am($this->_settings, $settings);

        // Make it an integer always
        $this->_settings['debug'] = (int)$this->_settings['debug'];

        $this->_active = in_array($Controller->params['url']['ext'], $this->_settings['extensions']);

        if (!$this->_active) {
            return;
        }

        // Don't know why,  but RequestHandler isn't settings
        // Content-Type right;  so using header() for now instead
        switch($Controller->params['url']['ext']) {
            case 'json':
                // text/javascript
                // application/json
                if ($this->_settings['debug'] < 2) {
                    header('Content-Type: text/javascript');
                    $Controller->RequestHandler->setContent('json', 'text/javascript');
                    $Controller->RequestHandler->respondAs('json');
                }
                $Controller->helpers['Rest.RestJson'] = $this->_settings;
                break;
            case 'xml':
                if ($this->_settings['debug'] < 2) {
                    header('Content-Type: text/xml');
                    $Controller->RequestHandler->setContent('xml', 'text/xml');
                    $Controller->RequestHandler->respondAs('xml');
                }
                $Controller->helpers['Rest.RestXml'] = $this->_settings;
                break;
            default:
                trigger_error(sprintf('Unsupported extension: "%s"',
                        $Controller->params['url']['ext']), E_USER_ERROR);
                break;
        }

    }

    public function startup (&$Controller) {
        if (!$this->_active) {
            return;
        }
        if ($this->_settings['viewsFromPlugin']) {
            // Setup the controller so it can use
            // the view inside this plugin
            $Controller->layout     = 'default';
            $Controller->plugin     = 'rest';
            $Controller->viewPath   = 'generic' . DS . $Controller->params['url']['ext'];
        }
    }

    public function isActive() {
        return $this->_active;
    }

    public function error($format, $arg1 = null, $arg2 = null) {
        $args = func_get_args();
        if (count($args) > 1) {
            $format = vsprintf($format, $args);
        }
        $this->_errors[] = $format;
        return false;
    }

    public function getErrors($formatted = false) {
        if (empty($this->_errors)) {
            return null;
        }

        if ($formatted) {
            $errs = array();
            foreach ($this->_errors as $i=>$err) {
                $errs[] = array(
                    'error' => array(
                        'str' => $err,
                    ),
                );
            }
            return $errs;
        }

        return $this->_errors;
    }
    
    public function beforeRender (&$Controller) {
        if (!$this->_active) {
            return;
        }
        
        // Set debug
        Configure::write('debug', $this->_settings['debug']);
        $Controller->set('debug', $this->_settings['debug']);

        // Collect Vars we want in rest
        $result = array();
        foreach ((array)@$this->_settings[$Controller->action]['restVars'] as $var=>$restVar) {
            if (is_numeric($var)) {
                $var = $restVar;
            }

            if (false !== strpos($var, '::')) {
                list($containerName, $var) = explode('::', $var);
                $container = &$Controller->viewVars[$containerName];
            } else {
                $containerName = 'viewVars';
                $container = &$Controller->viewVars;
            }

            if (!isset($container[$var])) {
                $this->error('var "%s" was not found in %s',
                        $var, $containerName);
            }
            $result[$restVar] = $container[$var];
        }

        $restVars = array();
        $e = $this->getErrors(true);
        if ($e) {
            $restVars['errors'] = $e;
        }
        $restVars['results'] = $result;

        $Controller->set('restVars', $restVars);
    }
}
?>