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

I held a presentation during the first Dutch CakePHP meetup

- [REST presentation at slideshare][1]

  [1]: http://www.slideshare.net/kevinvz/rest-presentation-2901872



Todo:
- XML (now only JSON is supported)
- Tests

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
Beware that you can no longer use ->render() yourself


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
            return is_object(@$this->Rest) && $this->Rest->isActive();
        }

        public function redirect($url, $status = null, $exit = true) {
            if ($this->_isRest()) {
                // Just don't redirect.. Let REST die gracefully
                // Do set the HTTP code though
                parent::redirect(null, $status, false);
                $this->Rest->abort(compact('url', 'status', 'exit'));
            }

            parent::redirect($url, $status, $exit);
        }

    }

`extract` extracts variables you have in: `$this->viewVars`
and makes them available in the resulting XML or json under
the name you specify in the value part.

Authorization
-------------
Check the HTTP header as shown here: http://docs.amazonwebservices.com/AmazonS3/2006-03-01/index.html?RESTAuthentication.html
You can control the `authKeyword` setting to control what keyword belongs to
your REST API. By default it uses: TRUEREST. Have your users supply a header like:
`Authorization: TRUEREST username=john&password=xxx&apikey=247b5a2f72df375279573f2746686daa`

Now, inside your controller these variables will be available by calling
`$this->Rest->credentials()`. So login anyone with e.g. `$this->Auth->login()`;

Router
------
    // Add an element for each controller that you want to open up
    // in the REST API
    Router::mapResources(array('servers'));

    // Add xml + json to your parseExtensions
    Router::parseExtensions('rss', 'json', 'xml', 'json', 'pdf');

