<?php
// print_r(apache_get_modules());
// echo "<pre>"; print_r($_SERVER); die;
// $_SERVER["REQUEST_URI"] = str_replace("/phalt/","/",$_SERVER["REQUEST_URI"]);
// $_GET["_url"] = "/";
use Phalcon\Di\FactoryDefault;
use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Application;
use Phalcon\Url;
use Phalcon\Config;
use Phalcon\Di;
use Phalcon\Session\Manager;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Http\Response\Cookies;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Micro;


// require_once("../app/vendor/autoload.php");
require_once("../vendor/autoload.php");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = new Config([]);

// Define some absolute path constants to aid in locating resources
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Register an autoloader
$loader = new Loader();

$loader->registerNamespaces(
    [

        'App\Handler' => '../app/handlers'
    ]
);

$loader->register();

$container = new FactoryDefault();

$container->set(
    'mongo',
    function () {
        $mongo = new \MongoDB\Client("mongodb://mongo", array("username" => 'root', "password" => "password123"));
        // mongo "mongodb+srv://sandbox.g819z.mongodb.net/myFirstDatabase" --username root

        return $mongo->api->products;
    },
    true
);
$container->set(
    'view',
    function () {
        $view = new View();
        $view->setViewsDir(APP_PATH . '/views/');
        return $view;
    }
);

$container->set(
    'url',
    function () {
        $url = new Url();
        $url->setBaseUri('/');
        return $url;
    }
);

$container->set(
    'session',
    function () {
        $session = new Manager();
        $files = new Stream(
            [
                'savePath' => '/tmp',
            ]
        );

        $session
            ->setAdapter($files)
            ->start();

        return $session;
    }
);


$application = new Application($container);

$app = new Micro($container);


$app->before(
    function () use ($app) {
        $token = $app->request->getQuery("token");
        // echo $token;
        // die;

        if (!str_contains($_SERVER['REQUEST_URI'], 'gettoken')) {
            if (!$token) {
                echo 'Provide token in URL using query parameter "token"';
                die;
            }
            $key = 'example_key';
            try {
                $decoded = JWT::decode($token, new Key($key, 'HS256'));
            } catch (\Firebase\JWT\ExpiredException $e) {
                echo 'Caught exception: ',  $e->getMessage(), "\n";
                die;
            }
        }
    }
);

$app->get(
    '/products/gettoken',
    function () use ($app) {

        $key = "example_key";
        $payload = array(
            "iss" => "http://example.org",
            "aud" => "http://example.com",
            "iat" => 1356999524,
            "nbf" => 1357000000,
            "role" => "admin"
        );
        $jwt = JWT::encode($payload, $key, 'HS256');

        print_r($jwt);
    }
);

$app->get(
    '/products/search/{keyword}',
    function ($keyword) use ($app) {
        $key = "example_key";
        // $decoded = JWT::decode($token, new Key($key, 'HS256'));
        
            $keyword = urldecode($keyword);
            $keyword = explode(" ", $keyword);
            print_r($keyword);
            // $token = $this->session->get('token');
            
            // print_r($token);
            // die($token);
            $result = "";
            foreach ($keyword as $k => $val) {
                $info = $app->mongo->find(
                    [
                        '$or' => [
                            ['productName' =>['$regex'=> $val]],
                            ['productCategory' =>['$regex'=> $val]]
                        ]
                    ]
                );

                foreach ($info as $p) {
                    // print_r($p);
                    $result .= json_encode($p);
                }
            }
            print_r($result);
       
    }
);


// $app->handle(
//     $_SERVER["REQUEST_URI"]
// );

try {
    $app->handle(
        $_SERVER['REQUEST_URI']
    );
} catch (\Exception $e) {
    echo 'Exception: ', $e->getMessage();
}