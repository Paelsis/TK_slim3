<?php

namespace App\Controllers;

class TkTextController extends Controller
{
    public function test($request, $response) 
    {
        return 'TkTextController test';
    }

    // Get single text with SQL-statement
    protected function _getSingleTextSql($groupId, $textId, $language)
    {
        $sql="SELECT textBody from tbl_text where `groupId` = '$groupId' and `textId`='$textId' and `language`='$language'";
        return $sql;
    }

    protected function _getSingleText($groupId, $textId, $language)
    {
        $this->logger->info('groupId:' . $groupId . ' textId:' . $textId . ' languate:' . $language);
        $sql=$this->_getSingleTextSql($groupId, $textId, $language);
        $this->logger->info('sql:' . $sql);

        // $sql1 = "SELECT textBody from tbl_text where groupId = 'Order' and textId='SwishOpen' and language='SV'";
        $rows = $this->_selectRows($sql);
        
        $this->logger->info('count(rows):' . count($rows));

        if ((count($rows) === 0) || ($rows === null)) {
            return(null);
        } else {   
            return($rows[0]['textBody']);
        }    
    }

    // Get single text out of table tbl_text where groupId, textId and language is specified (should reply with a string).
    public function getSingleText($request, $response)
    {
        $allGetVars = $request->getQueryParams();

        if (isset($allGetVars['groupId']) && isset($allGetVars['textId']) && isset($allGetVars['language'])) {
            $groupId = $allGetVars['groupId'];
            $textId = $allGetVars['textId'];
            $language = $allGetVars['language'];
            $text = $this->_getSingleText($groupId, $textId, $language);
            if($text !== null) {
                return $response->withJson(array('status' => 'true','result'=>$text) ,200);
            } else{
                return $response->withJson(array('status' => 'No text found for (groupId, textId, language) = (' . 
                    $groupId . ', ' . $textId . ', ' . $language . ')'));
            }
        } else {
            return $response->withJson(array('status' => 'Missing groupId, textId or language in function getSingleText'),422);
        }
    }


    public function text($request, $response) 
    {
        try{
            $con = $this->container->db;
            $sql = "SELECT id, groupId, textId, textBody, DATE_FORMAT(updateTime, '%d %M %Y %H:%i') AS updateTime FROM tbl_text order by groupId asc, id desc";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            }else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
                   
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
   
    }

    public function textId($request, $response) 
    {
        $id = $request->getAttribute('id');
        try{
            $con = $this->container->db;
            $sql = "SELECT id, groupId, textId, textBody, DATE_FORMAT(updateTime, '%d %M %Y %H:%i') AS updateTime FROM tbl_text where groupId=$id order by groupId asc, id desc";
            $result = array();
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if (count($result)>0) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else {
                return $response->withJson(array('status' => 'No entries found in table ' . $table),422);
            }
                   
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }

    public function getMenu($request, $response) 
    {
        try{
            $tableName = 'tbl_menu';
            $rows = $this->_fetchall($tableName);
            if (count($rows) > 0) {
                return $response->withJson(array('status' => 'true','result'=>$rows) ,200);
            } else {
                return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database'),422);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }
    }


    public function getTexts($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['tableName'])) {
            $tableName=$queryParams['tableName'];
        } else {
            $tableName='tbl_text';
        }    

        try{
            $rows = $this->_fetchall($tableName);
            if (count($rows) > 0) {
                return $response->withJson(array('status' => 'true','result'=>$rows) ,200);
            } else {
                return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database'),422);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }
    }


};    


