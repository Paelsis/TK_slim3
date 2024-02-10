<?php
require __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/config.php';
date_default_timezone_set('Europe/Stockholm');

$app = new Slim\App(["settings" => $config]);
/*ls

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});
*/
$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
    //      ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Autorization with basic HTTP auth
/*
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, 
EXPRESS OR IMPLIED, 
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. 
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
WHETHER IN AN ACTION OF CONTRACT, 
TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path"=>["/admin",],
    "secure"=> false, // When true, only allow https or localhost (http does not work when true)
    "relaxed"=>["/localhost", "tk.local", "tk.local:8080"],
    "realm"=>"Protected",
    "users" => [
        "per" => "fente33",
        "daniel" => "notengoni1mango",
        "anna" => "sommarochsol",
        "anders" => "behringsaspelarvi",
        "per@tangokompaniet.com" => "bierseoss"
    ],
]));

// Handle Dependencies
$container = $app->getContainer();

// Add logger to container
$container['logger'] = function($container) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler('../logs/tangokompaniet.log');
    $logger->pushHandler($file_handler);
    return $logger;
};

// Paypal API
$paypal = $container['settings']['paypal'];
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        $paypal['clientId'],         // ClientID
        $paypal['clientSecret']    // ClientSecret
    )
);
$container['apiContext'] = $apiContext;

// Add db container
$container['db'] = function ($container) {
   try{
       $db = $container['settings']['db'];
       $options  = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, );
       $pdo = new PDO("mysql:host=" . $db['servername'] . ";dbname=" . $db['dbname']  . ";charset=utf8",
       $db['username'], $db['password'],$options);
       $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
       return $pdo;
   }
   catch(\Exception $ex){
       return $ex->getMessage();
   }
};

// Add 
$container['Discount'] = function($container) {
    return new \App\Controllers\Discount($container);
};


$container['HomeController'] = function($container) {
    return new \App\Controllers\HomeController;
};

$container['TkTableController'] = function($container) {
    return new \App\Controllers\TkTableController($container);
};

$container['TkColumnsController'] = function($container) {
    return new \App\Controllers\TkColumnsController($container);
};

$container['TkSchoolController'] = function($container) {
    return new \App\Controllers\TkSchoolController($container);
};

$container['TkFestivalController'] = function($container) {
    return new \App\Controllers\TkFestivalController($container);
};

$container['TkDiscountController'] = function($container) {
    return new \App\Controllers\TkDiscountController($container);
};

$container['TkShopController'] = function($container) {
    return new \App\Controllers\TkShopController($container);
};

$container['TkEventController'] = function($container) {
    return new \App\Controllers\TkEventController($container);
};

$container['TkTextController'] = function($container) {
    return new \App\Controllers\TkTextController($container);
};

$container['TkInventoryController'] = function($container) {
    return new \App\Controllers\TkInventoryController($container);
};

$container['TkBamboraController'] = function($container) {
    return new \App\Controllers\TkBamboraController($container);
};

$container['TkSwishController'] = function($container) {
    return new \App\Controllers\TkSwishController($container);
};

$container['TkPaypalController'] = function($container) {
    return new \App\Controllers\TkPaypalController($container);
};

$container['TkMailController'] = function($container) {
    return new \App\Controllers\TkMailController($container);
};

$container['MailController'] = function($container) {
    return new \App\Controllers\MailController($container);
};

$container['TestMailController'] = function($container) {
    return new \App\Controllers\TestMailController($container);
};


$container['TkImageController'] = function($container) {
    return new \App\Controllers\TkImageController($container);
};

$container['ImageController'] = function($container) {
    return new \App\Controllers\ImageController($container);
};

$container['CalendarController'] = function($container) {
    return new \App\Controllers\CalendarController($container);
};

// Define a log middleware
$app->add(function ($req, $res, $next) {
    $return = $next($req, $res);

    // or you can use $this->get('logger')->info('...');
    $now = date('D dMy H:i:s');
    $this['logger']->addInfo('--- SLIM transaction at ' . $now . ' ---');
    return $return;
});

// All my routes
require __DIR__ . '/../app/routes.php';

$app->run();


