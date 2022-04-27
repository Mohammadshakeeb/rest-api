<?php

namespace App\Listeners;

use Phalcon\Events\Event;
use GuzzleHttp\Client;

class NotificationsListeners
{

    public function updateProductStock(Event $event, $app, $values)
    {
        // echo "hoiiii";
        $URL = "http://192.168.2.13:8080/frontend/user/updateStock";
        // echo "<pre>";
        // print_r($values['product_id']);
        $client = new Client();
        $client->request('POST', $URL, ['form_params' => $values]);
        // die;
    }

    public function updateStatus(Event $event, $app, $values)
    {

        $URL = "http://192.168.2.13:8080/frontend/user/updateStatus";
        $client = new Client();
        $client->request('PUT', $URL, ['form_params' => $values]);
    }
}
