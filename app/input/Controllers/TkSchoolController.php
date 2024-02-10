<?php

namespace App\Controllers;

define("AVA_OPEN", "AV");
define("AVA_CLOSED_FOR_LEADER", "CL");
define("AVA_CLOSED_FOR_FOLLOWER", "CF");
define("AVA_COMPLETELY_CLOSED", "CC");

define("DISCOUNT_EARLY_BIRD", "EB");
define("DISCOUNT_CAMPAIGN_CODE", "CC");
define("DISCOUNT_PACKAGE_FESTIVAL", "PF");
define("DISCOUNT_PACKAGE_COURSE", "PC");

const SOCIAL_ID = ['UTE'=> 'UTE', 'INNE'=>'INNE'];
const DURATION_UNIT = ['d'=>['SV'=>'dagar', 'EN'=>'days', 'ES'=>'dias'],
               'w'=>['SV'=>'veckor', 'EN'=>'weeks', 'ES'=>'semanas'],
               'v'=>['SV'=>'veckor', 'EN'=>'weeks', 'ES'=>'semanas'],
               'm'=>['SV'=>'månader', 'EN'=>'months', 'ES'=>'meses'],
               'y'=>['SV'=>'år', 'EN'=>'years', 'ES'=>'años'],
               'å'=>['SV'=>'år', 'EN'=>'years', 'ES'=>'años'],
               'g'=>['SV'=>'gånger', 'EN'=>'times', 'ES'=>'vezes']
            ];

const DURATION_UNIT_SINGLE = ['d'=>['SV'=>'dagar', 'EN'=>'days', 'ES'=>'dias'],
               'w'=>['SV'=>'vecka', 'EN'=>'week', 'ES'=>'semana'],
               'v'=>['SV'=>'vecka', 'EN'=>'week', 'ES'=>'semana'],
               'm'=>['SV'=>'månad', 'EN'=>'month', 'ES'=>'mese'],
               'y'=>['SV'=>'år', 'EN'=>'year', 'ES'=>'año'],
               'å'=>['SV'=>'år', 'EN'=>'year', 'ES'=>'año'],
               'g'=>['SV'=>'gång', 'EN'=>'time', 'ES'=>'vez']
            ];

const EVENT_STATUS_TEXT = [
    'OPEN'=>[
        'SV'=>'Platser kvar för både förare och följare.',
        'EN'=>'Space available for both leaders and followers',
        'ES'=>'Space available for both leaders and followers',
    ], 
    'FULL'=>[
        'SV'=>'Dansen är fullbokad. Kontakta Tangkompaniet för eventuella återbud.',
        'EN'=>'No space available. Contact Tangokompaniet and check for cancellations',
        'ES'=>'No space available. Contact Tangokompaniet and check for cancellations',
    ], 
    'LEADERS_FULL'=>[
        'SV'=>'Fullbokat för förare, endast platser kvar åt följare',
        'EN'=>'Fully booked for leaders, space availabile for followers only',
        'ES'=>'Fully booked for leaders, space availabile for followers only',
    ], 
    'LEADERS_OVERFLOW'=>[
        'SV'=>'Överskott på förare (+3). För tillfället kan endast följare eller par anmäla sig.',
        'EN'=>'Leader surplus (+3). Currently registration is only open for followers and couples',
        'ES'=>'Leader surplus (+3). Currently registration is only open for followers and couples',
        ],
    'FOLLOWERS_FULL'=>[
        'SV'=>'Fullbokat för följare, endast platser kvar åt följare',
        'EN'=>'Fully booked for followers, space availabile for leaders only',
        'ES'=>'Fully booked for followers, space availabile for leaders only',
    ], 
    'FOLLOWERS_OVERFLOW'=>[
        'SV'=>'Överskott på följare (+3). För tillfället kan endast förare eller par anmäla sig.',
        'EN'=>'Follower surplus (+3). Currently registration is only open for leaders and couples',
        'ES'=>'Follower surplus (+3). Currently registration is only open for leaders and couples',
    ]
];     

class TkSchoolController extends Controller
{
    protected function _getStatusText($status) 
    {
        switch ($status) {
            case 'WL': return('Waiting on leader');        
            case 'WF': return('Waiting on follower');        
            case 'WS': return('Waiting on cancellation');
            case 'OK': return('Confirmed');
            default: return('Unknown status:' . $status);
        }    
    }    

    protected function translateSingleCourseLength($id, $language) 
    {
        preg_match('/[a-z]/i', $id, $m);
        //var_dump($m);
        $unit = isset($m[0])?strtolower($m[0]):'g';
        $value = substr($id, 0, strspn($id, "0123456789"));

        if ($value == 1) {
            $durationUnit = isset(DURATION_UNIT_SINGLE[$unit])?DURATION_UNIT_SINGLE[$unit][$language]:DURATION_UNIT_SINGLE['g'][$language];
        } else {
            $durationUnit = isset(DURATION_UNIT[$unit])?DURATION_UNIT[$unit][$language]:DURATION_UNIT['g'][$language];
        }    
        return $value . ' ' . $durationUnit;
    }


    protected function translateCourseLength($row) {
        $courseLength = $row['courseLength'];
        $row['courseLengthSV'] =  $this->translateSingleCourseLength($courseLength, 'SV');
        $row['courseLengthEN'] =  $this->translateSingleCourseLength($courseLength, 'EN');
        $row['courseLengthES'] =  $this->translateSingleCourseLength($courseLength, 'ES');
        return $row;
    }


    public function getUpdateTable($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (($str = $this->_checkMandatoryInput($allGetVars, ['id', 'presence']))!= null) {
            return $response->withJson(array('message' => $str, 'result'=>null),422);
        }
        $table = 'tbl_registration';
        $id = $allGetVars['id'];
        $data = ['presence'=>$allGetVars['presence']];
        $this->logger->addDebug('Updating id:' . $id);
        if ($this->_updateTable($table, $data, $id)) {
            $this->logger->addDebug('Table $table updated successfully for id:' . $id);
            return $response->withJson(array('message' => 'OK', 'id'=>$id, 'result'=>$data) ,200);
        } else{
            $this->logger->addDebug('Table $table failed for id:' . $id);
            $this->logger->addDebug('id:' . $id);
            return $response->withJson(array('message' => 'FAILED to update id='. $id . 'in table ' . $table , 'id'=>-1, 'result'=>null),422);
        }
    }

