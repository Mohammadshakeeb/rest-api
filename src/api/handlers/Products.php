<?php
namespace App\Handler;
use Phalcon\Mvc\Model;



class Cars extends Model 
{
    public $productId;
    public $productName;
    public $productCategory;
    public $productPrice;
    public $productDescription;
    public $productExtra;
}

