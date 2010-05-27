CakePHP REST Plugin takes whatever your existing controller actions gather
in viewvars, reformats it in json, and outputs it to the client.
Be cause you hook it into existing actions, you only have to write your
features once, and this plugin will just unlock them as API.
The plugin know's it's being called by looking at the extension in the url: .json.

The reformatting can even change the structure of your existing viewvars by
using bi-directional xpaths. So you can extract info using an xpath, and
it will be written into API json with another xpath. If this doesn't make any
sense, look at the examples.

You attach the Rest.Rest component to a controller, but you can limit REST
activity to a single action.

For best results, 2 changes to your application have to be made.

  1 A check for REST in errors & redirects
  2 Resource mapping in your router

Based on:

- [Priminister's API presentation during CakeFest #03, Berlin][1]
- [Forked XML Serialization helper by rodrigorm][2]
- [REST documentation][3]
- [CakeDC article][4]

  [1]: http://www.cake-toppings.com/2009/07/15/cakefest-berlin/
  [2]: http://github.com/rodrigorm/rest
  [3]: http://book.cakephp.org/view/476/REST
  [4]: http://cakedc.com/eng/developer/mark_story/2008/12/02/nate-abele-restful-cakephp

I held a presentation on this plugin during the first Dutch CakePHP meetup:

- [REST presentation at slideshare][5]

  [5]: http://www.slideshare.net/kevinvz/rest-presentation-2901872


Still in testing. Todo:

 - XML (now only JSON is supported)
 - Tests
 - Documentation
 - The RestLog model that tracks usage should focus more on IP for rate-limiting than account info. This is mostly to defend against denial of server & brute force attempts
 - Cake 1.3 support
 - Maybe some Refactoring. This is pretty much the first attempt at a working plugin

License: BSD-style

# Installation

## As a git submodule

    git submodule add git://github.com/kvz/cakephp-rest-plugin.git app/plugins/rest
    git submodule update --init

## Other

Just place the files directly under: `app/plugins/rest`

# Implementation

## Controller

Beware that you can no longer use ->render() yourself

    <?php
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
    ?>

`extract` extracts variables you have in: `$this->viewVars`
and makes them available in the resulting XML or JSON under
the name you specify in the value part.

## Authorization

Check the HTTP header as shown here: http://docs.amazonwebservices.com/AmazonS3/2006-03-01/index.html?RESTAuthentication.html
You can control the `authKeyword` setting to control what keyword belongs to
your REST API. By default it uses: TRUEREST. Have your users supply a header like:
`Authorization: TRUEREST username=john&password=xxx&apikey=247b5a2f72df375279573f2746686daa`

Now, inside your controller these variables will be available by calling
`$this->Rest->credentials()`. So login anyone with e.g. `$this->Auth->login()`;

## Router

    // Add an element for each controller that you want to open up
    // in the REST API
    Router::mapResources(array('servers'));

    // Add XML + JSON to your parseExtensions
    Router::parseExtensions('rss', 'json', 'xml', 'json', 'pdf');

## Callacks

If you're using the built-in ratelimiter, you may still want a little control yourself.
I provide that in the form of 4 callbacks:

    public function restlogBeforeSave ($Rest) {}
    public function restlogAfterSave ($Rest) {}
    public function restlogBeforeFind ($Rest) {}
    public function restlogAfterFind ($Rest) {}

That will be called in you AppController if they exists.

You may want to give a specific user a specific ratelimit. In that case you can use
the following callback in your User Model:

    public static function restRatelimitMax ($Rest, $credentials = array()) { }

