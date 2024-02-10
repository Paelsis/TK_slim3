<?php

namespace App\Controllers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const EMAIL_DANIEL='daniel@tangokompaniet.com';
const SWISH = "123 173 30 05";
const SWISH_ANNA = "070-5150345";
const PAYPAL_ANNA = "anna@tangokompaniet.com";
const BIC = "SWEDSESS";
const BANKGIRO = "5532-8223";
const IBAN = "SE59 8000 0821 4994 3833 6324";
const COMPANY_NAME = "Tangokompaniet";
const TBL_ORDER = "tbl_order";
const TBL_REGISTRATION='tbl_registration';
const TBL_REGISTRATION_MARATHON='tbl_registration_marathon';
const TBL_REGISTRATION_FESTIVAL='tbl_registration_festival';
const TBL_REGISTRATION_FESTIVAL_PRODUCT='tbl_registration_festival_product';
const TBL_PHONEBOOK='tbl_phonebook';
const SLIM_PUBLIC_IMAGES_PATH='../public/images';
const SLIM_IMAGES_PATH='../public/images';


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

const PAYMENT_TXT = [
   'YOU_SHALL_PAY' => [
        'SV'=>'Du skall betala ',
        'ES'=>'You shall pay ',
        'EN'=>'You shall pay ',
   ],    
   'HEADER' => [
         'SV'=>'När du betalar via Internet (swish/paypal/bank) ange ditt order nummer ',
         'ES'=>'When you pay via Internet (swish/paypal/bank) enter your order number ',
         'EN'=>'When you pay via Internet (swish/paypal/bank) enter your order number ',
    ],    
    'ONLY_IN_SWEDEN' => [
          'SV'=>' (Fungerar bara i Sverige)', 
          'EN'=>' (Does only work within Sweden)',
          'ES'=>' (Does only work within Sweden)',
    ],
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


const LC_TIME_NAMES = [
    'SV'=>'sv_SE',
    'EN'=>'en_US',
    'ES'=>'es_AR',
];

const TOKEN=[
    'SV'=>'Klicka på denna länk om du vill cancellera din anmälan:', 
    'EN'=>'Click on the following link to cancel your registration:',
    'ES'=>'Click on the following link to cancel your registration:',
];

const THANK_YOU=[
    'SUMMER'=>"Best regards Malmö Tango Summer crew",
    'EASTER'=>"Best regards Malmö Tango Easter crew",
    'FESTIVALITO'=>"Best regards Malmö Tango Festivalito crew",
    'MARATHON'=>"Best regards Malmö Tango Marathon crew",
    'DEFAULT'=>"Best regards Tangokompaniet", 
    'WEEKEND'=>"Best regards Tangokompaniet", 
    'INTENSE'=>"Best regards Tangokompaniet", 
    'DEFAIÖT'=>"Best regards Tangokompaniet", 
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

    public function _clickableUrl($stringUrl, $title) 
    {
        $url = '~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i'; 
        $string = preg_replace($url, "<a href='$0' target='_blank' title='$title'>$title</a>", $stringUrl);
        return $string;
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

    protected function _paymentInfo($amount, $language) {
        $reply="";
        $reply .= PAYMENT_TXT['YOU_SHALL_PAY'][$language] . $amount . ' SEK';
        $reply .=  "<p/>";
        $reply .= PAYMENT_TXT['HEADER'][$language];
        $reply .= "<ul>";
        $reply .= "<li>SWISH:" . SWISH . PAYMENT_TXT['ONLY_IN_SWEDEN'][$language] . "</li>";
        $reply .= "<li>Bankgiro:" . BANKGIRO . PAYMENT_TXT['ONLY_IN_SWEDEN'][$language] . "</li>";
        $reply .= "<li>International IBAN:" . IBAN . " BIC:" . BIC . "</li>";
        $reply .= "</ul>";
        $reply .= "<p/>";
        return $reply;
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
            $strArr = json_encode($arr);
            $message = "Required value/s [$missing] is/are missing in input array $strArr";
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


    protected function _sqlInsert($sql, $values) 
    {
        if (!isset($values)) {
            $this->logger->error("ERROR: _sqlInsert calling parameter values is unset for sql-statement:$sql");
            return false;
        }

        $strValues = json_encode($values);    
        $this->logger->info("Will try to prepare and insert sql:$sql with values:$strValues");
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute($values) || die("Failed to execute $sql"); 
            $id = $con->lastInsertId();
            $this->logger->info("_sqlInsert: Successful insert into table with id=$id");
            return true;
        } catch (PDOException $e) {
            $this->logger->error('ERROR: PDO exception in _sqlInsert duplicate insert of same entry =' . $sql . " message=" . $e->getMessage());
            return false;
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

    protected function _updateRow($table, $data, $id) 
    {
        if (!isset($id)) {
            $this->logger->error('Key id is not set in call to _updateRow');
            return false;
        }

        $this->logger->addDebug('id:' . $id);
        $cols = array();
        $values = array();
        foreach($data as $key=>$val) {
            if (isset($val)) {
                $cols[] = "`$key` = ?";
                $values[] = $val;
            }    
        }
        $values[] = (int) $id;
        $sql = "UPDATE `$table` SET " . implode(', ', $cols) . " WHERE `id`=?";

        $strData =  json_encode($data);
        $strValues =  json_encode($values);
        $this->logger->addDebug("_updateRow, data:$strData sql:$sql values:$strValues");

        // Make database call and bind id to SQL-statement
        $con = $this->db;
        $sth = $con->prepare($sql);
        $sth->execute($values) || die("Failed to execute $sql"); 
        return true;
    }

    protected function _updateList($table, $data) 
    {
        // Build SQL-statement from object
        foreach($data as $key => $val) {
            $id = $val['id'];
            unset($val['id']); 
            if (!$this->_updateRow($table, $val, $id)) {
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

    protected function _build_sql_insert_duplet($table, $data) 
    {
        unset($data['updTimestamp']);
        unset($data['creaTimestamp']);
        $keys = array();
        $values = array();
        $questionMarks = array();

        foreach ($data as $key=>$value) { 
            if (is_array($value)) {
                $this->logger->error("Insert key is an array Key=" . $key);
            } else if (isset($value) && strlen($value) !== 0) {
                $keys[] = $key;
                $values[] = $value;
                $questionMarks[] = '?';
            }
        }    

        $sql = 'REPLACE INTO ' . $table . ' (' . implode(', ', $keys) . ')'
             . ' VALUES (' . implode(', ', $questionMarks) . ')';

        $strValues= json_encode($values);        
        $this->logger->addInfo("_build_sql_insert_duplet, sql-statement:$sql values=$strValues");
          
        return array('sql'=>$sql, 'values'=>$values);
    }


    // Update if duplicate key
    protected function _build_insert_or_update_duplet($table, $data, $updKeys) 
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

        $questions1 = array();
        $questions2 = array();
        $values = array();    
        foreach($data as $val) {
            $questions1[] = '?';   
            $values[] = $val;
        }

        foreach($updKeys as $key) {
            if (isset($data[$key])) {
                $questions2[] = "`$key`=?";
                $values[] = $data[$key];
            }    
        }

        $strData = json_encode($data);
        $strUpdKeys = json_encode($updKeys);

        $strQuestions1 = json_encode($questions1);
        $strQuestions2 = json_encode($questions2);
        $strValues = json_encode($values);

        $keys = array_keys($data);
        $sql = "INSERT INTO $table (" . implode(', ', $keys) . ") "
                . "VALUES (" . implode(", ", $questions1) . ") "  
                . "ON DUPLICATE KEY UPDATE " . implode(', ', $questions2);
            
        $this->logger->addInfo("_build_insert_or_update_duplet data:$strData, updKeys:$strUpdKeys sql:$sql values:$strValues");
        return array('sql'=>$sql, 'values'=>$values);
    }
    
    protected function _escape_array($arr) {
        $newArr = array();
        $this->logger->addInfo("_escape_array: Before mysql_real_escape_string");
        forEach($arr as $value) {
            if (is_string($value)) {
                $newArr[] = $value;
            } else {
                $newArr[] = $value;   
            }
            // $newArr[] = $value;
        }   
        return $newArr;
    }

    protected function _bindFields($arr)
    {
        end($arr); 
        $lastField = key($arr);
        $bindFields = ' ';
        foreach($arr as $field => $data){ 
            $bindString .= $field . '=:' . $field; 
            $bindString .= ($field === $lastField ? ' ' : ',');
        }
        return $bindFields;
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
        $val = $this->_escape_array($val);


        $sql = "INSERT IGNORE INTO $table (" . implode(', ', $key) . ") "
                . "VALUES ('" . implode("', '", $val) . "') ";  

        $this->logger->addInfo("_insert_or_ignore, sql-statement:" . $sql);
            
        return($sql);
    }
    


    protected function _insertOrUpdateRowInTable($table, $row, $updKeys) 
    {
        $duplet = $this->_build_insert_or_update_duplet($table, $row, $updKeys);
        $sql = $duplet['sql'];
        $values = $duplet['values'];

        if ($this->_sqlInsert($sql, $values)) {
            $this->logger->info("_insertOrUpdateRowInTable: Successful update of table $table");
            return true;
        } else {    
            $this->logger->error('_insertOrUpdateRowInTable: Failed to insert row with command:' . $sql);
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
                $this->logger->error('_insertOrUpdateRowsInTable: Failed to insert into table ' . $table);
                return false;
            }
        }
        return true;    
    }



    protected function _build_html_from_object($object, $implode_array_flag){
        // start table
        $html = '<table>';
        // header row
        /*
        $html .= '<thead>';
            $html .= '<tr>';
                $html .= '<th>' . 'Name' . '</th>';
                $html .= '<th>' . 'Value' . '</th>';
            $html .= '</tr>';
        $html .= '</thead>';
        */ 
        
        $html .= '<tbody>';
            foreach($object as $key=>$value) {
                if ($implode_array_flag || !is_array($value)) {
                    $newValue = is_array($value)?implode(', ', $value ):$value;
                    $html .= '<tr>';
                        $html .= "<td style='padding-right:5px;'>" . htmlspecialchars($key) . '</td>';
                        $html .= '<td>' . htmlspecialchars($newValue) . '</td>';
                    $html .= '</tr>';
                }
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

    protected function _build_html_from_product_array($array){
        // start table
        $partnerExistsInList = false;
        foreach($array as $row) {
            if (isset($row['firstNamePartner'])) {
                $partnerExistsInList = true;
                break;
            }
        }    
        $html = '';
        if (is_array($array)) {
            $html .= '<h2>List of products</h2>';
            $html .= '<table>';
            $html .= '<thead>';
            $html .= '<th>' . 'Product' . '</th>';
            $html .= '<th>' . 'Type' . '</th>';
            if ($partnerExistsInList) {
                $html .= '<th>' . 'Partner' . '</th>';
            }    
            $html .= '</thead>';
            $html .= '<tbody>';
            foreach($array as $row) {
                $html .= '<tr>';
                $html .= isset($row['product'])?'<td>' . htmlspecialchars($row['product']) . '</td>':'<td/>';
                $html .= isset($row['productType'])?'<td>' . htmlspecialchars($row['productType']) . '</td>':'<td/>' ;
                if ($partnerExistsInList) {
                    $html .= isset($row['firstNamePartner'])?'<td>' . 'with dance partner ' . htmlspecialchars($row['firstNamePartner']) . ' ' . htmlspecialchars($row['lastNamePartner']) . '</td>':'<td/>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
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

    protected function _fetchRows($sql) 
    {    
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            /*
            $strResult = json_encode($result);
            $this->logger->info('_fetchRows, rows:' . $strResult);
            */
            return $result;
        }
        catch(\Exception $ex){
            return null;
        }
    }    


    public function _getColumns($tableName) 
    {
        try{
            //echo "tableName = $tableName";
            $con = $this->db;
            $sql = "SHOW COLUMNS FROM `$tableName`";
            $values = array();
            $columns=$this->_fetchRowsDuplet($sql); 
            if (count($columns) > 0) {
                return $columns;
            } else {
                $this->logger->warning("WARNING: No columns in table; $tableName");
                return null;
            }               
        }
        catch(\Exception $ex){
            $this->logger->error("ERROR: Failed to get columns in table; $tableName");
            return null;
        } 
    }

    public function _onlyExistingColumns($tableName, $candidates) 
    {
        try{
            $con = $this->db;
            $sql = "SHOW COLUMNS FROM `$tableName`";
            $values = array();
            $columns=$this->_fetchRows($sql); 
            $existing = array();
            $missing = array();
            foreach($candidates as $key=>$value) {
                $found = false;
                foreach($columns as $col) {
                    if ($col['Field'] === $key) {
                        $found = true;
                    }
                }    
                if ($found===true) {
                    $existing += [$key=>$value]; 
                } else {
                    $missing[] = $key;
                }
            }    
            if (count($existing) > 0) {
                return $existing;
            } else {
                $strMissing = json_encode($missing);
                $strWarning = "WARNING: No exising columnsmns is missing in table $tableName columns $strMissing";
                $this->logger->warning($strWarning);
                return null;
            }               
        } catch(\Exception $ex){
            $strError = "ERROR: Failed to get columns in table; $tableName";
            $this->logger->error($strError);
            return null;
        } 
    }


    protected function _replaceRow($tableName, $data) 
    {

        unset($data['updTimestamp']);
        unset($data['creaTimestamp']);

        // Ensure that only fields that exist in the database is passed to be inserted
        $reducedData = $this->_onlyExistingColumns($tableName, $data);

        $this->logger->addDebug("_replaceRow data:" . json_encode($data));
        $this->logger->addDebug("_replaceRow reducedData:" . json_encode($reducedData));
        

        $keys = array_keys($reducedData);
        $placeholders = array();
        foreach($reducedData as $value) {
            $placeholders[] = '?';
        }

        $values = array_values($reducedData);
        
        $sql = "REPLACE INTO $tableName (" . implode(', ', $keys) . ") "
                . 'VALUES (' . implode(', ', $placeholders) . ') ';  

        $stringValues=json_encode($values); 
        $this->logger->addDebug("_replaceRow: Before replace row in _replaceRow sql=$sql values=$stringValues");
        
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute($values); 
            $this->logger->addDebug("_replaceRow: SUCCESSFUL execution before fetch lastInsertId");
            $id = $con->lastInsertId();
            $this->logger->addDebug("_replaceRow: SUCCESSFUL execution of id $id");
            return $id;
        } catch (PDOException $e) {
            $strValues = json_encode($values); 
            $message = $e->getMessage();
            $this->logger->error("ERROR: FaiLed in _replaceRow, sql=$sql values=$strValues message=$message");
            return false;
        }
    }


    
    protected function _replaceRowsInTable($table, $rows) 
    {
        $updCount = 0;
        $ret = 0;
        if (count($rows)===0) {
            $this->logger->warning('_InTable: No rows in input to update');
            return false;
        }

        foreach($rows as $row) {
            if (!$this->_replaceRow($table, $row)) {
                return false;
            } 
        }
        return true;
    }

    protected function _insertOrIgnoreRowIntoTable($table, $data)  
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

        $keys = array_keys($data);
        $values = array_values($data);
        $placeholders = array();
        foreach($data as $value) {
            $placeholders[] = '?';
        }

        $sql = "INSERT IGNORE INTO $table (" . implode(', ', $keys) . ") "
                . "VALUES ('" . implode("', '", $placeholders) . "') ";  

        $stringValues=json_encode($values); 

        $this->logger->addDebug("Before replace row in _insertOrIgnoreRowIntoTable sql=$sql  values =$stringValues");

        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute($values) || die("Failed to execute $sql"); 
            $this->logger->info("Successful insert (_insertOrIgnoreRowIntoTable)");
            return true;
        } catch (PDOException $e) {
            $this->logger->error('ERROR: Fauked in _insertOrIgnoreRowIntoTable, sql=' . $sql . " values=" . $values . " message=" . $e->getMessage());
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
            if (!$this->_insertOrIgnoreRowIntoTable($table, $row)) {
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

    protected function _fetchall($tableName) {
        $con = $this->db;
        $sql = "SELECT * FROM `$tableName` order by id asc";
        $rows = array();
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        return $rows;
    }    

    protected function _fetchall_with_last_update($tableName) {
        $con = $this->db;
        $sql = "SELECT *, IF(updTimestamp=0, creaTimestamp, updTimestamp) as lastUpdate FROM `$tableName` order by id asc";
        $rows = array();
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        return $rows;
    }    

    protected function _fetch_columns($tableName) {
        $con = $this->db;
        $sql = "SHOW FULL COLUMNS from $tableName";
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

        $this->logger->addDebug("_sendMailWithReplyTo: Step 2");
  
        // Initialize mail
        $mail = new PHPMailer;
        $mail->CharSet = 'UTF-8';
        // $mail->SMTPDebug = 2;                              // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;     

       $this->logger->addDebug("_sendMailWithReplyTo: Step 3");

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
        $this->logger->addDebug("_sendMailWithReplyTo: Step 4");

        $mail->Subject = $subject;
        $mail->Body = $body;
        if(!$mail->send()) {
            $this->logger->info('host:' . $mail->Host . ' username:' . $mail->Username . ' password:' . $mail->Password);
            $this->logger->info('from: [' . $from[0] . ', ' . $from[1] . ']');
            $this->logger->info('replyTo: [' . $replyTo[0] . ', ' . $replyTo[1]. ']');
            $this->logger->info("Subject:" . $subject);
            $this->logger->info("Subject:" . $body);
            $this->logger->warning("Problem sending mail with in function _sendMailWithReplyTo()");
            return(false);
            // $app->halt(500);
        } 
        $this->logger->addDebug("Mail send to $recipient with success");
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
        if (strpos($entry, '_thumb') !== false && $this->_isImage($entry)) {
            return(true);
        } else {
            return(false);
        }
    }

    protected function _isThumbnailAndOriginal($entry) {
        $entryWithoutThumb=trim($entry, '_thumb');

        if (strpos($entry, '_thumb') !== false && $this->_isImage($entry) && $this->_isImage($entryWithoutThumb)) {
            return true;
        } else {
            return false;
        }
    }

    protected function _isImageButNotThumbnail($entry) {
        if (strpos($entry, '_thumb') === false && $this->_isImage($entry)) {
            return true;
        } else {
            return false;
        }
    }


    protected function _fetchOrder($orderId) {
        $table=TBL_ORDER;
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
                    $this->logger->warning('Failed creation of thumbnail ' .$dir.'/'.$entry);
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

    protected function _createRegPartner($reg) {
        $regPartner = $reg;
        $regPartner['firstName']=$reg['firstNamePartner'];
        $regPartner['firstNamePartner']=$reg['firstName'];
        $regPartner['lastName']=$reg['lastNamePartner'];
        $regPartner['lastNamePartner']=$reg['lastName'];
        $regPartner['emailPartner']=$reg['email'];
        if (isset($reg['address'])) {
            $regPartner['addressPartner']=$reg['address'];
        }
        $regPartner['phonePartner']=$reg['phone'];

        if (isset($reg['emailPartner'])) {
            $regPartner['email']=$reg['emailPartner'];
        } else {
            $regPartner['email']='';
        }   

        if (isset($reg['phonePartner'])) {
            $regPartner['phone']=$reg['phonePartner'];
        } else {
            $regPartner['phone']='Phone missing';
        }   

        if (isset($reg['addressPartner'])) {
            $regPartner['address']=$reg['addressPartner'];
        } else {
            $regPartner['address']='Address missing';
        }   

        if (isset($regPartner['message'])) {
            unset($regPartner['message']);
        }
        if (isset($regPartner['newsletter'])) {
            unset($regPartner['newsletter']);
        }
        return($regPartner);
    }

    protected function _createOrderId($order) 
    {
        $con = $this->db;
        $insertObj = $this->_objectReduceToKeys($order, ['eventType']); 
        if (!isset($order['status'])) {
            $insertObj += ['status'=>'OK'];
        }
        $table=TBL_ORDER;
        $duplet=$this->_build_sql_insert_duplet($table, $insertObj);

        // Insert into tbl_order and get orderId
        if ($this->_sqlInsert($duplet['sql'], $duplet['values'])) {
            return $con->lastInsertId();
        } else {    
            return -1001;
        } 
    }

}