    public function updateTable($request, $response) 
    {
        $input = $request->getParsedBody();

        if (($str = $this->_checkMandatoryInput($input, ['id', 'table', 'data']))!= null) {
            return $response->withJson(array('status'=>'WARNING', 'message' => $str, 'result'=>null),200);
        }

        $id=$input['id'];
        $table=$input['table'];
        $data=$input['data'];
        $this->logger->addDebug('Updating id:' . $id);

        if ($this->_updateTable($table, $data, $id)) {
            $this->logger->addDebug('Table $table updated successfully for id:' . $id);
            return $response->withJson(array('status'=>'OK', 'message' => 'OK', 'id'=>$id, 'result'=>$data) ,200);
        } else{
            $this->logger->addDebug('Table $table failed for id:' . $id);
            $this->logger->addDebug('id:' . $id);
            return $response->withJson(array('status'=>'ERROR','message' => 'FAILED to update id='. $id . 'in table ' . $table , 'id'=>-1, 'result'=>null),200);
        }
    }

    protected function _insertArrIntoTable($table, $arr, $cols)
    {
        $newArr = array();
        foreach($arr as $row) {
            if (($str = $this->_checkMandatoryInput($row, $cols))== null) {
                $this->logger->error($str);
                return(false);
            } else {
                $newArr[] = $this->_objectReduceToKeys($row, $cols);
            }
        }    
        if ($this->_insertColumnsIntoTable($table, $newArr, $cols)) {
            $this->logger->addDebug('Rows inserted to  table ' . $table . ' successfully');
            return(true);
        } else {
            $this->logger->error('Failed to insert rows into table ' . $table);
            return(false);
        }
        return $response->withJson(array('message' => $str, 'result'=>null),422);
    }

    public function getInsertArrIntoTable($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if ($this->_insertColumnsIntoTable($table, $newArr, $cols)) {
            $this->logger->addDebug('Rows inserted to  table ' . $table . ' successfully');
            return(true);
        } else {
            $this->logger->error('Failed to insert rows into table ' . $table);
            return(false);
        }
    }

    public function updateRowsInPresence($request, $response) 
    {
        $input = $request->getParsedBody();

        if (($str = $this->_checkMandatoryInput($input, ['table', 'data'])) !== null) {
            return $response->withJson(array('message' => $str . 'in table ' . $table , 'result'=>null),200);
        }    

        $table = $input['table'];
        $rows = $input['data'];

        // Only unset the creaTimestam if it is set, it should not be updated
        foreach($rows as $row) {
            if (!empty($row['creaTimestamp'])) {
                unset($row['creaTimestamp']);
            } 
        }
        if (count($rows) === 0) {
            $this->logger->error('updateRowsInPresence: There was no rows in after filter rows (original count(rows)=' . count($rows) . ')' );
            return $response->withJson(array('message' => 'No rows inserted/updated in table ' . $table , 'result'=>$rows),200);
        } else if ($this->_replaceRowsInTable($table, $rows)) {
            $this->logger->addDebug('Table $table insert/update successfully');
            return $response->withJson(array('message' => 'OK', 'result'=>$rows, 'count'=>count($rows)) ,200);
        } else{
            $this->logger->error('Table $table failed to update');
            return $response->withJson(array('message' => 'FAILED to insert/update rows in table ' . $table , 'result'=>$rows),200);
        }
    }

    public function updateTableAll($request, $response) 
    {
        $input = $request->getParsedBody();

        if (($str = $this->_checkMandatoryInput($input, ['table', 'data']))!= null) {
            return $response->withJson(array('message' => $str, 'result'=>null),422);
        }

        $table=$input['table'];
        $data=$input['data'];

        if ($this->_updateTableAll($table, $data)) {
            $this->logger->addDebug('Table $table updated successfully for ids');
            return $response->withJson(array('message' => 'OK', 'result'=>$data) ,200);
        } else{
            $this->logger->addDebug('Table $table failed for ids');
            return $response->withJson(array('message' => 'FAILED to update ids in table ' . $table,  'result'=>null),422);
        }
    }

    public function updateTableProduction($request, $response) 
    {
        $input = $request->getParsedBody();
        $id=$input['id']?$input['id']:0;
        $table=$input['table'];
        $data=$input['data'];
        
        $allowedFields=['deleted', 'comment'];
        $allowedData=$this->_objectReduceToKeys($data, $allowedFields);

        $prodSchema='tangoma_web';
        $prodTable=$prodSchema.$table;
        
        // Build SQL-statement from object
        if ($this->_updateTable($prodTable, $allowedData, $id)) {
            return $response->withJson(array('message' => 'OK', 'id'=>$id, 'result'=>$data) ,200);
        } else{
            return $response->withJson(array('message' => 'FAILED to update id='. $id . 'in table ' . $table , 'id'=>-1, 'result'=>null),422);
        }
    }


    public function updateRegistrationOld($request, $response) 
    {
        // $this->logger defined in Controller 
        $input = $request->getParsedBody();
        $id=$input['id']?$input['id']:0;
        $data=$input['data'];

        $this->logger->addDebug('id:' . $id);

        
        // Build SQL-statement from object
        $table='tbl_registration';
        //$sql = $this->_build_sql_update_id($data);
        $sql = $this->_build_sql_update_id_old($table, $id, $data);
        $this->logger->addDebug($sql?'SQL statment:' . $sql:'WARNING:SQL statement not built !!!');

        // Make database call
        $con = $this->db;

        $sth = $con->prepare($sql);
        /*
        $sth->bindParam(':id', $id, PDO::PARAM_INT);
        $sth->bindParam(':tab', $tab, PDO::PARAM_STR, 20);        
        */
        if ($this->_sthExecute($sth)) {
            return $response->withJson(array('status' => 'OK','result'=>'Successful update') ,200);
        } else{
            return $response->withJson(array('status' => 'FAILED', 'result'=>'Failed to update' . $table),422);
        }
    
    }


    public function test($request, $response) 
    {
        return 'TkSchoolController test';
    }

    protected function _getPrice($obj) {
        if (isset($row['price'])) {
            if ($row['price'] > 0) {
                return($row['price']);
            }
        }
        return(0);
    }

    protected function _discount($reg) {
        $list = array();
        $list[] = $reg;
        $Discount = $this->container['Discount'];
        // $discounts = $Discount->getDiscounts();
        $discounts = $Discount->getDiscounts();
        $arr = $Discount->calcDiscountFromShoppingCart($discounts, $list);
        return $arr['totalDiscount'];
    }

