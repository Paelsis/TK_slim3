<?php

namespace App\Controllers;

class UserController extends Controller
{
    public function test($request, $response) 
    {
        return 'TkShopController test';
    }
   
    public function userId($request, $response) 
    {
        try{
            $id = $request->getAttribute('id');
            $con = $this->db;
            $sql = "SELECT * FROM users WHERE id = :id";
            $pre  = $con->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $values = array(
            ':id' => $id);
            $pre->execute($values);
            $result = $pre->fetch();
            if($result){
                return $response->withJson(array('status' => 'true','result'=> $result),200);
            }else{
                return $response->withJson(array('status' => 'User Not Found'),422);
            }
           
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
     
    }
    
    public function User($request, $response) 
    {
        try{
            $con = $this->db;
            $sql = "INSERT INTO `users`(`username`, `email`,`password`) VALUES (:username,:email,:password)";
            $pre  = $con->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $values = array(
            ':username' => $request->getParam('username'),
            ':email' => $request->getParam('email'),
            //Using hash for password encryption
            'password' => password_hash($request->getParam('password'),PASSWORD_DEFAULT)
            );
            $result = $pre->execute($values);
            return $response->withJson(array('status' => 'User Created'),200);
            
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }    



    public function Users($request, $response) 
    {
        try{
            $con = $this->db;
            $sql = "SELECT * FROM users";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result),200);
            }else{
                return $response->withJson(array('status' => 'Users Not Found'),422);
            }
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }
    
};    