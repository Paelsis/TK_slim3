<?php

namespace App\Controllers;

class TkTableController extends Controller
{
    protected $crud_error_count;

    public function test($request, $response) 
    {
        return 'TkTable Controller';
    }
    public function readFile($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $fileName = $allGetVars['fileName'];

        $inputDir = __DIR__ . '/../input/';
        $result=$this->_readJsonFile($inputDir, $fileName); 
        
        if ($result !== null) {
            return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message' =>'Failed to read data from file ' . $fileName),
                 422);
        }
    }

    public function index($request, $response) 
    {
        try{
            $allGetVars = $request->getQueryParams();
            $tableName = 'unset';
            $tableName = $allGetVars['tableName'];
            if (!isset($tableName)) {
                $this->logger->addInfo("The variable tableName was not set");
                return $response->withJson(array('status' => 'ERROR', 'message'=>'The table-name was not set in the query'),400);
            }
            $result = $this->_fetchall($tableName);
            if (count($result) > 0) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else {
                return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database'),200);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }
    }


    public function withoutId($request, $response) 
    {
        try{
            $allGetVars = $request->getQueryParams();
            $tableName = $allGetVars['tableName'];
            $con = $this->container['db'];
            $sql = "SELECT * FROM $tableName";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if ($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else {
                return $response->withJson(array('status' => 'No entries found in database'),200);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),500);
        }
    }

    public function insertTest($request, $response) 
    {
        // $this->logger defined in Controller 
        $input = $request->getParsedBody();

        $this->logger->addInfo("TkTableController testInsert");
        $this->log_input($input);

        return $response->withJson(array(
                                    'status' => 'Pers status response',
                                    'statusMessage' => 'Tango is beautiful',
                                   ),200);
    }

    public function insert($request, $response, $args) 
    {
        // $this->logger defined in Controller 
        $input = $request->getParsedBody();
        $table=$input['table'];
        $name=$table['name'];
        $data=$table['data'];

        // Logging information 
        $this->logger->addInfo("TkTableController insert");
        $this->log_input($input);

        // Create sql-statement

        $duplet = $this->_build_sql_insert_duplet($name, $data);

        // Make database call with $sql statement
        $con = $this->db;
        $sth = $con->prepare($duplet['sql']);
        $sth->execute($duplet['values']);

        // Return response with id as first item in object
        $firstItem = array('id' => $con->lastInsertId());
        $data = $firstItem + $data;
        return $response->withJson($data);
    }

    public function replaceRow($request, $response) 
    {
        $parsedBody = $request->getParsedBody();

        if (($str = $this->_checkMandatoryInput($parsedBody, ['table', 'data'])) !== null) {
            return $response->withJson(array('status'=>'WARNING', 'message' => $str . 'in table ' . $table , 'result'=>null), 200);
        }    

        $table = $parsedBody['table'];
        $row = $parsedBody['data'];

        
        if (($id=$this->_replaceRow($table, $row))!==false) {
            $this->logger->addDebug("replaceRow: Table $table replaced row successfully");
            $list = $this->_fetchall($table);
            return $response->withJson(array('status' => 'OK', 'message'=> 'successful update', 'id'=>$id, 'record'=>$row, 'list'=>$list) ,200);
        } else{
            $this->logger->error("replaceRow: Table $table failed to update");
            return $response->withJson(array('status' => 'ERROR', 'message'=> 'FAILED to insert/update rows in table ' . $table , 'result'=>$row),200);
        }
    }

    
    public function deleteRow($request, $response) 
    {
        $parsedBody = $request->getParsedBody();
        $table=$parsedBody['table'];
        if (($str = $this->_checkMandatoryInput($parsedBody, ['table', 'id'])) !== null) {
            return $response->withJson(array('status'=>'WARNING', 'message' => $str . 'in table ' . $table , 'result'=>null), 200);
        }    
        $id=$parsedBody['id'];
        $con = $this->db;
        $sth = $con->prepare("DELETE FROM $table WHERE id=:id");
        $sth->bindParam(":id", $id);
        if ($this->_sthExecute($sth)) {
            $list = $this->_fetchall($table);
            $this->logger->addDebug($id?'Id:' . $id . ' deleted':'WARNING:id not found !!!');
            $message = 'Id' . $id . ' is deleted from tableName ' . $table;
            return $response->withJson(array('status' => 'OK', 'message' => $message, 'list'=> $list, ),200);
        } else {
            return $response->withJson(array('status' => 'WARNING', 'message'=>'Failed to delete', 'list'=>array()), 204);
        }
    }

    public function addRow($request, $response) 
    {
        $input = $request->getParsedBody();
        $tableName=$input['tableName']?$input['tableName']:$input['table'];
        $id=$input['record'];
        $con = $this->db;
        $sth = $con->prepare("Insert into DELETE FROM $tableName WHERE id=:id");
        $sth->bindParam(":record", $record);
        if ($this->_sthExecute($sth)) {
            $list = $this->_fetchall($tableName);
            $this->logger->addDebug($id?'Id:' . $id . ' deleted':'WARNING:id not found !!!');
            $message = 'Id' . $id . ' is deleted from tableName ' . $tableName;
            return $response->withJson(array('status' => 'OK', 'message' => $message, 'list'=> $list),200);
        } else {
            return $response->withJson(array('status' => 'WARNING', 'message'=>'Failed to delete', 'result'=>array()), 204);
        }
    }


    protected function str_object($item) {
        
        $str='{';
        if (count($item) > 0) {    
            foreach($item as $key=>$val) {
                $str = $str . $key . ':' . $val;
                $keys=array_keys($item);
                if (end($keys) != $key) {
                    $str = $str . ', ';
                }
            }
        }
        $str = $str . '}';
        return $str;
    }    

    protected function str_array($arr) 
    {
        $str='[';
        if (count($arr) > 0) {
            $idx=0;
            foreach($arr as $item) {
                $str=$str . $this->str_object($item);
                if ($idx != count($arr)-1) {
                    $str = $str . ', ';
                }
                $idx++;
            }
        }
        $str = $str . ']';
        return $str;
    }

    protected function log_array($text, $arr) {
        $this->logger->addDebug($text . ':' . $this->str_array($arr));
    }
    

    protected function _crud_execute($sql, $values) 
    {
        $strValues = json_encode($values);
        $this->logger->addDebug("Tries to execute crud sql=$sql values=$strValues");
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute($values);
            return true;
        } catch (Exception $e) {
            $this->logger->error("ERROR: Failed to execute crud for sql:$sql values:$strValues");
            $this->logger->error('ERROR: Message:' . $e->getMessage());
            return false;
        }
    }    

    protected function _crud_update($table, $updated) 
    {
        if (count($updated) > 0) {
            
            $strUpdated = json_encode($updated);
            $this->logger->addDebug("_crud_update sql:$sql values:$strUpdated");

            foreach($updated as $item) {
                $values=array();
                $cols = array();
                $id=(int) $item['id'];
                foreach($item as $key=>$val) {
                    // Dont update keys __ORIGINAL or id
                    if ((strpos($key, '__ORIGINAL') === false) && ($key!=="id")) {
                        $cols[] = "$key = ?";
                        $values[] = $val;
                    }    
                }   
                // id should be last field in $values
                $values[] = (int) $id;
                $strValues=json_decode($values);

                if (count($cols) > 0) {
                    $sql="UPDATE $table SET " . implode(', ', $cols) . ' WHERE id = ?';
                    $this->_crud_execute($sql, $values);
                } else {
                    $this->logger->warning("_crud_update: Noting to update for table $table values:$strValues");
                }

            }
        }
    }

    protected function _crud_delete_by_id($table,  $deleted) 
    {
        if (count($deleted) > 0) {
            foreach ($deleted as $item) {
                $id=$item['id'];
                if (strpos($id,'A') === false) {
                    $sql="DELETE FROM `$table` WHERE `id`=?";
                    $values=[$id];
                    $this->_crud_execute($sql, $values);
                }
            }
            $this->logger->addDebug('Number of deleted:' . count($deleted));
        }
    }

    protected function _crud_delete_by_value($table, $deleteByValue) 
    {
        if (count($deleteByValue) > 0) {
            foreach ($deleteByValue as $row) {
                foreach ($row as $name=>$value) {
                    $sql="DELETE FROM `$table` WHERE `$name`=?";
                    $values=[$value];
                    $this->_crud_execute($sql, $values);
                }    
            }
        }
    }

    protected function _crud_truncate($table) 
    {
        $con = $this->db;
        $this->logger->addDebug('Truncate table:' . $table);
        $sql="TRUNCATE TABLE $table";
        $this->_crud_execute($sql, null);
    }



    protected function _crud_insert($table, $inserted) 
    {
        $this->logger->addDebug('Number of candiates for insert:'. count($inserted));
        $this->logger->addDebug('crud_insert before');
        if (count($inserted) > 0) {
            $con = $this->db;
            foreach($inserted as $item) {
                if (isset($item['id'])) {
                    unset($item['id']);
                }    
                $duplet = $this->_build_sql_insert_duplet($table, $item);
                if ($this->_sqlInsert($duplet['sql'], $duplet['values'])) {
                    $itemString = json_encode($item);
                    $this->logger->info('item = ' . $itemString);
                } else {    
                    $this->logger->error('_sqlInsert failed');
                    exit;
                }    
            }
        }
        $this->logger->addDebug('_crud_insert successful');
    }

    // Crud functionality
    public function crud($request, $response, $args) 
    {
        $payload = $request->getParsedBody();
        $table=$payload['table'];
        $crud=$payload['crud'];
        $crudString = json_encode($crud);

        $this->crud_error_count=0;


        $this->logger->addDebug('In function crud, table:' . $table . ' crud:' . $crudString);



        if (isset($crud['truncate'])) {
            $this->_crud_truncate($table);
        }    
        if (isset($crud['deleteByValue'])) {
            $this->_crud_delete_by_value($table, $crud['deleteByValue']);
        }    
        if (isset($crud['inserted'])) {
            $this->log_array('inserted', $crud['inserted']);
            $this->_crud_insert($table, $crud['inserted']);
        }    
        if (isset($crud['updated'])) {
            $this->log_array('updated', $crud['updated']);
            $this->_crud_update($table, $crud['updated']);
        }    
        if (isset($crud['deleted'])) {
            $this->log_array('deleted', $crud['deleted']);
            $this->_crud_delete_by_id($table, $crud['deleted']);
        }    
        $this->logger->addDebug('In function crud, before fetchall');

        $list = $this->_fetchall($table);

        $strList = json_encode($list);
        $this->logger->addDebug("List:$strList");

        if (count($list) > 0) {
            return $response->withJson(array('status' => 'OK','result'=>$list), 200);
        } else if (isset($crud['deleteByValue']) || isset($crud['deleted']) || isset($crud['truncate']) && !isset($crud['inserted'])) {
            return $response->withJson(array('status' => 'EMPTY', 'result'=>$list), 200);
        } else {
            $list = array();
            return $response->withJson(array('status' => 'WARNING', 'result'=>$list, 'message'=>'No entries found in database'), 204);
        }
    }

    protected function _getTableSql($tableName) 
    {
        return "select * from `$tableName` order by id";
    }    

    protected function _getTable($sql, $language) 
    {
        try{
            $rows=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                $rowLanguage = isset($row['language'])?$row['language']:null;
                if ($rowLanguage === null || $rowLanguage === $language) {
                    $rows[] = $row;
                }    
            }
            return $rows;
        }
        catch(\Exception $ex){
            return null;
        }
    }    

    public function getTable($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $tableName = isset($allGetVars['tableName'])?$allGetVars['tableName']:null;
        $language = isset($allGetVars['language'])?$allGetVars['language']:'SV';

        $this->_setLcTimeNames($language);

        $rows = array();
        if ($tableName !==null) {
            $this->logger->info('getTable');
            $sql=$this->_getTableSQL($tableName);
            $rows = $this->_getTable($sql, $language); 
        }    

        if(($cnt = count($rows)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$rows, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    public function getColumns($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $tableName = isset($allGetVars['tableName'])?$allGetVars['tableName']:null;

        $list = $this->_fetch_columns($tableName);

        if(($cnt = count($list)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$list, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    public function updateRow($request, $response) 
    {
        $input = $request->getParsedBody();

        if (($str = $this->_checkMandatoryInput($input, ['id', 'table', 'data']))!= null) {
            return $response->withJson(array('status'=>'WARNING', 'message' => $str, 'result'=>null),200);
        }

        $id=$input['id'];
        $table=$input['table'];
        $data=$input['data'];
        $this->logger->addDebug('Updating id:' . $id);

        if ($this->_updateRow($table, $data, $id)) {
            $strData=json_encode($data);
            $this->logger->addDebug("Table $table updated successfully for id:$id data:$strData");
            return $response->withJson(array('status'=>'OK', 'message' => 'Successful', 'id'=>$id, 'result'=>$data) ,200);
        } else{
            $this->logger->addDebug('Table $table failed for id:' . $id);
            $this->logger->addDebug('id:' . $id);
            return $response->withJson(array('status'=>'ERROR','message' => 'FAILED to update id='. $id . 'in table ' . $table , 'id'=>-1, 'result'=>null),200);
        }
    }

};





