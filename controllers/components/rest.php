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

    public $Controller;
    public $RestXml;
    public $RestJson;

    protected $_active = false;
    protected $_activeHelper = false;
    protected $_feedback = array();

    protected $_settings = array(
        // Passed as Component options
        'extensions' => array('xml', 'json'),
        'viewsFromPlugin' => true,

        // Passed as Both Helper & Component options
        'debug' => '0',
        
        // Passed as Helper options
        'view' => array(
            'extract' => array(),
        ),
    );

    public function initialize (&$Controller, $settings = array()) {
        $this->Controller = $Controller;
        $this->_settings = am($this->_settings, $settings);

        // Make it an integer always
        $this->_settings['debug'] = (int)$this->_settings['debug'];
        Configure::write('debug', $this->_settings['debug']);
        $this->Controller->set('debug', $this->_settings['debug']);

        $this->_active = in_array($this->Controller->params['url']['ext'], $this->_settings['extensions']);

        if (!$this->_active) {
            return;
        }
        
        $this->headers();

        // Attach Rest Helper to controller
        $this->Controller->helpers['Rest.' . $this->_activeHelper] = $this->_settings;
    }

    public function headers($ext = false) {
        if (!$ext) {
            $ext = $this->Controller->params['url']['ext'];
        }

        // Don't know why,  but RequestHandler isn't settings
        // Content-Type right;  so using header() for now instead
        switch($ext) {
            case 'json':
                // text/javascript
                // application/json
                if ($this->_settings['debug'] < 3) {
                    header('Content-Type: text/javascript');
                    $this->Controller->RequestHandler->setContent('json', 'text/javascript');
                    $this->Controller->RequestHandler->respondAs('json');
                }
                $this->_activeHelper = 'RestJson';
                break;
            case 'xml':
                if ($this->_settings['debug'] < 3) {
                    header('Content-Type: text/xml');
                    $this->Controller->RequestHandler->setContent('xml', 'text/xml');
                    $this->Controller->RequestHandler->respondAs('xml');
                }
                $this->_activeHelper = 'RestXml';
                break;
            default:
                trigger_error(sprintf('Unsupported extension: "%s"',
                        $this->Controller->params['url']['ext']), E_USER_ERROR);
                break;
        }
    }

    public function isActive() {
        return $this->_active;
    }

    public function helper() {
        if (!is_object($this->{$this->_activeHelper})) {
            App::import('Helper', 'Rest.'. $this->_activeHelper);
            $className = $this->_activeHelper . 'Helper';
            $this->{$this->_activeHelper} = new $className();
        }
    
        return $this->{$this->_activeHelper};
    }

    public function startup (&$Controller) {
        if (!$this->_active) {
            return;
        }
        if ($this->_settings['viewsFromPlugin']) {
            // Setup the controller so it can use
            // the view inside this plugin
            $this->Controller->layout   = false;
            $this->Controller->plugin   = 'rest';
            $this->Controller->viewPath = 'generic' . DS . $this->Controller->params['url']['ext'];
        }
    }


    public function error($format, $arg1 = null, $arg2 = null) {
        $args = func_get_args();
        if (count($args) > 1) $format = vsprintf($format, $args);
        $this->_feedback[__FUNCTION__][] = $format;
        return false;
    }
    public function info($format, $arg1 = null, $arg2 = null) {
        $args = func_get_args();
        if (count($args) > 1) $format = vsprintf($format, $args);
        $this->_feedback[__FUNCTION__][] = $format;
        return false;
    }
    public function warning($format, $arg1 = null, $arg2 = null) {
        $args = func_get_args();
        if (count($args) > 1) $format = vsprintf($format, $args);
        $this->_feedback[__FUNCTION__][] = $format;
        return false;
    }

    public function getFeedBack($format = false) {
        if (!$format) {
            return $this->_feedback;
        }
        
        $feedback = array();
        foreach ($this->_feedback as $level=>$messages) {
            foreach ($messages as $i=>$message) {
                $feedback[] = array(
                    'text' => $message,
                    'level' => $level,
                );
            }
        }

        return $feedback;
    }

    /**
     * Takes injects vars into restVars according to Xpaths in $take
     *
     * @param array $take
     * @param array $viewVars
     *
     * @return array
     */
    public function inject($take, $viewVars) {
        $result = array();
        foreach ($take as $path=>$dest) {
            if (is_numeric($path)) {
                $path = $dest;
            }

            $result = Set::insert($result, $dest, Set::extract($path, $viewVars));
        }
        
        return $result;
    }

    /**
     * Get an array of everything that needs to go into the Xml / Json
     *
     * @param array $result optional. Data collected by cake
     * 
     * @return array
     */
    public function restVars($result = array()) {
        $feedback   = $this->getFeedBack(true);

        $serverKeys = array_flip(array(
            'HTTP_HOST',
            'HTTP_USER_AGENT',
            'REMOTE_ADDR',
            'REQUEST_METHOD',
            'REQUEST_TIME',
            'REQUEST_URI',
            'SERVER_ADDR',
            'SERVER_PROTOCOL',
        ));
        $server = array_intersect_key($_SERVER, $serverKeys);
        foreach($server as $k=>$v) {
            if ($k === ($lc = strtolower($k))) {
                continue;
            }
            $server[$lc] = $v;
            unset($server[$k]);
        }
        
        $status = count(@$this->_feedback['error'])
            ? 'error'
            : 'ok';

        $restVars = array(
            'request' => array(
                'status' => $status,
                'messages' => $feedback,
                'headers' => $server,
            ),
        );

        $restVars = am($restVars, $result);
        return $restVars;
    }

    /**
     * Should be called by Controller->redirect to dump
     * an error & stop further execution.
     */
    public function abort() {
        // Automatically fetch Auth Component Errors
        if (is_object($this->Controller->Session) && @$this->Controller->Session->read('Message.auth')) {
            $this->error($this->Controller->Session->read('Message.auth.message'));
        }
        
        $this->headers();
        $xml = $this->helper()->serialize($this->restVars());
        
        // Die.. ugly. but very safe. which is what we need
        // or all Auth & Acl work could be circumvented
        die($xml);
    }

    public function beforeRender (&$Controller) {
        if (!$this->_active) return;
        
        $result = $this->inject((array)@$this->_settings[$this->Controller->action]['extract'],
            $this->Controller->viewVars);
        
        $this->Controller->set('restVars', $this->restVars($result));
    }
}
?>