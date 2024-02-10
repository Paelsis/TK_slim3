<?php

namespace App\Controllers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const TBL_CALENDAR='tbl_calendar';
const ADMINISTRATORS=['anita@tangosweden.se', 'admin@tangosweden.se'];

class CalendarController extends Controller
{
    protected function _createDbEntry($obj) 
    {
        $event = $obj;
        unset($event['creaTimestamp']);
        unset($event['updTimestamp']);
        unset($event['repeat']);
        unset($event['frequency']);

        $strEvent = json_encode($event);
        $this->logger->addDebug("_createDbEntry event:$strEvent");
        return $event;
    }

    protected function remove_emoji($string)
    {
        // Match Enclosed Alphanumeric Supplement
        $regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
        $clear_string = preg_replace($regex_alphanumeric, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $clear_string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);
        
        // Match Supplemental Symbols and Pictographs
        $regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
        $clear_string = preg_replace($regex_supplemental, '', $clear_string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clear_string = preg_replace($regex_dingbats, '', $clear_string);

        return $clear_string;
    }
        

    protected function _insertEvent($tableName, $event) 
    {
        // If eventId is not defined for eventList, then set it to unique Id
        if (!isset($event['eventId'])) {
            $prefix=$event['email']?$event['email']:'' . $event['startDateTime']; 
            $event['eventId'] = uniqid($prefix, false);
        }    

        $event['description'] = $this->remove_emoji($event['description']);
                  
        // Insert or replace a calendar event
        if (!$this->_replaceRow($tableName, $event)) {
            return false;
        } 

        return true;
    }  
    
    protected function _insertEventList($tableName, $events) 
    {
        if (count($events) > 0) {
            $event = $events[0];    

            foreach ($events as $event) {
                if (!$this->_insertEvent($tableName, $event)) {
                    $strEvent = json_encode($event);
                    $this->logger->error("Failed to insert event strationFestival: before _insertExtendedProductList event:$strEvent");
                    return false;
                }
            }    
        }
        return true;
    }

    // Post the registration only
    public function addEvent($request, $response) 
    {
        $event = $request->getParsedBody();
        $strEvent = json_encode($event);
        // $this->logger->addDebug("addEvent parsedBody:$strEvent");

        if ($this->_insertEvent(TBL_CALENDAR, $event)) {
            return $response->withJson(array(
                'status'=>'OK', 
                'message'=>"Successful insert of event"), 200);
        } else {        
            return $response->withJson(array(
                'status'=>'ERROR',
                'parsedBody'=> $strParsedBody, 
                'message'=>"Failed to add single event into calendar"), 204);
        }
    }

    public function addEvents($request, $response) 
    {
        $eventList = $request->getParsedBody();
        $strEventList = json_encode($eventList);
        // $this->logger->addDebug("addEvents parsedBody:$strEventList");

        if ($this->_insertEventList(TBL_CALENDAR, $eventList)) {
            $this->logger->addDebug("addEvents: success");

            return $response->withJson(array(
                'status'=>'OK', 
                'message'=>"Successful insert of event"), 200);
        } else {        
            $this->logger->addDebug("addEvents: success");

            return $response->withJson(array(
                'status'=>'ERROR',
                'parsedBody'=> $strParsedBody, 
                'message'=>"Failed to add events to calendar"), 200);
        }
    }

    protected function _cancelEventSql($event) 
    {
        $tableName = isset($event['tableName'])?$event['tableName']:'tbl_calendar';
        $eventId = isset($event['eventId'])?$event['eventId']:'No eventId';
        $email = $event['email'];
        $startDateTime = isset($event['startDateTime'])?$event['startDateTime']:null;

        $sql = "delete from $tableName where eventId ='$eventId'"; 
        $sql .= isset($startDateTime)?" and startDateTime='$startDateTime'":'';
        if (!in_array($email, ADMINISTRATORS)) {
            $sql .= " and email='$email'";
        };    

        $this->logger->info('_cancelEventSql:' . $sql);

        return $sql;
    }

    protected function _cancelEvent($event) {
        $sql = $this->_cancelEventSql($event);
        if ($this->_sqlExecute($sql)) {
            $cnt = $this->_getRowCount();
            return $cnt; 
        }
        return 0;
    }


    public function cancelEvent($request, $response) 
    {
        // $payload = $request->getQueryParams();
        $event = $request->getParsedBody();
        $strEvent = json_encode($event);
        $this->logger->addDebug("cancelEvent parsedBody:$strEvent");
       
        $rowCount = $this->_cancelEvent($event);
            
        if ($rowCount > 0) {
            $this->logger->info('CANCELLATION:' . $event['eventId']);
            return $response->withJson(array('status' => 'OK', "message"=>"Successful removal of $rowCount events from calendar.") ,200);
        } else {    
            return $response->withJson(array('status' => 'WARNING', "event"=> $strEvent, "message"=>"Cancellation not done. No events removed from calendar") ,200);
        }    
    }         


    protected function _getEventsSQL($table)
    {
        $sql = "SELECT eventId, email, title, description, hideLocationAndTime, startDateTime as start, endDateTime as end, startDateTime, endDateTime, location from $table
        where startDateTime >= CURRENT_DATE() order by startDateTime";

        // $this->logger->addDebug("_getEventsSql $sql");

        return $sql;
    }    

    protected function _getRows($sql) 
    {    
        $this->logger->info('_getRows: SQL-statement:' . $sql);
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                $row['hideLocationAndTime']= !empty($row['hideLocationAndTime']);
                $result[] = $row;
            }
            return $result;
        }
        catch(\Exception $ex){
            $this->logger->error('Excption found _getRows' . $ex);
            return null;
        }
    }    

    protected function _getTableForCity($table) {
        switch ($table) {
            case 'malmo':
                return('tbl_calendar');
            case 'gothenburg':
                return('tbl_calendar_gothenburg');
            case 'stockholm':
                return('tbl_calendar_stockholm');
            case 'helsingborg':
                return('tbl_calendar_helsingborg');
            case 'helsingborg':
                return('tbl_calendar_halmstad');
            case 'umea':
                return('tbl_calendar_umea');
            default:
                null;    
        }
    }

    public function getEvents($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $language = isset($allGetVars['language'])?$allGetVars['language']:'SV';
        $city = isset($allGetVars['city'])?$allGetVars['city']:'malmo';
        $this->logger->info('getCalendar');

        $tableName = $this->_getTableForCity($city);
        if ($tableName === null) {
            return $response->withJson(array('status' => 'WARNING', 'message'=>'No calendar exists for city $city', 'result:'=>null),200);
        }        
        
        $sql=$this->_getEventsSQL($tableName);
        $events=$this->_getRows($sql); 

        if(($cnt = count($events)) > 0) {
            return $response->withJson(array('status' => 'OK','result'=>$events, 'count'=>$cnt) , 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'No events found in database', 'result:'=>null),200);
        }
    }

    protected function _updateSingleEvent($event) 
    {

        $sql = "update tbl_calendar set title=?, description=?, hideLocationAndTime=?, startDateTime=?, endDateTime=?, location=? where eventId =? and startDateTime = ?";
        $values = array($event['title'], $event['description'], $event['hideLocationAndTime'], $event['startDateTime'], $event['endDateTime'], $event['location'], $event['eventId'], $event['originalStartDateTime']);

        if ($this->_sqlInsert($sql, $values)===true) {
            return true;
        } else {    
            $this->logger->error('_updateSingleEvent: Failed to insert row with command:' . $sql);
            return false;
        } 
    }

    protected function _updateAllEvents($event) 
    {
        $sql = "update tbl_calendar set title=?, description=?, startDateTime=concat(date(startDateTime), ' ', ?), endDateTime=concat(date(endDateTime), ' ', ?), location=? where eventId =?";
        $values = array($event['title'], $event['description'], $event['startTime'], $event['endTime'], $event['location'], $event['eventId']);

        if ($this->_sqlInsert($sql, $values)) {
            return true;
        } else {    
            $this->logger->error('_updateAllEvents: Failed to insert row with command:' . $sql);
            return false;
        } 
    }

    public function updateEvent($request, $response) 
    {
        $event = $request->getParsedBody();
        $strEvent = json_encode($event);
        $eventId = isset($event['eventId'])?$event['eventId']:null;
        $originalStartDateTime = isset($event['originalStartDateTime'])?$event['originalStartDateTime']:null;
        $this->logger->addDebug("updateEvent parsedBody:$strEvent originalStartDateTime:$originalStartDateTime");
        
        if (isset($event['changeAll'])) {
            $rowCount = $this->_updateAllEvents($event);
        } else {
            $rowCount = $this->_updateSingleEvent($event);
        }
            
        if ($rowCount > 0) {
            $this->logger->info('CANCELLATION:' . $event['eventId']);
            return $response->withJson(array('status' => 'OK', "message"=>"Successful update of event=$eventId. $rowCount rows updated in database.") ,200);
        } else {    
            return $response->withJson(array('status' => 'WARNING', "event"=> $strEvent, "message"=>"WARNING: Failed to update event") ,200);
        }    
    }         


}

