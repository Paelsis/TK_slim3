<?php

namespace App\Controllers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const IMAGE_HANDLERS = [
    IMAGETYPE_JPEG => [
        'load' => 'imagecreatefromjpeg',
        'save' => 'imagejpeg',
        'quality' => 100
    ],
    IMAGETYPE_PNG => [
        'load' => 'imagecreatefrompng',
        'save' => 'imagepng',
        'quality' => 0
    ],
    IMAGETYPE_GIF => [
        'load' => 'imagecreatefromgif',
        'save' => 'imagegif'
    ]
];

const WEEKDAY = [
    1=>['SV'=>'Måndag', 'EN'=>'Monday', 'ES'=>'Lunes'], 
    2=>['SV'=>'Tisdag', 'EN'=>'Tuesday', 'ES'=>'Martes'], 
    3=>['SV'=>'Onsdag', 'EN'=>'Wednesday', 'ES'=>'Miércoles'], 
    4=>['SV'=>'Torsdag', 'EN'=>'Thursday', 'ES'=>'Jueves'], 
    5=>['SV'=>'Fredag', 'EN'=>'Friday', 'ES'=>'Viernes'], 
    6=>['SV'=>'Lördag', 'EN'=>'Saturday', 'ES'=>'Sábado'], 
    7=>['SV'=>'Söndag', 'EN'=>'Sunday', 'ES'=>'Domingo'], 
];

const SLIM_PUBLIC_IMAGES_PATH='../public/images';

const LC_TIME_NAMES = [
    'SV'=>'sv_SE',
    'EN'=>'en_US',
    'ES'=>'es_AR',
];


class Controller 
{
    protected $container;
    protected $logger;
    protected $db;



    public function __construct($container)
    {
        $this->container = $container;
        $this->logger = $container['logger'];
        $this->db = $container['db'];
        //echo 'In container';
    }
    
    protected function log_var($name, $var) 
    {
        ob_start(); //Start output buffer
        print_r($var);//Grab output
        $output = ob_get_contents();
        $this->logger->addDebug($name . ':' . $output);
        ob_end_clean(); //Discard output buffer
    }
    
    protected function _getFileSize($path) {
        clearstatcache();
        return filesize($path);
    }

    protected function _checkMandatoryInput($arr, $mandatory) 
    {
        $missing = "";
        foreach ($mandatory as $key) {
            if (!isset($arr[$key])) {
                if (strlen($missing) > 0) {
                    $missing = $missing . ', ';
                }
                $missing = $missing . ' ' . $key;
            }
        }   
        if (strlen($missing) > 0) {
            $message = "Required value/s [$missing] is/are missing in input array";
            $this->logger->info($message);
            return ($message);
        } else {    
            return(null);
        }    
    } 

    // When both arrays contains a key, then it is accepted
    protected function _intersectionOfKeys($arr, $keys) 
    {
        $intersection = array();
        foreach ($keys as $key) {
            if (isset($arr[$key])) {
                // Make all email to lower case
                if (filter_var($arr[$key], FILTER_VALIDATE_EMAIL)) {
                    $intersection[$key]=strtolower($arr[$key]);
                } else {
                    $intersection[$key]=$arr[$key];
                }
            } else {
                $message = "Expected key=" . $key . " not found in input array and therefore removed from intersection keys";
                $this->logger->info($message);
            }
        }   
        return($intersection);
    } 


    // Common functions start here
    protected function _sthExecute($sth) 
    {
        try {
            $sth->execute();
            $this->logger->addDebug('OK: Successful execution of sth-statement');
            return true;
        } catch (PDOException $e) {
            // g failure is never OK case since productIdalways is deleted first
            $this->logger->error('ERROR: sql-statement=' . $sql . " message=" . $e->getMessage());
            return(false);
        } catch(\Exception $e) {
            $this->logger->error('ERROR: Failed to execute stq statement' . $e->message);
            return false;
        }
    }   

