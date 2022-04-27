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
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;


// require_once("../app/vendor/autoload.php");
require_once("./vendor/autoload.php");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = new Config([]);

// Define some absolute path constants to aid in locating resources
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/api');

// Register an autoloader
$loader = new Loader();

$loader->registerNamespaces(
    [

        'App\Handler' => '../api/handlers',
        'App\Listeners' => APP_PATH . '/components'
    ]
);

$loader->register();

$container = new FactoryDefault();
$app = new Micro($container);

$container->set(
    'mongo',
    function () {
        $mongo = new \MongoDB\Client("mongodb://mongo", array("username" => 'root', "password" => "password123"));
        // mongo "mongodb+srv://sandbox.g819z.mongodb.net/myFirstDatabase" --username root

        return $mongo->api;
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

$eventManager = new EventsManager();
$eventManager->attach(
    'myevent',
    new \App\Listeners\NotificationsListeners()
);

$container->set(
    'eventManager',
    $eventManager
);

// $application = new Application($container);



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
    '/api/products/gettoken',
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
    '/api/products/search/{keyword}',
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
            $info = $app->mongo->products->find(
                [
                    '$or' => [
                        ['name' => ['$regex' => $val]],
                        ['category' => ['$regex' => $val]]
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

$app->post(
    '/api/order/create',
    function () use ($app) {
        $token = $this->request->getQuery('token');
        $key = 'example_key';
        $decodedtoken = JWT::decode($token, new Key($key, 'HS256'));
        // print_r($decodedtoken);
        $body = $this->request->getPost();
        // print_r($body);
        // die;
        // echo "shakeeb";
        if (isset($body['customer_name']) && isset($body['product_id']) && isset($body['product_name']) && isset($body['quantity'])) {
            $data = array(
                'customer_name' => $body['customer_name'],
                "customer_id" => $decodedtoken->id,
                'product_name' => $body['product_name'],
                'product_id' => $body['product_id'],
                'quantity' => $body['quantity'],
                "status" => "paid"
            );
            $this->mongo->orders->insertOne($data);
            $user = $this->mongo->orders->findOne(['product_id' => $body['product_id']]);
            $id = strval($user->_id);
            echo "Order placed successfully ";
            echo "Order Id is: " . $id;
            $update = $this->mongo->products->findOne(["_id" => new MongoDB\BSON\ObjectId($body['product_id'])]);
            // print_r($update);
            // die;
            $stock = $update->stock - $data['quantity'];
            // echo $stock;
            // die;
            $array = array(
                'stock' => $stock,
                'product_id' => $data['product_id']
            );
            $update = $this->mongo->products->updateOne(["_id" => new MongoDB\BSON\ObjectId($body['product_id'])], ['$set' => ['stock' => $stock]]);
            $eventmanager = $this->di->get('eventManager');
            $array = $eventmanager->fire('myevent:updateProductStock', $this, $array);
            echo "after event fire";

            //  echo $id;
            // print_r($user);
            // die;
        } else {
            echo "Invalid Parameters";
        }
    }
);

$app->put(
    '/api/order/update',
    function () use ($app) {
        if ($this->request->isput()) {

            $updatedstatus = $this->request->getput('status');
            $id = $this->request->getPut('id');
            $orders = $this->mongo->orders->findOne(["_id" => new MongoDB\BSON\ObjectId($id)]);
            if (isset($orders)) {
                $this->mongo->orders->updateOne(["_id" => new MongoDB\BSON\ObjectId($id)], ['$set' => ['status' => $updatedstatus]]);
                echo "order status updated successfully";
                $array = array(
                    'status' => $updatedstatus,
                    'id' => $id
                );
                $eventmanager = $this->di->get('eventManager');
                $array = $eventmanager->fire('myevent:updateStatus', $this, $array);
            } else {
                echo "order does not exists";
            }
        }
    }
);

$app->notFound(function () use ($app) {
    $app->response->setStatusCode(404, "Not Found")->sendHeaders();
    echo 'This is crazy, but this page was not found!';
});


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
