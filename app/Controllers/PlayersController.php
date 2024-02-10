<?php

namespace App\Controllers;


class PlayersController extends Controller
{
    public function player($request, $response) 
    {
        $id     = $request->getAttribute('id');

        $arr=null;
        $arr[] = array('name'=>'adam', 'age'=>21, 'sport'=>'football');
        $arr[] = array('name'=>'bertil', 'age'=>22, 'sport'=>'tennis');
        $arr[] = array('name'=>'cecar', 'age'=>23, 'sport'=>'badminton');
        $arr[] = array('name'=>'david', 'age'=>24, 'sport'=>'curling');

        //echo 'id = ' . $id;

        $foundObject=$arr[$id];

        //echo 'name = ' . $found['name'];

        // 4. Make a Create Call and print the values
        try {
            forEach($arr as $player) {
                forEach ($player as $key=>$value) {
                    //echo $key . '=' . $value . '&nbsp'; 
                }
                //echo '<br />';
            }
            if($arr){
                return $response->withJson(array('status' => 'true','result'=>$foundObject) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
        }

        catch (\Exception $ex) {
            // This will print the detailed information on the exception.
            //REALLY HELPFUL FOR DEBUGGING
            echo 'Error';
        }      
    }
    
    public function players($request, $response) 
    {
        $arr=null;
        $arr[] = array('name'=>'adam', 'age'=>21);
        $arr[] = array('name'=>'bertil', 'age'=>22);
        $arr[] = array('name'=>'cecar', 'age'=>23);
        $arr[] = array('name'=>'david', 'age'=>24);

        // 4. Make a Create Call and print the values
        try {
            forEach($arr as $player) {
                forEach ($player as $key=>$value) {
                    //echo $key . '=' . $value . '&nbsp'; 
                }
                //echo '<br />';
            }
            if($arr){
                return $response->withJson(array('status' => 'true','result'=>$arr) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
        }
        catch (\Exception $ex) {
            // This will print the detailed information on the exception.
            //REALLY HELPFUL FOR DEBUGGING
            echo 'Error';
        }      
    }
}