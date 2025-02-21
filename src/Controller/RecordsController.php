<?php

namespace Upload\Controller;
use Upload\Model\UploadRecords;

class RecordsController extends BaseController
{
    public function list()
    {
        
        $records = UploadRecords::select()->toArray();
        return [200, 'Success', $records];
    }

    


}