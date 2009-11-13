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
            $Controller->layout     = false;
            $Controller->plugin     = 'rest';
            $Controller->viewPath   = 'generic' . DS . $Controller->params['url']['ext'];
        }
    }

    public function isActive() {
        return $this->_active;
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

    public function getFeedBack($formatted = false) {
        if ($formatted) {
            $feedback = array();
            foreach ($this->_feedback as $level=>$messages) {
                foreach ($messages as $i=>$message) {
                    $feedback[] = array(
                        'message' => $message,
                        'level' => $level,
                    );
                }
            }
            return $feedback;
        }

        return $this->_feedback;
    }

    public function extractIns($take, $viewVars) {
        // Collect Vars we want in rest
        $result = array();
        foreach ($take as $path=>$dest) {
            if (is_numeric($path)) {
                $path = $dest;
            }


            $result = Set::insert($result, $dest, Set::extract($path, $viewVars));
            //$result[$dest] = ;
        }
        
        return $result;
    }

    public function beforeRender (&$Controller) {
        if (!$this->_active) {
            return;
        }
        
        // Set debug
        Configure::write('debug', $this->_settings['debug']);
        $Controller->set('debug', $this->_settings['debug']);

        $result = $this->extractIns((array)@$this->_settings[$Controller->action]['extract'],
            $Controller->viewVars);

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

        $status = count(@$feedback['error'])
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
        
        $Controller->set('restVars', $restVars);
    }
}
?>