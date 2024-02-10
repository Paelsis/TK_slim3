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
        $sql = $this->_build_sql_insert($name, $data);
        $this->logger->addDebug($sql?'SQL-insert statement:' . $sql:'WARNING:SQL-insert statement not found !!!');
        
        // Make database call with $sql statement
        $con = $this->db;
        $sth = $con->prepare($sql);
        $sth->execute();

        // Return response with id as first item in object
        $firstItem = array('id' => $con->lastInsertId());
        $data = $firstItem + $data;
        return $response->withJson($data);
    }

    public function updateTest($request, $response, $args) 
    {
        // $this->logger defined in Controller 
        $id=$args['id'];
        $input = $request->getParsedBody();
        $table=$input['table'];
        $name=$table['name'];
        $data=$table['data'];

        // Logging information 
        $this->logger->addInfo("TkTableController update");
        $this->logger->addDebug($id?'Id:' . $id:'WARNING:key hittades inte !!!');
        $this->log_input($input);
   
        // Build SQL-statement from object
        $sql = $this->_build_sql_update_id($name, $data);
        $this->logger->addDebug($sql?'SQL statment:' . $sql:'WARNING:SQL statement not built !!!');
      
        $data['id'] = $args['id'];

        return $this->response->withJson($input);
    }

    public function deleteRecord($request, $response) 
    {
        $input = $request->getParsedBody();
        $table=$input['table'];
        $id=$input['id'];
        $con = $this->db;
        $sth = $con->prepare("DELETE FROM $table WHERE id=:id");
        $sth->bindParam(":id", $id);
        if ($this->_sthExecute($sth)) {
            $this->logger->addDebug($id?'Id:' . $id . ' deleted':'WARNING:id not found !!!');
            $message = 'Id' . $id . ' is deleted from table ' . $table;
            return $response->withJson(array('status' => 'OK', 'message' => $message),200);
        } else {
            return $response->withJson(array('status' => 'WARNING', 'message'=>'Failed to delete', 'result'=>array()), 204);
        }
    }

    public function update($request, $response, $args) 
    {
        // $this->logger defined in Controller 
        $id=$args['id'];
        $input = $request->getParsedBody();
        $table=$input['table'];
        $name=$table['name'];
        $data=$table['data'];

        // Logging information 
        $this->logger->addInfo("TkTableController update");
        $this->logger->addDebug($id?'Id:' . $id:'WARNING:key hittades inte !!!');
        $this->log_input($input);
   
        // Build SQL-statement from object
        $sql = $this->_build_sql_update_id($table, $data);
        $this->logger->addDebug($sql?'SQL statment:' . $sql:'WARNING:SQL statement not built !!!');
        
        // Make database call
        $con = $this->db;
        $sth = $con->prepare($sql);
        $sth->bindParam("name", $name);
        $sth->bindParam("id", $id);
        $sth->execute();
        $data['id'] = $args['id'];
        if (strcmp($table, 'tbl_products')==0) {
            $xxx= new TkShopController;
            $ret=$xxx::renameImage($product); 
        }
        // return $this->response->withJson($input);
        return $response->withJson(array(
            'status' => 'Pers status response id:' . $id,
            'statusMessage' => 'Tango is beautiful',
        ),200);
        
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
    

    protected function _crud_execute($sql, $sth) 
    {
        try {
            $sth->execute();
            $this->logger->addDebug('OK: Execution worked SQL:' . $sql);
            return true;
        } catch (Exception $e) {
            $this->logger->error('ERROR: Failed to crud execute for sql statement:' . $sql);
            $this->logger->error('ERROR: Message:' . $e->getMessage());
            return false;
        }
    }    


    protected function _crud_update_sql($table, $item) {
        $cols_counter = 0;
        $cols = array();
        foreach($item as $key=>$val) {
            // If value contains __ORIGINAL or if it is an id, then do not updtate column
            if (strpos($key, '__ORIGINAL') === false && $key !== 'id') {
                // $val=htmlspecialchars($val, ENT_QUOTES, '', double_encode); // Escape single quote
                $escVal = addslashes($val);
                $cols[] = "$key = '$escVal'";
                $cols_counter++;
            }    
        }   
        
        if ($cols_counter > 0) {
            //$sql = "UPDATE :table SET " . implode(', ', $cols) . " WHERE id=:id";
            $sql="UPDATE $table SET " . implode(', ', $cols) . ' WHERE id=' . $item['id'];
            $this->logger->addDebug("UPDATE $table SET " . implode(', ', $cols) . ' WHERE id=' . "'" . $item['id'] . "'");
        } else {
            $sql='';
        }
        return $sql;
    }

    protected function _crud_update($table, $updated) 
    {
        if (count($updated) > 0) {
            $con = $this->db;
            
            foreach($updated as $item) {
                $sql=$this->_crud_update_sql($table, $item);
                if (strlen($sql) > 0) {
                    $this->logger->addDebug('(crud_update)' . $sql);
                    $id=$item['id'];
                    $sth = $con->prepare($sql);
                    //$sth->bindValue(':id', $id, PDO::PARAM_STR);
                    //$sth->bindValue(':table', $table, PDO::PARAM_STR);
                    $this->_crud_execute($sql, $sth);
                    $this->logger->addDebug('(Update ' . $table . ' where id=' . $id . ') ' . $sql);
                }
            }
        }
    }

    protected function _crud_delete($table,  $deleted) 
    {
        if (count($deleted) > 0) {
            $this->logger->addDebug('--------- crud_delete----------');
            $this->logger->addDebug('Number of deleted:' . count($deleted));
            $sql = "DELETE FROM :table WHERE id=:id";
            $con = $this->db;
            $this->logger->addDebug('SQL-statement:' . $sql);
            foreach ($deleted as $item) {
                $id=$item['id'];
                if (strpos($id,'A') === false) {
                    $sql='DELETE FROM ' . $table . ' WHERE id=' . $id;
                    $sth = $con->prepare($sql);
                    //$sth->bindValue(':id', $id, PDO::PARAM_STR);
                    //$sth->bindValue(':table', $table, PDO::PARAM_STR);
                    $this->logger->addDebug('SQL-statement:' . $sql);
                    $this->_crud_execute($sql, $sth);
                }
            }
        }
    }

    protected function _crud_deleteByValue($table,  $deleteByValue) 
    {
        if (count($deleteByValue) > 0) {
            $this->logger->addDebug('Number of deleted:' . count($deleteByValue));
            foreach ($deleteByValue as $row) {
                foreach ($row as $name=>$value) {
                }    
            }
        }        
        // $sql = "DELETE FROM :table WHERE id=:id";
        $con = $this->db;
        if (count($deleteByValue) > 0) {
            $this->logger->addDebug('--------- _crud_deleteByValue-----------');
            foreach ($deleteByValue as $row) {
                foreach ($row as $name=>$value) {
                    $this->logger->addDebug('Delete name:' . $name . ' value:' . $value);
                    $sql="DELETE FROM " . $table . " WHERE `" . $name . "` = '" . $value . "'";
                    $this->logger->addDebug('SQL-statement:' . $sql);
                    $sth = $con->prepare($sql);
                    $this->_crud_execute($sql, $sth);
                }    
            }
        }
    }

    protected function _crud_truncate($table) 
    {
        $con = $this->db;
        $this->logger->addDebug('--------- _crud_truncate-----------');
        $this->logger->addDebug('Truncate table:' . $table);
        $sql="TRUNCATE TABLE $table";
        $this->logger->addDebug('SQL-statement:' . $sql);
        $sth = $con->prepare($sql);
        $this->_crud_execute($sql, $sth);
    }



    protected function _crud_insert($table, $inserted) 
    {
        $this->logger->addDebug('Number of candiates for insert:'. count($inserted));
        $this->logger->addDebug('crud_insert before');
        if (count($inserted) > 0) {
            $con = $this->db;
            foreach($inserted as $item) {
                unset($item['id']);
                $sql = $this->_build_sql_insert($table, $item);
                if (!$this->_sqlInsert($sql)) {
                    $this->logger->error('_crud_execute failed');
                    exit;
                }    
            }
        }
        $this->logger->addDebug('_crud_execute successful');
    }

    // Crud functionality
    public function crud($request, $response, $args) 
    {
        $payload = $request->getParsedBody();
        $table=$payload['table'];
        $crud=$payload['crud'];

        $this->crud_error_count=0;

        $this->logger->addDebug('In function crud, table:' . $table);

        if (isset($crud['truncate'])) {
            $this->_crud_truncate($table);
        }    
        if (isset($crud['deleteByValue'])) {
            $this->_crud_deleteByValue($table, $crud['deleteByValue']);
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
            $this->_crud_delete($table, $crud['deleted']);
        }    
        $this->logger->addDebug('In function crud, before fetchall');
        
        $list = $this->_fetchall($table);

        $this->logger->addDebug('In function crud, after fetchall: Number of records fetched:' . count($list));

        if ($list) {
            return $response->withJson(array('status' => 'OK','result'=>$list), 200);
        } else if (isset($crud['deleteByValue']) || isset($crud['deleted']) || isset($crud['truncate']) && 
            !isset($crud['inserted'])) {
            return $response->withJson(array('status' => 'EMPTY','result'=>$list), 200);
        } else {
            return $response->withJson(array('status' => 'WARNING', 'message'=>'No entries found in database', 'result'=>array()), 404);
        }
    }

};

