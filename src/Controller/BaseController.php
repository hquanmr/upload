<?php

namespace Upload\Controller;
use Workerman\Protocols\Http\Request;


class BaseController
{

    protected $request;
    public function __construct( Request $request)
    {   
        $this->request = $request;
   
    }

}