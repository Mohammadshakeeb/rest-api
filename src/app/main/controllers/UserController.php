<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
//use Phalcon\Http\Response\Cookies;

class UserController extends Controller
{
    public function indexAction()
    {
        //return '<h1>Hello!!!</h1>';

    }

    public function signupAction()
    {

        if ($this->request->isPost('name') || $this->request->isPost('email')) {

            // $user = new Users();

            //assign value from the form to $user
            $this->mongo->users->insertOne([
                "Name" => $this->request->getPost('name'),
                "Email" => $this->request->getPost('email'),
                "Password" => $this->request->getPost('password'),
                "Role" => "User"

            ]);

            $user = $this->mongo->users->findOne(['Email' => $this->request->getPost('email')]);
            $id = strval($user->_id);
            //  echo $id;
            //  die;
            $token = $this->token($id);
            echo $token;
            die;
        }
    }


    public function token($id)
    {
        $key = "example_key";
        $payload = array(
            "iss" => "http://example.org",
            "aud" => "http://example.com",
            "iat" => 1356999524,
            "exp" => time() * 24 + 3600,
            "role" => "user",
            "id" => $id
        );

        $jwt = JWT::encode($payload, $key, 'HS256');

        return $jwt;
    }
    public function loginAction()
    {
    }

    public function loginhelperAction()
    {

        $data = $this->request->getpost();
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        $login = $this->mongo->users->findOne(['Email' => $this->request->getpost('email'), 'Password' => $this->request->getpost('password')]);
        // die();
        if ($login) {

            header('location:http://localhost:8080/app/user/dashboard');
        } else {
            echo "Enter correct credentials";
        }
    }


    public function dashboardAction()
    {

        // $orders=$this->mongo->orders->find()->toArray();
        $orders=$this->mongo->orders->find();
        $this->view->data=$orders;
        // $orders = json_decode(json_encode($orders, true), true);
        // foreach($orders as $k => $v){
        //     echo '<pre>';
        //     print_r($v);
        // }
        // die;
          
    }

    public function logoutAction()
    {
        $this->session->destroy();
        $this->cookies->get('cookies')->delete();

        header('location:http://localhost:8080/user/login');
    }
}