    protected function _sqlExecute($sql) 
    {
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute();
            $this->logger->addDebug('OK: Successful execution of SQL-statement:' . $sql);
            return true;
        } catch (PDOException $e) {
            // Insert failure is never OK case since productIdalways is deleted first
            $this->logger->error('ERROR: sql-statement=' . $sql . " message=" . $e->getMessage());
            exit;
        } catch(\Exception $e) {
            $this->logger->error('ERROR: Failed to execute stq statement, message:' . $e->message);
            exit;
        }
    }    

    protected function _sqlInsert($sql) 
    {
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute();
            $this->logger->addDebug('OK: Successful insert in _sqlInsert:' . $sql);
            return true;
        } catch (PDOException $e) {
            $this->logger->error('ERROR: PDO exception in _sqlInsertduplicate insert of same entry =' . $sql . " message=" . $e->getMessage());
            if (isset($e->errorInfo)) {
                if ($e->errorInfo[1] == 1062) {
                    $this->logger->error('ERROR: duplicate insert of same entry =' . $sql . " message=" . $e->getMessage());
                    return false;
                } else {     
                    $this->logger->error('ERROR: other indrty error than dupicate for' . $sql . " message=" . $e->getMessage());
                    return false;
                }    
            } else {
                $this->logger->error('ERROR: SQL insert failed but errInfo not defined =' . $sql . " message=" . $e->getMessage());
                return false; 
            }
        }
    }    

    protected function _setLcTimeNames($language) 
    {
        $sql ='SET @@lc_time_names =' . LC_TIME_NAMES[$language]; 
        $this->logger->addDebug('OK: _setLcTimeNames:' . $sql);
        $this->_sqlExecute($sql);
    }

    
    // Reduce object to only contain the fields given by names of array $keys
    protected function _objectReduceToKeys($obj, $keys)
    {
        $reduced_obj=array();
        foreach($keys as $f) {
            if (array_key_exists($f, $obj)) {
                $reduced_obj[$f] = $obj[$f];
            } else {    
                $this->logger->warning('WARNING: Key ' . $f . ' is missing and ignored when reducing object)');
            } 
        }    
        return $reduced_obj;
    }

    // Reduce the objects of an array $arr to only include the fields given by names of array $keys     
    protected function _arrayReduceToKeys($arr, $keys) 
    {
        $reducedArr=array();
        foreach($arr as $p) {
            $rp = $this->_objectReduceToKeys($p, $keys);
            array_push($reducedArr, $rp);
        } 
        return $reducedArr; 
    }

    protected function _weekday($dayOfWeek, $language) 
    {
        return WEEKDAY[$dayOfWeek][$language];
    }

    protected function _updateTable($table, $data, $id) 
    {
        $this->logger->addDebug('id:' . $id);
        
        // Build SQL-statement from object
        $tab='tbl_registration';
        $sql = $this->_build_sql_update_id($table, $data);
        $this->logger->addDebug($sql?'SQL statment:' . $sql:'WARNING:SQL statement not built !!!');

        // Make database call and bind id to SQL-statement
        $con = $this->db;
        $sth = $con->prepare($sql);
        $sth->bindParam(':id', $id);
        if ($this->_sthExecute($sth)) {
            return true;
        } else{
            return false;
        }
    }

    protected function _updateTableAll($table, $data) 
    {
        // Build SQL-statement from object
        forEach($data as $key => $val) {
            $id = $val['id'];
            unset($val['id']); 
            $sql = $this->_build_sql_update_id($table, $val);
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->bindParam(':id', $id);
            if (!$this->_sthExecute($sth)) {
                $this->logger->addDebug($sql?'SQL statment:' . $sql:'WARNING:SQL statement not built !!!');
                $this->logger->addDebug('Failed to update table ' . $table . ' for id:' . $id);
                return false;
            }
        }
        return true;
    }


    protected function _serializeArray($arr) 
    {
        $str='[';
        $first=true;
        foreach($arr as $key => $val) {
            if (!$first) {
                $str = $str . ', ';
            }          
            $str = $str .  '"' . $key . '"=>';
            if (is_array($arr[$key])) {
                $str = $str . $this->_serializeArray($arr[$key]);
            } else {
                $str = $str . $val;
            }
            $first=false;
        }
        $str = $str . ']';
        return($str);
    }  
    
    protected function _logObject($obj) 
    {
        $str = $this->_serializeArray($obj);
        $this->logger->info($str);
    }    


    protected function _logList($list) {
        $cnt=0;
        foreach($list as $obj) {
            $this->logger->addDebug('Object:' . $cnt++);
            $this->_logObject($obj);
        }   
    }

    protected function _build_sql_insert($table, $data) 
    {
        if (isset($data['updTimestamp'])) {
            unset($data['updTimestamp']);
        }    
        if (isset($data['creaTimestamp'])) {
            unset($data['creaTimestamp']);
        }   
        // $this->_logObject($data);
        
        $filterData = array();
        foreach ($data as $key=>$value) { 
            if (strlen($value) !== 0) {
                $filterData[$key] = $value;
            }
        }    
 
        $key = array_keys($filterData);
        $val = array_values($filterData);

        $sql = "REPLACE INTO $table (" . implode(', ', $key) . ") "
             . "VALUES ('" . implode("', '", $val) . "')";

        $this->logger->addInfo("_build_sql_insert, sql-statement:" . $sql);
          
        return($sql);
    }


    // Update if duplicate key
    protected function _build_insert_or_update_sql($table, $data, $updKeys) 
    {
        if (isset($data['updTimestamp'])) {
            unset($data['updTimestamp']);
        }    
        if (isset($data['creaTimestamp'])) {
            unset($data['creaTimestamp']);
        }   
        if (isset($data['id'])) {
            unset($data['id']);
        }   
        
        $upd = array();
        foreach($data as $key=>$val) {
            $this->logger->addInfo("_build_insert_or_update_sql, key:" . $key . ' value=' . $val);
            if (in_array($key, $updKeys)) {
                $upd[] = "`$key` = '$val'";
            }    
        }

        $key = array_keys($data);
        $val = array_values($data);
        $sql = "INSERT INTO $table (" . implode(', ', $key) . ") "
                . "VALUES ('" . implode("', '", $val) . "') "  
                . "ON DUPLICATE KEY UPDATE " . implode(', ', $upd);

        $this->logger->addInfo("_build_sql_insert, sql-statement:" . $sql);
            
        return($sql);
    }

        // Update if duplicate key
        protected function _build_insert_or_concat_sql($table, $data, $concatKeys) 
        {
            if (isset($data['updTimestamp'])) {
                unset($data['updTimestamp']);
            }    
            if (isset($data['creaTimestamp'])) {
                unset($data['creaTimestamp']);
            }   
            if (isset($data['id'])) {
                unset($data['id']);
            }   
            
            $upd = array();
            foreach($concatKeys as $key) {
                $this->logger->addInfo("_build_insert_or_concat_sql, key:" . $key);
                if (isset($data[$key])) {
                    $val=$data[$key];
                    $upd[] = "`$key` = CONCAT(`$key`,', ','$val')";
                }    
            }
    
            $key = array_keys($data);
            $val = array_values($data);
            $sql = "INSERT INTO $table (" . implode(', ', $key) . ") "
                    . "VALUES ('" . implode("', '", $val) . "') "  
                    . "ON DUPLICATE KEY UPDATE " . implode(', ', $upd);
    
            $this->logger->addInfo("_build_insert_or_concat_sql, sql-statement:" . $sql);
                
            return($sql);
        }
    
    

    // Update if duplicate key
    protected function _build_replace_into_sql($table, $data) 
    {
        if (isset($data['updTimestamp'])) {
            unset($data['updTimestamp']);
        }    
        if (isset($data['creaTimestamp'])) {
            unset($data['creaTimestamp']);
        }   
        if (isset($data['id'])) {
            unset($data['id']);
        }   

        $key = array_keys($data);
        $val = array_values($data);
        $sql = "REPLACE INTO $table (" . implode(', ', $key) . ") "
                . "VALUES ('" . implode("', '", $val) . "') ";  

        $this->logger->addInfo("_build_replace_into_sql, sql-statement:" . $sql);
            
        return($sql);
    }

        // Update if duplicate key
    protected function _build_insert_or_ignore_into_sql($table, $data) 
    {
        if (isset($data['updTimestamp'])) {
            unset($data['updTimestamp']);
        }    
        if (isset($data['creaTimestamp'])) {
            unset($data['creaTimestamp']);
        }   
        if (isset($data['id'])) {
            unset($data['id']);
        }   

        $key = array_keys($data);
        $val = array_values($data);
        $sql = "INSERT IGNORE INTO $table (" . implode(', ', $key) . ") "
                . "VALUES ('" . implode("', '", $val) . "') ";  

        $this->logger->addInfo("_insert_or_ignore, sql-statement:" . $sql);
            
        return($sql);
    }
    

    protected function _insertRowIntoTable($table, $row) 
    {
        $sql = $this->_build_sql_insert($table, $row);
        $this->logger->addDebug('SQL-statement in _insertRowIntoTable before _sqlInsert:' . $sql);
        if ($this->_sqlInsert($sql)) {
            return true;
        } else {    
            $this->logger->addDebug('_insertRowsIntoTable: Failed to insert row with command:' . $sql);
            return false;
        } 
    }

    protected function _insertRowsIntoTable($table, $rows) 
    {
        foreach($rows as $row) {
            if (!$this->_insertRowIntoTable($table, $row)) {
                return false;
            }
        }
        return true;    
    }

    protected function _insertOrConcatRowInTable($table, $row, $concatKeys) 
    {
        $sql = $this->_build_insert_or_concat_sql($table, $row, $concatKeys);
        $this->logger->addDebug('SQL-statement in _insertOrConcatRowIntoTable:' . $sql);
        if ($this->_sqlInsert($sql)) {
            return true;
        } else {    
            $this->logger->addDebug('_insertOrConcatRowInTable: Failed to insert row with command:' . $sql);
            return false;
        } 
    }

    protected function _insertOrConcatRowsInTable($table, $rows, $concatKeys) 
    {
        $updCount = 0;
        $ret = 0;
        if (count($rows)===0) {
            $this->logger->warning('_insertOrConcatRowsInTable: No rows in input to update table ' . $table);
            return false;
        }

        foreach($rows as $row) {
            if (!$this->_insertOrConcatRowInTable($table, $row, $concatKeys)) {
                $this->logger->warning('_insertOrConcatRowsInTable: Failed to update ' . $table);
                return false;
            }
        }
        return true;    
    }



    protected function _insertOrUpdateRowInTable($table, $row, $updKeys) 
    {
        $sql = $this->_build_insert_or_update_sql($table, $row, $updKeys);
        $this->logger->addDebug('SQL-statement in _insertOrUpdateRowIntoTable:' . $sql);
        if ($this->_sqlInsert($sql)) {
            return true;
        } else {    
            $this->logger->addDebug('_insertOrUpdateRowInTable: Failed to insert row with command:' . $sql);
            return false;
        } 
    }

    protected function _insertOrUpdateRowsInTable($table, $rows, $updKeys) 
    {
        $updCount = 0;
        $ret = 0;
        if (count($rows)===0) {
            $this->logger->warning('_insertOrUpdateRowsInTable: No rows in input to update table ' . $table);
            return false;
        }

        foreach($rows as $row) {
            if (!$this->_insertOrUpdateRowInTable($table, $row, $updKeys)) {
                $this->logger->warning('_insertOrUpdateRowsInTable: Failed to insert into table ' . $table);
                return false;
            }
        }
        return true;    
    }


    protected function _replaceRowInTable($table, $row) 
    {
        $sql = $this->_build_replace_into_sql($table, $row);
        $this->logger->addDebug('SQL-statement in _repaceRowInTable:' . $sql);
        if ($this->_sqlInsert($sql)) {
            return true;
        } else {    
            $this->logger->addDebug('_replaceRowInTable: Failed to replace row with command:' . $sql);
            return false;
        } 
    }


    protected function _build_html_from_object($object){
        // start table
        $html = '<table>';
        // header row
        $html .= '<thead>';
            $html .= '<tr>';
                $html .= '<th>' . 'Name' . '</th>';
                $html .= '<th>' . 'Value' . '</th>';
            $html .= '</tr>';
        $html .= '</thead>';
            $html .= '<tbody>';
            foreach($object as $key=>$value){
                $newValue = is_array($value)?implode(', ', $value ):$value;
                $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($key) . '</td>';
                    $html .= '<td>' . htmlspecialchars($newValue) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody>';
        $html .= '</table>';
        return $html;
    }    

    protected function _build_html_from_array($array){
        // start table
        $html = '';
        if (is_array($array)) {
            $html .= '<table>';
            // header row
            $html .= '<thead>';
            $html .= '<tr>';

            reset($array);
            $first_key = key($array);
            if (is_array($array[$first_key])) {
                foreach($array[$first_key] as $key=>$value) {
                        $html .= '<th>' . htmlspecialchars($key) . '</th>';
                }
            }
            $html .= '</tr>';
            $html .= '</thead>';
        
            // data rows
            $html .= '<tbody>';
            foreach($array as $key=>$value) {
                $html .= '<tr>';
                if (is_array($value)) {
                    foreach($value as $key2=>$value2 ) {
                        $html .= '<td>' . htmlspecialchars($value2) . '</td>';
                    }
                }    
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            // finish table and return it

            $html .= '</table>';
        }    
        return $html;
    }    

    protected function _build_html_from_arrays_in_object($object) {
        // start table
       $html = "";
       foreach($object as $key=>$val) {
            if (is_array($val)) {
                $html .= $this->_build_html_from_array($val);
            }    
        }    
       return $html;
    }    

    
    protected function _replaceRowsInTable($table, $rows) 
    {
        $updCount = 0;
        $ret = 0;
        if (count($rows)===0) {
            $this->logger->warning('_replaceRowsInTable: No rows in input to update');
            return false;
        }

        foreach($rows as $row) {
            if (!$this->_replaceRowInTable($table, $row)) {
                $this->_logObject($row);
                $this->logger->warning('_replaceRowsInTable: Failed to insert row into table ' . $table);
                return false;
            } 
        }
        return true;
    }

    protected function _insertOrIgnoreRowIntoTable($table, $row)  
    {
        $sql = $this->_build_insert_or_ignore_into_sql($table, $row);
        $this->logger->addDebug('SQL-statement in _insertOrIgnoreRowsIntoTable:' . $sql);
        if ($this->_sqlInsert($sql)) {
            return true;
        } else {
            $this->logger->warning('_insertOrIgnoreRowsIntoTable: Failed to update row with command:' . $sql);
            return false;
        }
    }

    protected function _insertOrIgnoreRowsIntoTable($table, $rows) 
    {
        $updCount = 0;
        $ret = 0;
        if (count($rows)===0) {
            $this->logger->warning('_insertOrIgnoreRowsIntoTable: No rows in input to update');
            return false;
        }

        foreach($rows as $row) {
            if (!$this->insertOrIgnoreRowIntoTable($table, $row)) {
                $this->logger->warning('_insertOrIgnoreRowsIntoTable: Failed to insert into table ' . $table);
                return false;
            }  
        }
        return true;    
    }


    protected function _does_column_exist($table, $column) 
    {
        $sql="SHOW COLUMNS FROM $table LIKE '$column'";
        $query = $con->query($sql);
        $fetchedArray = $query->fetch();
        $result = $fetchedArray[$columnName];
        return $result?true:false;
    }



    protected function _build_sql_update_id_old($table, $id, $data)
    {
        $cols = array();

        foreach($data as $key=>$val) {
            $cols[] = "$key = '$val'";
        }
        $sql = "UPDATE $table SET " . implode(', ', $cols) . " WHERE id='$id'";

        return($sql);
    }
    
    

    /* function to build SQL UPDATE string */
    protected function _build_sql_update($table, $data, $where)
    {
        $cols = array();
    
        foreach($data as $key=>$val) {
            $cols[] = "$key = '$val'";
        }
        $sql = "UPDATE $table SET " . implode(', ', $cols) . " WHERE $where";
    
        return($sql);
    }

    /* function to build SQL UPDATE string */
    protected function UNUSED_build_sql_update_id($data)
    {
        $cols = array();

        foreach($data as $key=>$val) {
            $cols[] = "$key = '$val'";
        }
        $sql = "UPDATE :table SET " . implode(', ', $cols) . " WHERE id=:id";

        return($sql);
    }

    /* function to build SQL UPDATE string */
    protected function _build_sql_update_id($table, $data)
    {
        $cols = array();

        foreach($data as $key=>$val) {
            if (isset($val)) {
                $cols[] = "$key = '$val'";
            } else {
                $cols[] = "$key = null";
            }    
        }
        $sql = "UPDATE $table SET " . implode(', ', $cols) . " WHERE id=:id";

        return($sql);
    }

    protected function _getSingleValue($tableName, $prop, $value, $columnName)
    {
        $con = $this->db;
        $sql="SELECT `$columnName` FROM `$tableName` WHERE $prop='" . $value . "' limit 1";
        $query = $con->query($sql);
        $fetchedArray = $query->fetch();
        $result = $fetchedArray[$columnName];
        $this->logger->addDebug('getSingleValue SQL-statement:' . $sql . ' result:' . $result);
        return $result;
    }

    protected function _getSingleRow($tableName, $prop, $value)
    {
        $con = $this->db;
        $sql="SELECT * FROM `$tableName` WHERE $prop='" . $value . "' limit 1";
        $rows = $this->_selectRows($sql);
        if (count($rows) > 0) {
            return $rows[0];
        } else {    
            $this->logger->error('Failed to find any rows for SQL-statement:' . $sql);
            return null;
        }    
    }

    protected function _getSingleValueForUpdate($tableName, $prop, $value, $columnName)
    {
        $con = $this->db;
        $query = $con->query("SELECT `$columnName` FROM `$tableName` WHERE $prop='" . $value . "' limit 1 for update");
        $fetchedArray = $query->fetch();
        $result = $fetchedArray[$columnName];
        return $result;
    }

    protected function _getRowCount()
    {
        $con = $this->db;
        $query = $con->query("SELECT ROW_COUNT() AS ROW_CNT");
        $fetchedArray = $query->fetch();
        $result = $fetchedArray['ROW_CNT'];
        return $result;
    }
    

    protected function _updateSingleValue($tableName, $prop, $value, $columnName, $columnValue)
    {
        
        // Update tbl_products with the updated sizeCount 
        $sql="UPDATE `$tableName` SET `$columnName`='" . $columnValue . "' where `$prop`='" . $value . "'";
        $this->logger->addDebug('OK: Execution worked SQL:' . $sql);
        $this->_sqlExecute($sql);
    }

    protected function _selectRows($sql) 
    {    
        try{
            $con = $this->db;
            $result=array();
            $this->logger->info('SQL:' . $sql);
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            $this->logger->info('count(result):' . count($result));
            return $result;
        }
        catch(\Exception $ex){
            $this->logger->error('Failed to execute _selectRows() for SQL-statment:' . $sql);
            return null;
        }
    }  



    protected function _groupByArr($arr, $key)
    {
        $groupByArr = array();
        foreach($arr as $it) {
            if (!isset($groupByArr[$it[$key]])) {
                $groupByArr[$it[$key]]=array();
            }    
            $groupByArr[$it[$key]][]=$it;
        }
        return($groupByArr);
    }

    protected function log_keys($data) 
    {
        // $this->logger defined in Controller 
        $keys=array_keys($data);
        foreach ($keys as $key)
        {
            $this->logger->addDebug($key?'Key:' . $key:'WARNING:key hittades inte !!!');
        }
    }

    protected function log_values($data) 
    {
        // $this->logger defined in Controller 
        $values=array_values($obj);
        $s="";
        foreach ($values as $value) {
            $s = $s . 'Value:' . $value?$value:'not found';
        }
        $this->logger->addDebug($input?'OK:input hittades':'WARNING:data hittades inte !!!');
    }

    protected function log_input($input) {
        // $this->logger defined in Controller 
        $payload=$input['payload'];
        $table=$payload['table'];
        $data=$payload['data'];
        $this->logger->addDebug($input?'OK:input hittades':'WARNING:data hittades inte !!!');
        $this->logger->addDebug($table?'Table name::' . $table:'WARNING:key hittades inte !!!');
        $col=1;
        $s='{';
        foreach($data as $key=>$val) 
        {
            $s = $s . $key . '=' . $val;
            if (end(array_keys($data)) !== $key) {
                $s = $s . ', ';
            }
        }
        $s = $s . '}';
        $this->logger->addDebug($key?'Column:' . $col . ':' . $key . '=' . $val:'WARNING:key hittades inte !!!');
    }    

    protected function _fetchall($table) {
        $con = $this->db;
        $sql = "SELECT * FROM $table order by id asc";
        $rows = array();
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        return $rows;
    }    

    protected function _fetchallSortBySequenceNumber($table) {
        $con = $this->db;
        $sql = "SELECT * FROM $table order by id asc";
        $rows = array();
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        $this->logger->addDebug(count($rows) . ' fetched from table ' . $table);
        return $rows;
    }    


    protected function _sendMailWithReplyTo($subject, $body, $recipient, $replyTo) 
    {
        // Fetch email properties for tk from config file
        $email = $this->container['settings']['tk']['email'];
        $host = $email['host'];
        $from = $email['from'];

        $this->logger->addDebug("_sendMailWithReplyTo: Step 1");

        // $replyTo = $email['replyTo']; // Array with 2 fields, email-address and alias
        $password = $email['password']; // Array with 2 properties, email-address and alias
        $this->logger->addDebug("host=" . $host);
        $this->logger->addDebug("from=[" . $from[0] . ', ' . $from[1] . ']');
        $this->logger->addDebug("replyTo)=[" . $replyTo[0] . ', ' . $replyTo[1]. ']');
        $this->logger->addDebug("recipient=" . $recipient);
        $this->logger->addDebug("subject=" . $subject);
        //$this->logger->addDebug("body=" . $body);

        $this->logger->addDebug("_sendMailWithReplyTo: Step 1");
  
        // Initialize mail
        $mail = new PHPMailer;
        $mail->CharSet = 'UTF-8';
        // $mail->SMTPDebug = 2;                              // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;     

       $this->logger->addDebug("_sendMailWithReplyTo: Step 2");

        // Enable SMTP authentication
        $mail->Username = $from[0];   // SMTP username
        $mail->Password = $password;                           // SMTP password
        //$mail->Username = $from;   // SMTP username
        //$mail->Password = $password;                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                                    // TCP port to connect to
        $mail->setFrom($from[0], $from[1]);
        $mail->addReplyTo($replyTo[0], $replyTo[1]);
        $mail->addAddress($recipient);
        $mail->isHTML(true);                                  // Set email format to HTML
        $this->logger->addDebug("_sendMailWithReplyTo: Step 3");

        $mail->Subject = $subject;
        $mail->Body = $body;
        if(!$mail->send()) {
            $this->logger->info('host:' . $mail->Host . ' username:' . $mail->Username . ' password:' . $mail->Password);
            $this->logger->info('from: [' . $from[0] . ', ' . $from[1] . ']');
            $this->logger->info('replyTo: [' . $replyTo[0] . ', ' . $replyTo[1]. ']');
            $this->logger->info("Subject:" . $subject);
            $this->logger->info("Subject:" . $body);
            $this->logger->info("ERROR: Problem sending mail with in function _sendMailWithReplyTo()");
            return(false);
            // $app->halt(500);
        } 
        $this->logger->addDebug("After mail sent");
        return(true);
    }

    protected function _sendMail($subject, $body, $recipient)
    {
        $replyTo = $this->container['settings']['tk']['email']['replyTo'];
        return $this->_sendMailWithReplyTo($subject, $body, $recipient, $replyTo);
    }    

    protected function _readJsonFile($inputDir, $fname) 
    {
        ob_start();
        $string = file_get_contents($inputDir . $fname);
        $this->logger->error('string = ' . $string);
        ob_clean();
        if ($string !== false) {
            $json_a = json_decode($string, true);
            if ($json_a !== null) {
                return($json_a);
            } else {
                $this->logger->error('ERROR: failed to make json_decode on string ' . $string);
            }    
        } else {
            $this->logger->error('ERROR: problems reading file ' . $fname . ' in directory ' . $inputDir);
            return(null);
        }
    }    

    protected function _betweenDates($dt, $startDt, $endDt) 
    {
        if ($startDt === '0000-00-00') {
            $startDt = '2000-01-01';
        }
        if ($endDt === '0000-00-00') {
            $endDt = '2100-12-31';
        }
        
        $current = date('Y-m-d', strtotime($dt));
        $begin = date('Y-m-d', strtotime($startDt));
        $end  = date('Y-m-d', strtotime($endDt));
        

        if ($current >= $begin && $current <= $end) {
            // $this->logger->addDebug('_betweenDates' . ' current:' . $current . ' in range [' . $begin . ' ,' . $end . ']');
            return(true);
        } else{
            return(false);  
        }
    }

    protected function _isImage($entry) {
        $file_parts = pathinfo($entry);
        if (!isset($file_parts['extension'])) {
            return(false);
        }
        switch(strtolower($file_parts['extension']))
        {                   
            case 'jpg':
            case 'jpeg':
            case 'png':
                return true;
            default: 
                return false;
        } 
    }

    protected function _isThumbnail($entry) {
        $file_parts = pathinfo($entry);
        if (strpos($entry, '_thumb') !== false && $this->_isImage($entry)) {
            return(true);
        } else {
            return(false);
        }
    }

    protected function _isImageButNotThumbnail($entry) {
        $file_parts = pathinfo($entry);
        if (strpos($entry, '_thumb') === false && $this->_isImage($entry)) {
            return(true);
        } else {
            return(false);
        }
    }


    protected function _fetchOrder($orderId) {
        $table='tbl_order';
        $this->logger->addDebug('_fetchOrder:' . $table);
        $con = $this->db;
        $sql = "SELECT * FROM `$table` where `orderId`='$orderId'"; 
        $this->logger->addDebug('_fetchOrder - sql:' . $sql);
        $rows = null;
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        $this->logger->addDebug('Number of fetched records:' . count($rows));
        if ($rows !== null) {
            return $rows[0];
        } else {
            return(null);
        }
    }    

    protected function _createThumbnail($src, $dest, $targetWidth, $targetHeight = null)
    {
        // 1. Load the image from the given $src
        // - see if the file actually exists
        // - check if it's of a valid image type
        // - load the image resource

        // get the type of the image
        // we need the type to determine the correct loader
        $type = exif_imagetype($src);

        // if no valid type or no handler found -> exit
        if (!$type || !IMAGE_HANDLERS[$type]) {
            return null;
        }

        // load the image with the correct loader
        $image = call_user_func(IMAGE_HANDLERS[$type]['load'], $src);

        // no image found at supplied location -> exit
        if (!$image) {
            return null;
        }


        // 2. Create a thumbnail and resize the loaded $image
        // - get the image dimensions
        // - define the output size appropriately
        // - create a thumbnail based on that size
        // - set alpha transparency for GIFs and PNGs
        // - draw the final thumbnail

        // get original image width and height
        $width = imagesx($image);
        $height = imagesy($image);

        // maintain aspect ratio when no height set
        if ($targetHeight == null) {

            // get width to height ratio
            $ratio = $width / $height;

            // if is portrait
            // use ratio to scale height to fit in square
            if ($width > $height) {
                $targetHeight = floor($targetWidth / $ratio);
            }
            // if is landscape
            // use ratio to scale width to fit in square
            else {
                $targetHeight = $targetWidth;
                $targetWidth = floor($targetWidth * $ratio);
            }
        }

        // create duplicate image based on calculated target size
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        // set transparency options for GIFs and PNGs
        if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG) {

            // make image transparent
            imagecolortransparent(
                $thumbnail,
                imagecolorallocate($thumbnail, 0, 0, 0)
            );

            // additional settings for PNGs
            if ($type == IMAGETYPE_PNG) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }
        }

        // copy entire source image to duplicate image and resize
        imagecopyresampled(
            $thumbnail,
            $image,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $width, $height
        );


        // 3. Save the $thumbnail to disk
        // - call the correct save method
        // - set the correct quality level

        // save the duplicate version of the image to disk
        return call_user_func(
            IMAGE_HANDLERS[$type]['save'],
            $thumbnail,
            $dest,
            IMAGE_HANDLERS[$type]['quality']
        );
    }

    protected function _thumbnailFname($dir, $entry) {
        $filename = pathinfo($entry, PATHINFO_FILENAME);
        $ext = pathinfo($entry, PATHINFO_EXTENSION);
        $fname_thumb = $dir . '/' . $filename . '_' . 'thumb' . '.' . $ext;
        return $fname_thumb;
    }

    protected function _createThumbnailFile($dir, $entry) {
        if ($entry != "." && $entry != ".." && $this->_isImageButNotThumbnail($entry)===true) {
            $fname_orig = $dir . '/' . $entry;
            $fname_thumb = $this->_thumbnailFname($dir, $entry);
            if (!file_exists($fname_thumb))  {
                if ($this->_createThumbnail($fname_orig, $fname_thumb, 200)) {
                    return $fname_thumb;
                } 
            } else {
                // Thumbnail already exist
                $this->logger->error('Thumb already exists for ' . $fname_thumb);
                return null;
            }
        }
    }

    protected function _createThumbnailsForDir($dir) {
        if (is_dir($dir))
        {
            $result = array();
            $allowed = array('.gif','.jpg','.jpeg','.png');
            $opendirectory = opendir($dir);

            while (($entry = readdir($opendirectory)) !== false)
            {
                if (!in_array(strtolower(strrchr($entry, '.')), $allowed)) {
                    continue;
                }
                $ret = $this->_createThumbnailFile($dir, $entry);
                if ($ret !== null)  {
                    // Records created
                    $this->logger->addDebug('Successful creation of thumbnail ' .$dir.'/'.$entry);
                    $result[] = $ret;    
                } else {
                    $this->logger->addDebug($name . ':' . $output);
                    $this->logger->error('Failed creation of thumbnail ' .$dir.'/'.$entry);
                }
            }    
            return $result;
        } else {
            // Directory not found 
            return null;
        }     

    }
    protected function _getHtmlFromTblText($groupId, $textId, $language)
    {
        $sql="select * from `tbl_text` where `groupId` = '$groupId' and `textId` = '$textId' and `language` = '$language'";
        $rows = $this->_selectRows($sql);
        if (count($rows) > 0) {
            return $rows[0]['textBody'];
        } else {    
            return "<p>Text not found in tbl tbl_text for groupId=$groupId textId=$textId language=$language</p>";
        }    
    }

    /* New 16/10-20 */
    protected function _vmailSubject($queryParams) {
        $con = $this->container['MailController'];
        return $con->mailSubject($queryParams); 
    }    

    /* New 16/10-20 */
    protected function _vmailBody($queryParams) {
        $con = $this->container['MailController'];
        return $con->mailBody($queryParams); 
    }

    protected function _mailSubject($orderId, $language, $status) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailSubject($orderId, $language, $status); 
    }    

    protected function _mailBody($orderId, $language) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailBody($orderId, $language); 
    }

    protected function _sendMailReg($reg) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->sendMailReg($reg);
    }

    protected function _insertOrder($order) {
        $controller = $this->container['TkShopController'];
        return $controller->insertOrder($order);
    }
}



