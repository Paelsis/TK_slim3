<?php

namespace App\Controllers;

class TkColumnsController extends Controller
{
    public function test($request, $response) 
    {
        return 'Test of TkColumnsController';
    }

    public function index($request, $response) 
    {
        try{
            $allGetVars = $request->getQueryParams();
            $tableName = $allGetVars['tableName'];
            
            //echo "tableName = $tableName";
            $con = $this->db;
            $sql = "SHOW COLUMNS FROM $tableName";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if ($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else {
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }
};    