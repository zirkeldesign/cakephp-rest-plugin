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
    public $postData;
    
    protected $_activeHelper = false;
    protected $_feedback = array();
    protected $_blackListControllers = array(
        'App',
        'Defaults',
    );

    protected $_settings = array(
        // Component options
        'extensions' => array('xml', 'json'),
        'viewsFromPlugin' => true,
        'authKeyword' => 'TRUEREST',
        'showControllers' => true,
        'requireSecure' => false,

        // Both Helper & Component options
        'debug' => '0',
        
        // Passed as Helper options
        'view' => array(
            'extract' => array(),
        ),
    );

    public function numeric($array = array()) {
        if (empty($array)) {
            return null;
        }
        $keys = array_keys($array);
        foreach($keys as $key) {
            if (!is_numeric($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     *
     * @param <type> $data
     * @return <type>
     */
    protected function _modelizePost(&$data) {
        if (!is_array($data)) {
            return $data;
        }
        
        // Protected against Saving multiple models in one post
        // while still allowing mass-updates in the form of:
        // $this->data[1][field] = value;
        if (Set::countDim($data) === 2) {
            if (!$this->numeric($data)) {
                return $this->error('2 dimensional can only begin with numeric index');
            }
        } else if (Set::countDim($data) !== 1) {
            return $this->error('You may only send 1 dimensional posts');
        }

        // Encapsulate in Controller Model
        $data = array(
            $this->Controller->modelClass => $data,
        );

        return $data;
    }

    public function initialize (&$Controller, $settings = array()) {
        $this->Controller = $Controller;
        $this->_settings  = am($this->_settings, $settings);

        // Control Debug First
        $this->_settings['debug'] = (int)$this->_settings['debug'];
        Configure::write('debug', $this->_settings['debug']);
        $this->Controller->set('debug', $this->_settings['debug']);

        // Validate & Modify Post
        $this->postData   = $this->_modelizePost($this->Controller->data);
        if ($this->postData === false) {
            return $this->abort('Invalid post data');
        }
        
        if (!$this->isActive()) {
            return;
        }

        if (false !== $this->_settings['requireSecure']) {
            if (!isset($this->Controller->Security)
                || !is_object($this->Controller->Security)) {
                return $this->abort('You need to enable the Security component first');
            }
            $this->Controller->Security->requireSecure($this->_settings['requireSecure']);
        }
        
        $this->headers();

        // Attach Rest Helper to controller
        $this->Controller->helpers['Rest.' . $this->_activeHelper] =
            $this->_settings;
    }

    public function credentials() {
        // Have your client set a header like:
        // Authorization: TRUEREST username=john&password=xxx&apikey=247b5a2f72df375279573f2746686daa<
        // http://docs.amazonwebservices.com/AmazonS3/2006-03-01/index.html?RESTAuthentication.html

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $parts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            $match = array_shift($parts);
            if ($match === $this->_settings['authKeyword']) {
                $str = join(' ', $parts);
                parse_str($str, $credentials);
                return $credentials;
            }
        }
        
        return false;
    }

    /**
     * Get a list of Controllers where Rest component has been activated
     * uses Cache::read & Cache::write by default to tackle performance
     * issues.
     *
     * @param boolean $cached
     *
     * @return array
     */
    public function controllers($cached = true) {
        $ckey = sprintf('%s.%s', __CLASS__, __FUNCTION__);

        if (!$cached || !($restControllers = Cache::read($ckey))) {
            $restControllers = array();
            
            if (method_exists('App', 'objects')) {
                // As of cake 1.3, use App::objects instead of Configure::listObjects
                // http://code.cakephp.org/wiki/1.3/migration-guide
                $controllers = App::objects('controller', null, false);
            } else {
                $controllers = Configure::listObjects('controller', null, false);
            }

            // Unlist some controllers by default
            foreach ($this->_blackListControllers as $blackListController) {
                if (false !== ($key = array_search($blackListController, $controllers))) {
                    unset($controllers[$key]);
                }
            }

            // Instantiate all remaining controllers and check components
            foreach ($controllers as $controller) {
                $className = $controller.'Controller';
                if (!App::import('Controller', $controller)) {
                    continue;
                }
                $Controller = new $className();

                if (isset($Controller->components['Rest.Rest'])
                    || in_array('Rest.Rest', $Controller->components)) {

                    $restControllers[] = $controller;
                }
                unset($Controller);
            }

            if ($cached) {
                Cache::write($ckey, $restControllers);
            }
        }
        
        return $restControllers;
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
                return $this->abort(sprintf('Unsupported extension: "%s"',
                        $this->Controller->params['url']['ext']), E_USER_ERROR);
                break;
        }
    }

    public function isActive() {
        static $isActive;
        if (!isset($isActive)) {
            $isActive = in_array($this->Controller->params['url']['ext'],
                $this->_settings['extensions']);
        }
        return $isActive;
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
        if (!$this->isActive()) {
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
        return true;
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
                    'message' => $message,
                    'level' => $level,
                );
            }
        }

        return $feedback;
    }

    /**
     * Reformats data according to Xpaths in $take
     *
     * @param array $take
     * @param array $viewVars
     *
     * @return array
     */
    public function inject($take, $viewVars) {
        $data = array();
        foreach ($take as $path=>$dest) {
            if (is_numeric($path)) {
                $path = $dest;
            }

            $data = Set::insert($data, $dest, Set::extract($path, $viewVars));
        }
        
        return $data;
    }

    /**
     * Get an array of everything that needs to go into the Xml / Json
     *
     * @param array $data optional. Data collected by cake
     * 
     * @return array
     */
    public function response($data = array()) {
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

        // In case of edit, return what post data was received
        if (empty($data) && !empty($this->postData)) {
            $data = $this->postData;
        }

        $status = count(@$this->_feedback['error'])
            ? 'error'
            : 'ok';

        $response = array(
            'meta' => array(
                'status' => $status,
                'feedback' => $feedback,
                'request' => $server,
            ),
            'data' => $data,
        );

        // showControllers
        if ($this->_settings['showControllers']) {
            $response['meta']['controllers'] = $this->controllers();
        }
        
        return $response;
    }

    /**
     * Should be called by Controller->redirect to dump
     * an error & stop further execution.
     */
    public function abort($params = array(), $data = array()) {
        if (is_string($params)) {
            $code  = '403';
            $error = $params;
        } else {
            $code  = '200';
            $error = '';

            if (is_object($this->Controller->Session) && @$this->Controller->Session->read('Message.auth')) {
                // Automatically fetch Auth Component Errors
                $code  = '403';
                $error = $this->Controller->Session->read('Message.auth.message');
            }

            if (!empty($params['status'])) {
                $code = $params['status'];
            }
            if (!empty($params['status'])) {
                $error = $params['error'];
            }
        }
        if ($error) {
            $this->error($error);
        }
        $this->Controller->header(sprintf('HTTP/1.1 %s %s', $code, $this->codes[$code]));
        
        $this->headers();
        $xml = $this->helper()->serialize($this->response($data));
        
        // Die.. ugly. but very safe. which is what we need
        // or all Auth & Acl work could be circumvented
        die($xml);
    }

    /**
     * Collects viewVars, reformats, and makes them available as viewVar: response
     * for use in REST serialization
     *
     * @param <type> $Controller
     * 
     * @return <type>
     */
    public function beforeRender (&$Controller) {
        if (!$this->isActive()) return;
        
        $data = $this->inject((array)@$this->_settings[$this->Controller->action]['extract'],
            $this->Controller->viewVars);

        $response = $this->response($data);
        
        $this->Controller->set(compact('response'));
    }
}
?>