    protected function _getRegistrationCountSQL($maxName, $maxImbalance) {
        if ($maxName !== null && $maxImbalance !== null) {
            return("SELECT R.productId, 
                sum(R.leader) leader, 
                count(*) - sum(R.leader) as follower, 
                count(*) as booked, 
                sum(IF (R.danceSite = 'ONLINE', 1, 0)) as regOnline,
                sum(IF (R.danceSite != 'ONLINE', 1, 0)) as regOnsite,
                C1.intValue as maxRegistrants,  
                C2.intValue as maxImbalance
            from tbl_registration R 
            left outer join tbl_constant C1 on C1.name='$maxName' 
            left outer join tbl_constant C2 on C2.name='$maxImbalance' 
            group by R.productId");
        }            
    }

    protected function _getRegistrationCountSQLNew($maxName) {
        if ($maxName !== null) {
            return("SELECT R.productId, 
                sum(R.leader) leader, 
                count(*) - sum(R.leader) as follower, 
                count(*) as booked, 
                count(*) as sum, 
                C.intValue maxRegistrants 
            from tbl_constant C left outer join tbl_registration R on 1 = 1
            where C.name='$maxName' 
            group by R.productId");
        }            
    }


    protected function _getRegistrationCourseSQL($language)
    {


        return("SELECT 
        IFNULL(C.eventType, 'Undefined') as eventType,
        IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2))) as year,
        CONCAT(IFNULL(ed.name$language, IF(SUBSTR(R.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleName,
        R.id, R.productType, R.productId, R.status, R.firstName, R.lastName, R.email, R.phone, R.firstNamePartner,
        R.lastNamePartner, R.emailPartner, R.leader, R.orderId, R.havePaid, R.shallPay, R.havePaid, R.deleted, R.message, 
        DATE_FORMAT(R.creaTimestamp, '%d%b%y') as regDate, 
        IF ((S.siteId = 'ON') OR (R.danceSite = 'ONLINE'), 'ONLINE', 'SITE') as danceSite,
        IFNULL(D.name$language, SUBSTR(R.productId, 13)) as name, 
        IFNULL(D.name$language, SUBSTR(R.productId, 13)) as name$language, 
        P.price,
        IFNULL(concat(IFNULL(t1.firstName, null), IF (t2.firstName is null, '', ' & '), IFNULL(t2.firstName,null)), 'Unknown') as teachers, 
        IFNULL(concat(IFNULL(t1.shortName, null), IF (t2.shortName is null, '', ' & '), IFNULL(t2.shortName,null)), 'Unknown') as teachersShort,
        WEEKDAY(IFNULL(C.startDate, DATE(SUBSTR(R.productId, 1,6))))  + 1 as dayOfWeek, 
        DAYNAME(IFNULL(C.startDate, DATE(SUBSTR(R.productId, 1,6)))) as dayname, 
        DATE_FORMAT(IFNULL(C.startDate, DATE(SUBSTR(R.productId, 1,6))), '%d%b%y') as startDate, 
        TIME_FORMAT(IFNULL(C.startTime, CONCAT(SUBSTR(R.productId, 7,2), ':', SUBSTR(R.productId, 9,2))),'%H:%i') as startTime, 
        IFNULL(S.city, SUBSTR(R.productId,11,2)) as city,
        IFNULL(S.siteName, SUBSTR(R.productId,11,2)) as siteName,
        IFNULL(D.sequenceNumber, 0) as sequenceNumber
        from 
        tbl_registration R  
        left outer join tbl_course C on C.productId = R.productId
        left outer join tbl_course_def D on D.courseId = IFNULL(C.courseId, SUBSTR(R.productId, 13)) 
        left outer join tbl_price_group P on D.priceGroup=P.priceGroup 
        left outer join tbl_site S on S.siteId = IFNULL(C.siteId, SUBSTR(R.productId, 11, 2))
        left outer join tbl_teacher t1 on t1.shortName = C.teacher1
        left outer join tbl_teacher t2 on t2.shortName = C.teacher2
        left outer join tbl_semester_def ed on ed.eventType = C.eventType
        where  R.productType='course'
        and DATE(SUBSTR(R.productId, 1,6)) >= DATE_SUB(NOW(), INTERVAL 26 WEEK)
        order by C.productId, D.sequenceNumber, R.creaTimestamp asc");
    }    



    protected function _getRegistrationHistorySQL($language)
    {
        return("SELECT 
        IFNULL(C.eventType, 'Undefined') as eventType,
        IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2))) as year,
        CONCAT(IF(SUBSTR(R.productId, 3,4) < '07', 'JAN-JUN', 'JUL-DEC'),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleName,
        R.id, IFNULL(R.year, DATE_FORMAT(C.startDate, '%y')) as year, IFNULL(R.productType, 'other') as productType, R.productId, R.status, R.firstName, R.lastName, R.email, R.phone, R.firstNamePartner,
        R.lastNamePartner, R.emailPartner, R.leader, R.orderId, R.danceSite,
        DATE_FORMAT(R.creaTimestamp, '%d%b%y') as regDate, 
        IFNULL(D.name$language, SUBSTR(R.productId, 13)) as name,
        P.price,
        concat(IFNULL(t1.firstName, ''), IF (t2.firstName is null, '', ' & '), IFNULL(t2.firstName,'')) as teachers, 
        concat(IFNULL(t1.shortName, ''), IF (t2.shortName is null, '', ' & '), IFNULL(t2.shortName,'')) as teachersShort,
        DAYNAME(IFNULL(C.startDate, DATE(SUBSTR(R.productId, 1,6)))) as dayname, 
        DATE_FORMAT(IFNULL(C.startDate, DATE(SUBSTR(R.productId, 1,6))), '%d%b%y') as startDate, 
        IFNULL(C.startTime, CONCAT(SUBSTR(R.productId, 7,2), ':', SUBSTR(R.productId, 9,2))) as startTime, 
        S.city, 
        IFNULL(S.siteName, SUBSTR(R.productId, 11,2)) as siteName
        from 
        tbl_registration_history R  
        left outer join tbl_course C on C.productId = R.productId
        left outer join tbl_course_def D on D.courseId = IFNULL(C.courseId, SUBSTR(R.productId, 13)) 
        left outer join tbl_price_group P on D.priceGroup=P.priceGroup 
        left outer join tbl_site S on S.siteId = IFNULL(C.siteId, SUBSTR(R.productId, 11, 2))
        left outer join tbl_teacher t1 on t1.shortName = C.teacher1
        left outer join tbl_teacher t2 on t2.shortName = C.teacher2
        where DATE(SUBSTR(R.productId, 1,6)) < DATE_SUB(NOW(), INTERVAL 26 WEEK)
        order by C.productId, D.sequenceNumber, R.creaTimestamp asc"
        );
    }    

    protected function _getRegistrationSocialSQL()
    {
        return("SELECT 
        R.id, R.productType, R.productId, R.status, R.firstName, R.lastName, R.email, R.phone, R.firstNamePartner,
        R.lastNamePartner, R.emailPartner, R.leader, R.orderId, R.shallPay, R.havePaid, R.deleted, R.message, R.label,
        DATE_FORMAT(DATE(SUBSTR(R.productId, 1,6)), '%d%b%y') as startDate, 
        CONCAT(SUBSTR(R.productId, 7,2), ':', SUBSTR(R.productId, 9,2)) as startTime, 
        '(no teachers)' as teachers, 
        '(no)' as teachersShort, 
        WEEKDAY(DATE(SUBSTR(R.productId, 1,6)))  + 1 as dayOfWeek, 
        DATE_FORMAT(R.creaTimestamp, '%d%b%y') as regDate 
        from 
        tbl_registration R  
        where R.productType='social'
        and DATE(SUBSTR(R.productId, 1,6)) > DATE_SUB(NOW(), INTERVAL 31 DAY) 
        order by R.productId, R.creaTimestamp"
        );
    }    

    protected function _getTeacherNote($tableName, $language)
    {
        return(" 
        SELECT T.id, 
        T.creaTimestamp, 
        T.updTimestamp, 
        groupId, 
        T.textId, 
        S.siteName,
        IFNULL(D.name$language, SUBSTRING(T.textId, 13, char_length(T.textId) -22)) as courseName,
        date_format(str_to_date(substr(T.textId, 1,6), '%y%m%d'),'%d%b%y') as startDate, 
        date_format(right(T.textId, 10), '%W %d %b %Y') as courseDate,
        right(T.textId, 10) as sortDate,
        dayname(right(T.textId, 10)) as dayname,
        substr(T.textId, 7,4) as time, 
        SUBSTRING(T.textId, 1, char_length(T.textId) -10) as productId, 
        language, textBody from $tableName T 
        left outer join tbl_site S on S.siteId = substr(T.textId, 11,2) 
        left outer join tbl_course_def D on D.courseId = SUBSTRING(T.textId, 13, char_length(T.textId) -22) 
        join tbl_course C on C.productId = SUBSTRING(T.textId, 1, char_length(T.textId) -10) 
        where T.groupId = 'PRESENCE'
        order by D.sequenceNumber, WEEKDAY(C.startDate), productId, sortDate"
        );
    }


    protected function _getPresenceSQL($courseDate, $language)
    {
        if ($courseDate === null) {
            return("
                SELECT 
                R.id, R.productId, R.firstName, R.lastName, R.email, 
                concat (R.firstName, ' ', R.lastName) fullName, 
                CONCAT(IFNULL(ed.name$language, IF(SUBSTR(R.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleName,
                CONCAT(IFNULL(ed.name$language, IF(SUBSTR(R.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleId,
                D.nameEN as courseName, 
                concat(t1.firstName, ' & ', t2.firstName) as teachers, 
                concat(C.teacher1, ' & ', C.teacher2) as teachersShort,
                si.city as city,
                TIME_FORMAT(C.startTime, '%H:%i') as startTime,
                C.startDate,
                DAYNAME(C.startDate) as dayname,
                WEEKDAY(C.startDate) as weekDay,
                DAYOFWEEK(C.startDate) as dayOfWeek,
                0 as present, 
                '' as courseDate
                from 
                tbl_registration R 
                left outer join tbl_course C on C.productId = R.productId
                left outer join tbl_course_def D on D.courseId = C.courseId 
                left outer join tbl_teacher t1 on t1.shortName = C.teacher1
                left outer join tbl_teacher t2 on t2.shortName = C.teacher2
                left outer join tbl_site si on si.siteId = C.siteId
                left outer join tbl_semester_def ed on ed.eventType = C.eventType
                where C.scheduleId is not null
                and ed.productType = 'course'
                order by D.sequenceNumber, WEEKDAY(C.startDate), R.productId, R.firstName, R.lastName asc");
        } else {
            return("
                SELECT 
                R.id, R.productId, R.firstName, R.lastName, R.email, 
                concat (R.firstName, ' ', R.lastName) fullName, 
                P.creaTimestamp,
                CONCAT(IFNULL(ed.name$language, IF(SUBSTR(R.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleName,
                CONCAT(IFNULL(ed.name$language, IF(SUBSTR(R.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(C.year, CONCAT(20, SUBSTR(R.productId, 1,2)))) as scheduleId,
                IFNULL(sc.nameEN, IFNULL(C.scheduleId, 'Course not in active schema')) as scheduleName,
                D.nameSV, D.nameEN, D.nameES, 
                concat(t1.firstName, ' & ', t2.firstName) as teachers, 
                concat(C.teacher1, ' & ', C.teacher2) as teachersShort,
                si.city as city,
                C.startDate,
                DAYNAME(C.startDate) as dayname, 
                WEEKDAY(C.startDate) as weekDay,
                DAYOFWEEK(C.startDate) as dayOfWeek,
                TIME_FORMAT(C.startTime, '%H:%i') as startTime,
                IFNULL(P.present, 0) present, 
                P.courseDate as courseDate 
                from 
                tbl_registration R 
                left outer join tbl_presence P on 
                    P.firstName=R.firstName 
                    and P.lastName = R.lastName 
                    and P.productId = R.productId 
                    and P.email = R.email
                    and DATEDIFF('$courseDate', P.courseDate) = 0
                left outer join tbl_course C on C.productId = R.productId 
                left outer join tbl_course_def D on D.courseId = C.courseId
                left outer join tbl_semester_def ed on ed.eventType = C.eventType
                left outer join tbl_teacher t1 on t1.shortName = C.teacher1
                left outer join tbl_teacher t2 on t2.shortName = C.teacher2
                left outer join tbl_site si on si.siteId = C.siteId
                and ed.productType = 'course'
                order by D.sequenceNumber, WEEKDAY(C.startDate), R.productId, R.firstName, R.lastName asc, P.creaTimestamp"   
            );

        }    
    }

    protected function _getPresenceHistorySQL($language)
    {
        return("
        SELECT
        p.id,
        p.firstName,
        p.lastName,
        p.email,
        p.courseDate,
        p.productId,
        p.present,
        DATE_FORMAT(
            STR_TO_DATE(
                SUBSTR(p.productId, 1, 6),
                '%y%m%d'
            ),
            '%d/%m-%Y'
        ) AS startDate,
        DATE_FORMAT(
            STR_TO_DATE(SUBSTR(p.productId, 7, 4),
            '%H%i'),
            '%H:%i'
        ) AS startTime,
        DAYNAME(
            STR_TO_DATE(
                SUBSTR(p.productId, 1, 6),
                '%y%m%d'
            )
        ) AS DAYNAME,
        SUBSTR(p.productId, 13) AS courseId,
        IF(p.present = 1, 1.0, 0.5) AS opacity,
        IF(
            p.present = 1,
            'närvarande',
            'frånvarande'
        ) AS presence,
        IFNULL(d.nameEN, p.courseName) AS courseName,
        CONCAT(IFNULL(ed.nameEN, IF(SUBSTR(p.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(c.year, CONCAT(20, SUBSTR(p.productId, 1,2)))) as scheduleName,
        CONCAT(IFNULL(ed.nameEN, IF(SUBSTR(p.productId, 3,4) < '07', 'Not in schedule JAN-JUN', 'Not in schedule JUL-DEC')),' ', IFNULL(c.year, CONCAT(20, SUBSTR(p.productId, 1,2)))) as scheduleId,
    IFNULL(
            si.siteName,
            SUBSTR(p.productId, 11, 2)
        ) AS siteName,
        IFNULL(si.city, '') AS city
    FROM
        `tbl_presence` p
    LEFT OUTER JOIN tbl_course_def AS d
    ON
        d.courseId = SUBSTR(p.productId, 13)
    LEFT OUTER JOIN tbl_course c ON
        c.productId = p.productId
    LEFT OUTER JOIN tbl_semester_def ed ON
        ed.eventType = c.eventType
    LEFT OUTER JOIN tbl_site AS si
    ON
        si.siteId = SUBSTR(p.productId, 11, 2)
    ORDER BY
        p.productId,
        p.firstName,
        p.lastName");
    }


    protected function _getRows($sql) 
    {    
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            return $result;
        }
        catch(\Exception $ex){
            return null;
        }
    }    

    protected function _getRegistrationHistory($sql) 
    {    
        $this->logger->info('SQL-statement in _getRegistration:' . $sql);
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                if (!isset($row['eventType'])) {
                    $row['eventType']='Undefined eventType';
                }
                $row['groupByProductId']=$row['productId'];
                $result[] = $row;
            }
            return $result;
        }
        catch(\Exception $ex){
            $this->logger->error('Excption found _getRegistrationHistory' . $ex);
            return null;
        }
    }    


    protected function _getRegistration($sql) 
    {    
        $this->logger->info('SQL-statement in _getRegistration:' . $sql);
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                if (!isset($row['scheduleId'])) {
                    $row['scheduleId']='NoSchedulelId';
                }
                $row['groupByStatus']=$row['status'];
                $row['groupByProductId']=$row['productId'];
                $row['price']=$this->_getPrice($row);
                $row['statusText']=$this->_getStatusText($row['status']);
                if (isset($row['teachersShort'])) {
                    $row['teachersShort']=rtrim('&', rtrim(' ', $row['teachersShort']));
                }
                if (isset($row['priceGroup'])) {
                    unset($row['priceGroup']);
                }    
                $result[] = $row;
            }
            return $result;
        }
        catch(\Exception $ex){
            return null;
        }
    }    


    public function getPresence($request, $response) 
    {

        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['courseDate'])) {
            $courseDate=$allGetVars['courseDate'];
        } else {
            $courseDate=null;
        }    
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);


        $sql=$this->_getPresenceSQL($courseDate, $language);
        $rows = $this->_getRows($sql); 
        if($rows) {
            return $response->withJson(array('status' => 'true','result'=>$rows) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }

    public function getPresenceHistory($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $sql=$this->_getPresenceHistorySQL($language);
        $rows = $this->_getRows($sql); 



        if($rows) {
            return $response->withJson(array('status' => 'true','result'=>$rows) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }

    public function getTeacherNote($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['tableName'])) {
            $tableName = $allGetVars['tableName'];
        } else {
            $tableName = 'tbl_teacher_note';
        }
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $sql=$this->_getTeacherNote($tableName, $language);
        $rows = $this->_getRows($sql); 

        if($rows) {
            return $response->withJson(array('status' => 'true','result'=>$rows) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }


    public function getRegistration($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['productType'])) {
            $productType = $allGetVars['productType'];
        } else {
            $productType = null;
        }
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $regs = array();
        if ($productType === null || strpos($productType, 'course')!==false) {
            $this->logger->info('getRegistration (course)');
            $sql=$this->_getRegistrationCourseSQL($language);
            $regs = array_merge($regs, $this->_getRegistration($sql)); 
        } 
        if ($productType === null || strpos($productType, 'social')!==false) {
            $this->logger->info('getRegistration social');
            $sql=$this->_getRegistrationSocialSQL();
            $regs = array_merge($regs, $this->_getRegistration($sql)); 
        }    
        if ($productType === null || strpos($productType, 'marathon')!==false) {
            $this->logger->error('Use controller getRegistrationMarathon');
        }    
        if ($productType === null || strpos($productType, 'festival')!==false) {
            $this->logger->error('Use controller getRegistrationFestival');
        }    

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    public function getRegistrationHistory($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);
        $this->logger->info('getRegistrationHistory');

        $regs = array();
        $sql=$this->_getRegistrationHistorySQL($language);
        $regs = array_merge($regs, $this->_getRegistrationHistory($sql)); 

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }


    public function getRegistrationCount($request, $response) {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['maxName'])) {
            $maxName=$queryParams['maxName'];
        } else {
            $maxName='maxRegistrantsInside';
        }    
        if (isset($queryParams['maxImbalance'])) {
            $maxImbalance=$queryParams['maxImbalance'];
        } else {
            $maxImbalance='maxImbalanceInside';
        }    
        if (isset($queryParams['language'])) {
            $language=$queryParams['language'];
        } else {
            $language='SV';
        }    

        $sql=$this->_getRegistrationCountSQL($maxName, $maxImbalance);
        $this->logger->info('SQL:' . $sql);


        $regs = $this->_getRows($sql); 

        $result=array();
        foreach($regs as $reg) {
            $imbalance = (int) $reg['leader'] - (int) $reg['follower'];
            if ($reg['booked'] >= $reg['maxRegistrants']) {
                $reg['avaStatus']='CR';
                $reg['eventStatus']='FULL';                 
            } else {
                $reg['avaStatus']='AV';
                if ((int) $reg['leader'] >= (((int) $reg['maxRegistrants']) + 1)/2) {
                    $reg['eventStatus']='LEADERS_FULL';                 
                } else if ($imbalance >= (int) $reg['maxImbalance']) {
                    $reg['eventStatus']='LEADERS_OVERFLOW';                 
                } else if ((int) $reg['follower'] >= (((int) $reg['maxRegistrants']) + 1)/2) {
                    $reg['eventStatus']='FOLLOWERS_FULL';
                } else if ($imbalance <= - (int) $reg['maxImbalance']) {
                    $reg['eventStatus']='FOLLOWERS_OVERFLOW';                 
                } else {            
                    $reg['eventStatus']='OPEN';                 
                }    
            }
            $reg['eventStatusText']=EVENT_STATUS_TEXT[$reg['eventStatus']][$language];
            $reg['imbalance'] = $imbalance;
            $reg['maxImbalance'] = (int) $reg['maxImbalance'];
            $reg['availableCount']=intval($reg['maxRegistrants'] - (isset($reg['booked'])?$reg['booked']:0));
            $reg['booked']=(int) $reg['booked'];
            $reg['leader']=(int) $reg['leader'];
            $reg['follower']=(int) $reg['follower'];
            $reg['maxRegistrants']=(int) $reg['maxRegistrants'];
            //unset($reg['leader']);
            //unset($reg['follower']);
            $result[]=$reg;
        }


        if($regs) {
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }    

    // courses are products to sell and must have a productType and a productId (other fields speicalized)
    public function courseDef($request, $response) 
    {
        try{
            $con = $this->db;
            // Note that productType and productId is important for this SELECT since we are saving it in tbl_products
            $sql = "SELECT 
                DF.*, PG.price, DF.courseId as textId,
                IFNULL(CT.nameSV, 'Övriga kurser') as groupNameSV, 
                IFNULL(CT.nameEN, 'Other courses') as groupNameEN, 
                IFNULL(CT.nameES, 'Otros cursos') as groupNameES, 
                IFNULL(CT.sequenceNumber, 9999) as groupSequenceNumber, 
                IFNULL(CT.courseType, 'XX') as courseType
                FROM tbl_course_def DF
                left outer join tbl_price_group PG on  DF.priceGroup = PG.priceGroup
                left outer join tbl_course_type CT on CT.courseType = DF.courseType
                where DF.active=1
                order by groupSequenceNumber asc, DF.sequenceNumber asc";
            $result = null;

            foreach ($con->query($sql) as $row) {
                // If price is missing, use price from tbl_price_group table
                if (!isset($row['price'])) {
                    $row['price'] = 'No pricegroup';
                }
                $row['sequenceNumber'] = (int) $row['sequenceNumber'];
                $row['groupSequenceNumber'] = (int) $row['groupSequenceNumber'];
                $row = $this->translateCourseLength($row);
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
                    
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    
    }
    

    // courses are products to sell and must have a productType and a productId (other fields speicalized)
    public function ScheduleCourses($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $productId = isset($allGetVars['productId'])?$allGetVars['productId']:null;
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);
        $andWhereClause = isset($productId)?" and productId = '$productId'":'';
        try{
            $con = $this->db;
            // Note that productType and productId is important for this SELECT since we are saving it in tbl_products
            $sql = "SELECT 
                'course' as productType, A.*, 
                /*
                concat(ET.name$language, ' ', A.year) as scheduleId, 
                concat(ET.name$language, ' ', A.year) as scheduleName,
                */
                DATE_FORMAT(A.startDate, '%d%b%y') startDate, 
                WEEKDAY(A.startDate) + 1 dayOfWeek, 
                TIME_FORMAT(A.startTime, '%H:%i') as startTime,
                TIME_FORMAT(ADDTIME(A.startTime, SEC_TO_TIME(C.minutes*60)), '%H:%i') as endTime,
                DATEDIFF(A.startDate, CURDATE()) daysUntilStart, 
                S.address, S.siteName, S.city, S.urlLocation, 
                C.nameSV, C.nameEN, C.nameES, C.courseId as textId, C.courseType, C.payViaApp, C.courseLength, C.maxRegistrants, C.genderBalance, C.sequenceNumber, 
                IFNULL(sd.name$language,'Out of present schedule')  as eventName,
                concat(IFNULL(A.teacher1, ''), IF (A.teacher1 IS NULL OR A.teacher2 IS NULL OR LENGTH(A.teacher1)=0 OR LENGTH(A.teacher2)=0, '', ' & '), IFNULL(A.teacher2, '')) as teachersShort,
                concat(IFNULL(T1.firstName, ''), IF (A.teacher1 IS NULL OR A.teacher2 IS NULL OR LENGTH(A.teacher1)=0 OR LENGTH(A.teacher2)=0, '', ' & '), IFNULL(T2.firstName, '')) as teachers,
                D.price as price
                FROM tbl_course A 
                left outer join tbl_site S on A.siteId=S.siteId
                left outer join tbl_semester_def sd on sd.eventType=A.eventType
                left outer join tbl_teacher as T1 on T1.shortName=A.teacher1
                left outer join tbl_teacher as T2 on T2.shortName=A.teacher2,
                tbl_course_def C 
                left outer join tbl_price_group D on C.priceGroup=D.priceGroup
                where A.active = 1 
                and C.courseId = A.courseId $andWhereClause
                order by A.year asc, A.startDate asc, dayOfWeek, A.startTime asc";
            $result = null;

            $rows=$this->_queryWithInventory($sql, 'tbl_registration');
            foreach ($rows as $row) {
                if (!isset($row['price'])) {
                    $row['price'] = 'No price';
                }

                if ($row['payViaApp']!=='1') {
                    unset($row['payViaApp']);
                    $row['dontPayViaApp']=1;
                } 
                $row['emailResponsible'] = $row['email'];
                unset($row['email']);

                $row = $this->translateCourseLength($row);
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
                   
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
   
    }

    public function teacher($request, $response) 
    {
        try{
            $con = $this->db;
            $result = null;
            $sql = "SELECT * from tbl_teacher order by shortName" ;

            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
                    
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }

    
    // courses are products to sell and must have a productType and a productId (other fields speicalized)

    protected function _fetchInventory($table) 
    {
        $con = $this->db;
        $sql = "SELECT productType, productId, leader, danceSite, count(*) cnt from `$table` group by productType, productId, danceSite, leader
                order by productType, productId, danceSite, leader asc";

        $result = array();
        try {
            foreach ($con->query($sql) as $row) {
                $productType=$row['productType'];
                $productId=$row['productId'];
                $cnt=$row['cnt'];
                if (!isset($result[$productType])) {
                    $result[$productType]=array();
                }    
                if (!isset($result[$productType][$productId])) {
                    $result[$productType][$productId]=array();
                    $result[$productType][$productId]['countLeader']=0;
                    $result[$productType][$productId]['countFollower']=0;
                    $result[$productType][$productId]['regOnline']=0;
                    $result[$productType][$productId]['regOnsite']=0;
                }    

                if ($row['leader']) {
                    $result[$productType][$productId]['countLeader'] += $cnt;
                } else {
                    $result[$productType][$productId]['countFollower'] +=$cnt;
                }    
                if ($row['danceSite'] === 'ONLINE') {
                    $result[$productType][$productId]['regOnline'] +=$cnt;
                } else  {
                    $result[$productType][$productId]['regOnsite'] +=$cnt;
                } 
            }
            return($result);
        } 
        catch(PDOException $e){
            $this->logger->error('message' . $e->$message);
            return null;
        }
    }    

    protected function _AddInventory($inv, $row)
    {
        $productType=$row['productType'];
        $productId=$row['productId'];

        $fields = array('countLeader', 'countFollower', 'regOnline', 'regOnsite');

        foreach ($fields as $fld) {
            $row[$fld] = 0;
        }
        if (isset($inv[$productType][$productId])) {
            $invCourse=$inv[$productType][$productId];
            foreach ($fields as $fld) {
                if (isset($invCourse[$fld])) {
                    $row[$fld]=$invCourse[$fld];
                }
            }    
        }
        return($row);
    }
    
    protected function _AddAvaStatuses($row) 
    {
        $countLeader=$row['countLeader'];
        $countFollower=$row['countFollower'];
        $total = $countLeader + $countFollower;

        /*
        if ($countLeader - $countFollower >= $row['genderBalance']) {
            $row['leaderSurplus']=true;
            $row['avaStatus']=constant('AVA_CLOSED_FOR_LEADER');
        } else if ($countFollower - $countLeader >= $row['genderBalance']) {  
            $row['followerSurplus']=true;
            $row['avaStatus']=constant('AVA_CLOSED_FOR_FOLLOWER');
        } 
        */
        // Commented out 8/1-2021 if ($total > $row['maxRegistrants'] + 10) {
        if ($total >= $row['maxRegistrants']) {
           $row['avaStatus']=constant('AVA_COMPLETELY_CLOSED');
        } else {  
            $row['avaStatus']=constant('AVA_OPEN');
        }    
        if ($row['regOnline'] >= $row['maxRegOnline']) {
            $row['avaStatusOnline']=constant('AVA_COMPLETELY_CLOSED');
        } else {  
             $row['avaStatusOnline']=constant('AVA_OPEN');
        }    
        if ($row['regOnsite'] >= $row['maxRegOnsite']) {
            $row['avaStatusOnsite']=constant('AVA_COMPLETELY_CLOSED');
        } else {  
            $row['avaStatusOnsite']=constant('AVA_OPEN');
        }    
        return $row;
    }



    public function _queryWithInventory($sql, $tblReg) {
        try{
            $con = $this->db;
            $result = array();
            $arrReg=$this->_fetchInventory($tblReg);    
            foreach ($con->query($sql) as $row) {
                $rowNew=$this->_AddInventory($arrReg, $row);
                $rowNew2=$this->_AddAvaStatuses($rowNew);
                $result[] = $rowNew2;
            }    
            $this->logger->info('_queryWithInventory returned ' . count($result) . 'rows successfully');
            return $result;
        } 
        catch(PDOException $e) {
            $this->logger->error('_queryWithInventory FAILED. Error messa' . $e->$message);
            return null;
        }
    }

    public function getRegistrationInventory($request, $response) 
    {
        $result = $this->_fetchInventory('tbl_registration');
        if($result != null){
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }

    public function getPartnerSearchList($request, $response) 
    {
        $result = $this->_getClearing('tbl_partner_search');
        if ($result != null) {
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }    

    // courses are products to sell and must have a productType and a productId (other fields speicalized)
    public function ScheduleSingleCourse($request, $response) 
    {
        try{
            $con = $this->db;
            // Note that productType and productId is important for this SELECT since we are saving it in tbl_products
            $sql = "SELECT 
                A.*,'course' as productType, 
                DATE_FORMAT(A.startDate, '%e/%c-%y') startDate, 
                WEEKDAY(A.startDate) + 1 dayOfWeek, 
                concat(IFNULL(A.teacher1, ''), IF (A.teacher1 IS NULL OR A.teacher2 IS NULL, '', ' & '), IFNULL(A.teacher2, '')) as teachersShort,
                concat(IFNULL(T1.firstName, ''), IF (A.teacher1 IS NULL OR A.teacher2 IS NULL, '', ' & '), IFNULL(T2.firstName, '')) as teachers,
                TIME_FORMAT(A.startTime, '%H:%i') as startTime,
                TIME_FORMAT(ADDTIME(A.startTime, SEC_TO_TIME(C.minutes*60)), '%H:%i') as endTime,
                S.address, S.siteName, S.city, S.urlLocation,  
                C.nameSV, C.nameEN, C.nameES, 
                C.courseType, C.maxRegistrants, C.genderBalance, C.courseId, C.courseId as textId, 
                D.price as price, 
                C.courseLength 
                FROM tbl_course A 
                LEFT OUTER JOIN tbl_site S on  S.siteId=A.siteId
                LEFT OUTER JOIN tbl_course_def C on C.courseId = A.courseId 
                LEFT OUTER JOIN tbl_price_group D on C.priceGroup = D.priceGroup
                left outer join tbl_teacher as T1 on T1.shortName=A.teacher1
                left outer join tbl_teacher as T2 on T2.shortName=A.teacher2
                WHERE 
                A.active = 1 
                order by A.startDate, dayOfWeek, S.city asc";

            $rows=$this->_queryWithInventory($sql, 'tbl_registration');
            $cnt=count($rows);

            $result = null;
            foreach ($rows as $row) {
                if (!isset($row['price'])) {
                    $row['price'] = 'No pricegroup';
                }

                // If price is missing, use price from tbl_price_group table
                $row['emailResponsible'] = $row['email'];

                $row = $this->translateCourseLength($row);

                unset($row['email']);
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true', 'count'=>$cnt, 'result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
                    
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    
    }
    

    protected function _copyScheduleCourse($templateName) 
    {
        $this->logger->addDebug('templateName = ' . $templateName);
        $sql = "INSERT IGNORE INTO tbl_course (
                    templateName,
                    eventType,
                    `year`,
                    courseId, 
                    productId,
                    teacher1,
                    teacher2,
                    siteId, 
                    online, 
                    startDate, 
                    startTime, 
                    email,
                    maxRegOnsite,
                    maxRegOnline,
                    maxRegTotal,
                    active) 
                SELECT 
                    te.templateName,
                    IFNULl(te.eventType, 'Undefined'),
                    IFNULL(te.year,DATE_FORMAT(te.startDate, '%y')),
                    df.courseId, 
                    concat(DATE_FORMAT(te.startDate, '%y%m%d'), TIME_FORMAT(te.startTime, '%H%i'),te.siteId,df.courseId),
                    IFNULL(te.teacher1, ''),
                    IFNULL(te.teacher2, ''),
                    te.siteId,
                    IFNULL(te.online, 0),
                    te.startDate,
                    TIME_FORMAT(te.startTime, '%H:%i'),
                    t1.email,
                    te.maxRegOnsite,
                    te.maxRegOnline,
                    te.maxRegTotal,
                    1 as active
                    from (
                        (tbl_course_template te join tbl_course_def df) 
                        left join tbl_teacher t1 on (IFNULL(te.teacher1,'') = t1.shortName)
                    )
                    where (te.courseId = df.courseId)
                    and te.templateName ='" . $templateName . "'";

        $this->logger->addDebug('_copyScheduleCourse, sql:' . $sql);

        if ($this->_sqlExecute($sql)) {
            return true;
        } else {
            return false;
        }
    }


    public function getCopySchedule($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (!isset($allGetVars['templateName'])) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'templateName not tiven'),422);
        }    

        $templateName=$allGetVars['templateName'];

        $con = $this->db;
        if ($this->_copySchedule($templateName)) {
            return $response->withJson(array('status' => 'OK','message'=>'Copy of tables successful') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    public function CopySchedule($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (!isset($payload['templateName'])) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'templateName is not defined in payload'),422);
        }    
        $templateName=$payload['templateName'];

        $con = $this->db;
        if ($this->_copyScheduleCourse($templateName)) {
            return $response->withJson(array('status' => 'OK','message'=>'Copy of tables successful') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    

    public function addWaitlist($request, $response)
    {
        // $this->logger defined in Controller 
        $input = $request->getParsedBody();
        $course=$input['course'];

        if ($course !== null) {
            $this->logger->addDebug('Adding waitlist for course id:' . $course['productId'] . ' ' . $course['firstName'] . ' ' . $course['lastName']);
            $result=array('firstName'=>$course['firstName'], 'lastName'=>$course['lastName'], 'email'=>$course['email']);
        } else {
            $result=array('status'=>'ERROR');
        }
        return $response->withJson(array('status' => 'waitlisted', 'result'=>$result) ,200);
    }

    protected function _checkDiscount($regs, $discounts) 
    {
        foreach($regs as $re) {
            foreach($discounts as $di) {
               // scheduleId check 
               if (!(isset($di['scheduleId']) && (strlen($di['scheduleId']) > 0) && ($di['scheduleId']!==$re['scheduleId']))) {
                    switch($di['discountType']) {
                        case constant('EARLY_BIRD'):
                            $sumDiscount += $this->_discountEarlyBird($di, $re);
                            break;
                        case constant('CAMPAIGN_CODE'):
                            $sumDiscount += $this->_discountcampaignCode($di, $re);
                        break;
                        case constant('PACKAGE_COURSE'):
                            $sumDiscount += $this->_discountPackageCourse($di, $re);
                        break;
                        case constant('PACKAGE_FESTIVAL'):
                            $sumDiscount += $this->_discountPakageFestival($di, $re);
                            break;
                        default:
                    }
                } 
            }
        }
    }

    public function testDiscount($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['discount'])) {
            $discount=$queryParams['discount'];
        }    
        $con = $this->db;
        if (true) {
            return $response->withJson(array('status' => 'OK','discount'=>5000, 'currency'=>'SEK') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    protected function _moveRegistrantsSql($fromProductId, $toProductId)
    {
        return("UPDATE tbl_registration set `productId` = '$toProductId' where `productId` = '$fromProductId'");
    }

    public function getChangeProductIdForReg($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        $fromProductId=$queryParams['fromProductId'];
        $toProductId=$queryParams['toProductId'];
        return $response->withJson(array('status' => 'OK','message'=>'Successful update of productId') , 200);
        $sql = $this->_moveRegistrantsSql($parms['fromProductId'], $parms['toProductId']);
        if ($this->_sqlExecute($sql)) {
            return $response->withJson(array('status' => 'OK','message'=>'Successful update of productId') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to update productId'),204);
        } 
    }         

    protected function _updateCourseSql($productId, $schedule) {
        $sql = "update ignore tbl_course set " . 
        "courseId='" . $schedule['courseId'] . "'," . 
        "startDate='" . $schedule['startDate'] . "'," . 
        "startTime='" . $schedule['startTime'] . "'," .  
        "teacher1='" . $schedule['teacher1'] . "'," . 
        "teacher2='" . $schedule['teacher2'] . "'," .
        "siteId='" . $schedule['siteId'] . "'," .
        "productId=concat(DATE_FORMAT('" . $schedule['startDate'] . "', '%y%m%d')," .
            "TIME_FORMAT('" . $schedule['startTime'] . "', '%H%i'), " .   
            "'" . $schedule['siteId'] . "', " . 
            "'" . $schedule['courseId'] . "') ". 
        "where productId = '" . $productId . "'"
        ;
        $this->logger->addDebug('updateCourseSql, sql:' . $sql);
        return($sql);
    }

    protected function _updateRegistrationSql($productId, $schedule) {
        $sql = "update tbl_registration set " . 
            "productId=concat(DATE_FORMAT('" . $schedule['startDate'] . "', '%y%m%d')," .
            "TIME_FORMAT('" . $schedule['startTime'] . "', '%H%i'), " .   
            "'" . $schedule['siteId'] . "', " . 
            "'" . $schedule['courseId'] . "') " . 
            "where productId = '" . $productId . "'";
        return($sql);
    }


    public function updateProductId($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($str = $this->_checkMandatoryInput($payload, ['fromProductId', 'toProductId']))!= null) {
            return $response->withJson(array('message' => $str, 'result'=>null),422);
        }
        $fromProductId = $payload['fromProductId'];
        $toProductId = $payload['toProductId'];
        $sql = $this->_moveRegistrantsSql($fromProductId, $toProductId);
        if ($this->_sqlExecute($sql)) {
            return $response->withJson(array('status' => 'OK','message'=>'Successful update of productId') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to update productId'),204);
        } 
    }         

    public function scheduleChange($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($str = $this->_checkMandatoryInput($payload, ['productId', 'schedule']))!= null) {
            return $response->withJson(array('message' => $str, 'result'=>null),422);
        }
        $productId = $payload['productId'];
        $schedule = $payload['schedule'];
        $sql1 = $this->_updateCourseSql($productId, $schedule);
        $sql2 = $this->_updateRegistrationSql($productId, $schedule);
        $this->logger->addDebug('Sql 1:' . $sql1);
        $this->logger->addDebug('Sql 2:' . $sql2);
        if (!$this->_sqlExecute($sql1)) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to change schedule in tbl_course'),200);
        } else if (!$this->_sqlExecute($sql2)) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to change schedule for tbl_registration'),200);
        } 
           
        return $response->withJson(array('status' => 'OK', 'message'=>'Successful change of schedule') ,200);
    }
   

    public function updatePhonebook($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        $sql = "INSERT IGNORE INTO `tbl_phonebook` (firstName, lastName, email, phone, latestRegTimestamp) SELECT firstName, lastName, email, IFNULL(phone,'Unknown'), creaTimestamp FROM `tbl_registration` order by creaTimestamp asc";
        if ($this->_sqlExecute($sql)) {
            $rowCount = $this->_getRowCount();
            return $response->withJson(array('status' => 'OK', 'message'=>'Phonebook updated with  new records'),200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to update phone book with new registrations'),200);
        } 
    }
};    



