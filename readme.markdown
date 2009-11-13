Not ready for production use!
=============================

Based on:

- [Priminister's API presentation during CakeFest #03, Berlin][1]
- [Forked XML Serialization helper by rodrigorm][2]
- [REST documentation][3]
- [CakeDC article][4]

  [1]: http://www.cake-toppings.com/2009/07/15/cakefest-berlin/
  [2]: http://github.com/rodrigorm/rest
  [3]: http://book.cakephp.org/view/476/REST
  [4]: http://cakedc.com/eng/developer/mark_story/2008/12/02/nate-abele-restful-cakephp

License: BSD-style

Installation
=============================

As a git submodule
------------------

    git submodule add git://github.com/kvz/cakephp-rest-plugin.git app/plugins/rest
    git submodule update --init

Other
-----
Just place the files directly under: `app/plugins/rest`

Implementation
==============

Controller
-----------
    class ServersController extends AppController {
        public $components = array(
            'RequestHandler',
            'Rest.Rest' => array(
                'debug' => 0,
                'view' => array(
                    'extract' => array('server.Server' => 'servers.0'),
                ),
                'index' => array(
                    'extract' => array('rows.{n}.Server' => 'servers'),
                ),
            ),
        );

        /**
         * Shortcut so you can check in your Controllers wether
         * REST Component is currently active.
         *
         * Use it in your ->redirect() and ->flash() methods
         * to forward errors to REST with e.g. $this->Rest->error()
         *
         * @return boolean
         */
        protected function _isRest() {
            return is_object($this->Rest) && $this->Rest->isActive();
        }

        public function redirect($url, $status = null, $exit = true) {
            if ($this->_isAjax()) {
                // Ajax stuff can't redirect
                $flash = $this->Session->read('Message.flash');
                $this->Session->del('Message.flash');
                echo json_encode($flash);
                exit;
            } elseif ($this->_isRest()) {
                // Just don't redirect.. Let REST die gracefully
                $this->Rest->abort($this);
            } else {
                parent::redirect($url, $status, $exit);
            }
        }

    }

`extract` extracts variables you have in: `$this->viewVars`
and makes them available in the resulting XML or json under
the name you specify in the value part.

Router
------
    // Add an element for each controller that you want to open up
    // in the REST API
    Router::mapResources(array('servers'));

    // Add xml + json to your parseExtensions
    Router::parseExtensions('rss', 'json', 'xml', 'json', 'pdf');

