<?php

namespace App\Controllers;

const MAX_IMBALANCE = 3;
const MAX_PARTICIPANTS = 220;
const MAX_GENDER = 110;


const IMBALANCE_FOLLOWER = 1;
const IMBALANCE_LEADER = 2;
const CLOSED_LEADER = 4;
const CLOSED_FOLLOWER = 8;
const CLOSED_BOTH = 16;
const CLOSED_ALL = 32;

const AVA_STATUS_TEXT = [
    'AV'=>[
        'SV'=>'Plats tillgänglig för samtliga dansroller',
        'EN'=>'Space available for all roles.',
        'ES'=>'Space available for all roles.',
    ],
    'CC'=>[
        'SV'=>'Kursen är fullbokad',
        'EN'=>'Course if full, check with TK if there are cancellations',
        'ES'=>'Course if full, check with TK if there are cancellations',
    ],    
    'OF'=>[
        'SV'=>'Endast platser kvar för följare',
        'EN'=>'Only space left for for followers',
        'ES'=>'Only space left for for followers',
    ],
    'OL'=>[
        'SV'=>'Endast platser kvar för förare',
        'EN'=>'Only space left for for leaders',
        'ES'=>'Only space left for for leaders',
    ],
    'OB'=>[
        'SV'=>'Endast platser kvar för de som kan båda roller',
        'EN'=>'Only space left for for those who dance both roles',
        'ES'=>'Only space left for for those who dance both roles',
    ],
    'CF'=>[
        'SV'=>'Fullbokad för följare',
        'EN'=>'No space keft for followers',
        'ES'=>'No space left for followers',
    ],
    'CL'=>[
        'SV'=>'Fullbokad för förare',
        'EN'=>'No space left for leaders',
        'ES'=>'No space left for leaders',
    ],
    'CB'=>[
        'SV'=>'Fullbokad för dem som dansar båda roller',
        'EN'=>'No space left for dancers that dances both roles',
        'ES'=>'No space left for dancers that dances both roles',
    ],
    'IF'=>[
        'SV'=>'På grund av följaröverskott kan för närvarande endast förare, de med partner och de som dansar båda roller registrera.',
        'EN'=>'Due to surplus of followers, currently only couples and single leaders (or both roles) is allowed to register',
        'ES'=>'Due to surplus of followers, currently only couples and single leaders (or both roles) is allowed to register',
    ],
    'IL'=>[
        'SV'=>'På grund av föraröverskott kan för närvarande endast de med som restistrarar sig med partner och de som dansar båda roller registrera.',
        'EN'=>'Due to surplus of leaders, currently only couples and single leaders (or both roles) is allowed to register',
        'ES'=>'Due to surplus of leaders, currently only couples and single followers (or both roles) is allowed to register',
    ],
];    

class TkFestivalController extends Controller
{
    public function packageDef($request, $response) 
    {
        try{
            $con = $this->db;
            $result = null;
            $sql = "SELECT * from tbl_package as p order by sequenceNumber, id";

            foreach ($con->query($sql) as $row) {
                unset($row['id']);
                $row['wsCount']=isset($row['wsCount'])?$row['wsCount'] = (int) $row['wsCount']:0;
                $row['allMilongasIncluded']=(boolean) $row['allMilongasIncluded'];
                $row['allWorkshops']=(boolean) $row['allWorkshops'];
                $row['register']=(boolean) $row['register'];
                $row['sequenceNumber']=(int) $row['sequenceNumber'];
                $row['wsCount']=(int) $row['wsCount'];
                $row['priceSEK']=(int) $row['priceSEK'];
                $row['priceEUR']=(int) $row['priceEUR'];
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

    protected function _getMarathonNamesSQL()
    {
        return("select * from v_marathon_names");  
    }    




    protected function _getRegistrationMarathonSQL($weeks)
    {

        return("SELECT
        A.*, NULL AS emailPartnerAlert
        FROM
        tbl_registration_marathon A
        WHERE STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') >= DATE_SUB(NOW(), INTERVAL $weeks WEEK)
        ORDER BY A.firstName, A.lastName");
    }    

    protected function _getRegistrationMarathonSQL_NOT_WORKING($weeks)
    {

        return("SELECT
        A.*, 
        IF(
            B.email IS NULL and A.emailPartner IS NOT NULL,
            'MISSING', IF(
            B.email != A.emailPartner AND B.email is not null,
            B.email, NULL)
           ) AS emailPartnerAlert
        FROM
        tbl_registration_marathon A
        LEFT OUTER JOIN tbl_registration_marathon B ON 
        B.dateRange=A.dateRange AND 
            (B.email=A.emailPartner OR 
                (A.firstNamePartner = B.firstName and A.lastNamePartner =  B.lastName and A.firstName = B.firstNamePartner and A.lastName = B.lastNamePartner)
            )    
        WHERE STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') >= DATE_SUB(NOW(), INTERVAL $weeks WEEK)
        ORDER BY id");
    }    
    
    protected function _getCountersMarathonSQL()
    {
        return "SELECT eventType, dateRange, role, count(role) as cnt 
        FROM `tbl_registration_marathon` 
        group by eventType, dateRange, role";    
    }    


    public function getCountersMarathon($request, $response) 
    {
            $allGetVars = $request->getQueryParams();
    
            $sql=$this->_getCountersMarathonSQL();
            $this->logger->info("getCountersMarathon: SQL-statment; $sql");

            $rows = $this->_fetchRows($sql); 

            $result = array();
            foreach($rows as $row) {
                $result[$row['eventType']][$row['dateRange']][$row['role']] = (int) $row['cnt'];
            }    
            if($rows) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
    }

    protected function _getCountersFestivalSQL()
    {
        return "SELECT eventType, dateRange, role, count(role) as cnt 
        FROM `tbl_registration_festival` 
        group by eventType, dateRange, role";    
    }    



    public function getCountersFestival($request, $response) 
    {
            $allGetVars = $request->getQueryParams();
    
            $sql=$this->_getCountersFestivalSQL();
            $this->logger->info("getCountersMarathon: SQL-statment; $sql");

            $rows = $this->_fetchRows($sql); 

            $result = array();
            foreach($rows as $row) {
                $result[$row['eventType']][$row['dateRange']][$row['role']] = (int) $row['cnt'];
            }    
            if($rows) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
    }

    protected function _getCountersWorkshopSQL()
    {
        return "SELECT dateRange, product as workshopId, role, count(role) as cnt 
        FROM `tbl_registration_festival_product` 
        group by dateRange, workshopId, role";    
    }    


    public function getCountersWorkshop($request, $response) 
    {
            $allGetVars = $request->getQueryParams();
    
            $sql=$this->_getCountersWorkshopSQL();
            $this->logger->info("getCountersMarathon: SQL-statment; $sql");

            $rows = $this->_fetchRows($sql); 

            $result = array();
            foreach($rows as $row) {
                $result[$row['dateRange']][$row['workshopId']][$row['role']] = (int) $row['cnt'];
            }    
            if($rows) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
    }

    public function getRegistrationMarathon($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $language=isset($allGetVars['language'])?$allGetVars['language']:'SV';
        $weeks=isset($allGetVars['weeks'])?$allGetVars['weeks']:130;
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistration social');
        $sql=$this->_getRegistrationMarathonSQL($weeks);
        $regs = $this->_fetchRows($sql); 

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    public function getMarathonNames($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistration social');
        $sql=$this->_getMarathonNamesSQL();
        $regs = array();
        $regs = array_merge($regs, $this->_getRegistrationMarathon($sql)); 


        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }



    protected function _getRegistrationFestivalByProductSQL($weeks)
    {
        return ("SELECT A.*,
        STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') as startDate,
        C.role, C.orderId,
        IF(
            B.email IS NULL and A.emailPartner IS NOT NULL,
            'MISSING', IF(
            B.email != A.emailPartner AND B.email is not null,
            B.email, NULL)
        ) AS emailPartnerAlert
        from tbl_registration_festival_product as A
        left outer join tbl_registration_festival_product as B on A.dateRange = B.dateRange  and A.product=B.product and
            (
                A.emailPartner=B.email OR (A.firstNamePartner = B.firstName AND A.lastNamePartner = B.lastName AND A.firstName = B.firstNamePartner AND A.lastName = B.lastNamePartner)
            )
        left outer join (select distinct eventType, orderId, dateRange, email, role from tbl_registration_festival) as C on C.email = A.email and C.eventType = A.eventType and C.dateRange = A.dateRange   
        WHERE STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') >= DATE_SUB(NOW(), INTERVAL $weeks WEEK)
        order by startDate, A.productType ASC, A.product ASC, A.firstName ASC");

    }              

    /*
    create temporary table numbers as 
  select 1 as n
  union select 2 as n
  union select 3 as n
  union select 4 as n
  union select 5 as n
  union select 6 as n
  union select 7 as n
  union select 8 as n
  union select 9 as n
  union select 10 as n
  union select 11 as n
  union select 12 as n
  union select 13 as n
  union select 14 as n
  union select 15 as n
  union select 16 as n
  */

    protected function _getRegistrationFestivalSQL($weeks)
    {
        return("SELECT
        A.*,
        STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') as startDate,
        IF(
            C.email IS NULL AND A.emailPartner IS NOT NULL,
            'MISSING',
            IF(
                C.email != A.emailPartner AND C.email IS NOT NULL,
                C.email,
                NULL
            )
        ) AS emailPartnerAlert,
        B.productList AS productList,
        C.productList AS productListPartner
    FROM
        tbl_registration_festival A
    LEFT OUTER JOIN(
        SELECT
            GROUP_CONCAT(product) AS productList,
            email,
            dateRange
        FROM
            tbl_registration_festival_product
        GROUP BY
            email,
            dateRange
    ) AS B
    ON
        A.email = B.email AND A.dateRange = B.dateRange
    LEFT OUTER JOIN(
        SELECT
            GROUP_CONCAT(product) AS productList,
            email,
            dateRange,
            firstName,
            firstNamePartner,
            lastName,
            lastNamePartner
        FROM
            tbl_registration_festival_product
        GROUP BY
            email,
            dateRange
    ) AS C
    ON
        A.dateRange = C.dateRange AND(
            A.emailPartner = C.email OR(
                A.firstNamePartner = C.firstName AND A.lastNamePartner = C.lastName AND A.firstName = C.firstNamePartner AND A.lastName = C.lastNamePartner
            )
        )
    WHERE STR_TO_DATE(substr(A.dateRange, 1, 7), '%d%b%y') >= DATE_SUB(NOW(), INTERVAL $weeks WEEK)
    ORDER BY
        A.id ASC");
    }    


    public function getRegistrationFestival($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $language=isset($allGetVars['language'])?$allGetVars['language']:'SV';
        $weeks=isset($allGetVars['weeks'])?$allGetVars['weeks']:52;

        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistrationFestival');
        $sql=$this->_getRegistrationFestivalSQL($weeks);
        $regs = array();
        $regs = $this->_fetchRows($sql); 
        $cnt = count($regs);

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'OK','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    protected function UNUSED_getRegistrationFestivalByProductSQL($weeks)
    {
        return ("SELECT
        *
    FROM
        (
        SELECT
            tf.id,
            IF(
                LENGTH(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(tf.productList, ',', n),
                        ',',
                        -1
                    )
                ) > 0,
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(tf.productList, ',', n),
                    ',',
                    -1
                ),
                'NO PRODUCTS CHOOSEN'
            ) AS product,
            tf.handled,
            tf.firstName,
            tf.lastName,
            tf.email,
            tf.address,
            tf.phone,
            tf.food,
            tf.allergies,
            tf.eventType,
            tf.dateRange,
            tf.productList,
            tf.role,
            tf.firstNamePartner,
            tf.lastNamePartner,
            tf.workshopPartners,
            tf2.productList AS productListPartner
        FROM
            tbl_registration_festival AS tf
        LEFT OUTER JOIN tbl_registration_festival AS tf2
        ON
            tf.dateRange = tf2.daterange AND (
                tf2.email = tf.emailPartner OR(
                    tf.firstNamePartner = tf2.firstName AND tf.lastNamePartner = tf2.lastName AND tf.firstName = tf2.firstNamePartner AND tf.lastName = tf2.lastNamePartner
                )
            )
        JOIN numbers ON CHAR_LENGTH(tf.productList) - CHAR_LENGTH(
        REPLACE
            (tf.productList, ',', '')
        ) >= n - 1
    ) AS A
    ORDER BY
        `A`.`productType` DESC, `A`.`firstName` ASC 
    ");
    }              

    public function getRegistrationFestivalByProduct($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $language=isset($allGetVars['language'])?$allGetVars['language']:'SV';
        $week=isset($allGetVars['weeks'])?$allGetVars['weeks']:52;
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistrationFestival');
        $sql=$this->_getRegistrationFestivalByProductSQL($week);
        $regs = array();
        $regs = $this->_fetchRows($sql); 

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'OK','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }
    
    // courses are products to sell and must have a productType and a productId (other fields speicalized)
    public function scheduleWorkshop($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['registrationType'])) {
            $registrationType=$allGetVars['registrationType'];
        }

        $this->_setLcTimeNames('EN');

        $sql=$this->_getCountersWorkshopSQL();
        $rows = $this->_fetchRows($sql); 
        foreach($rows as $row) {
            $dateRange =$row['dateRange'];
            $workshopId = $row['workshopId'];
            $role = $row['role'];
            $counters[$dateRange][$workshopId][$role] = $row['cnt'];
        }    


        try{
            $con = $this->db;
            // Note that productType and productId is important for this SELECT since we are saving it in tbl_products
            $sql = "SELECT 
            A.*, SD.*, A.id,
            CONCAT(DATE_FORMAT(SD.startDate, '%d%b%y') ,'-', DATE_FORMAT(SD.endDate,'%d%b%y')) as `dateRange`, 
            IF (now() < CAST(CONCAT(SD.openRegDate, ' ', SD.openRegTime) as datetime), 'NOT_OPEN_YET', 
            IF (now() > CAST(CONCAT(SD.endDate, ' ', SD.endTime) as datetime), 'FINISHED', 'OPEN')
            ) as status, 
            TIME_FORMAT(A.startTime, '%H:%i') as startTime,
            DATE_FORMAT(A.startDate, '%a %e/%c-%y') as startDate,
            DATE_FORMAT(SD.startDate, '%a %e/%c-%y') startDateEvent,
            TIME_FORMAT(SD.startTime, '%H:%i') as startTimeEvent,
            DATE_FORMAT(SD.endDate, '%a %e/%c-%y') endDateEvent,
            TIME_FORMAT(SD.endTime, '%H:%i') as endTimeEvent,
            A.eventType as eventTypeSC,
            CONCAT(A.eventType, A.year, A.workshopId) as productId,
            DATEDIFF(A.startDate, CURDATE()) daysUntilStart, 
            S.address, S.siteName, S.city, S.urlLocation 
            FROM tbl_workshop A 
            left outer join tbl_site S on A.siteId=S.siteId
            left outer join tbl_schedule_def SD on SD.eventType = A.eventType   
            where A.active = 1 
            order by A.eventType, A.year, A.productId, S.city asc";



            $result=array();
            foreach ($con->query($sql) as $row) {
                if (isset($row['eventTypeSC'])) {
                   $row['eventType'] = $row['eventTypeSC'];
                   unset($row['eventTypeSC']);
                }    
                unset($row['email']);
                unset($row['nameSV']);
                unset($row['nameEN']);
                unset($row['nameES']);

                $row['minutes'] = (int) $row['minutes'];
                $row['wsCount'] =  (int) $row['wsCount'];

                $row['priceSEK'] = (int) $row['priceSEK'];
                $row['priceEUR'] = (int) $row['priceEUR'];
                $row['active'] = (int) $row['active'];

                // Add counters for LEADERS, FOLLOWERS, BOTH Marathon
                if (isset($counters[$row['dateRange']][$row['workshopId']])) {
                    $arr = $counters[$row['dateRange']][$row['workshopId']];
                    foreach ($arr as $key=>$value) {
                        $row[$key] = (int) $value;
                    }
                    $leader = isset($row['LEADER'])?$row['LEADER']:0;
                    $follower = isset($row['FOLLOWER'])?$row['FOLLOWER']:0;
                    $both = isset($row['BOTH'])?$row['BOTH']:0;
                    if ($leader > $follower) {
                        $row['overflowLeader'] =(int)$leader - (int) $follower; 
                    }
                    if ($leader < $follower) {
                        $row['overflowFollower'] =(int)$follower - (int) $leader; 
                    }  

                    $maxImbalance = isset($row['maxImbalance'])?(int) $row['maxImbalance']:500;
                    $maxParticipants = isset($row['maxParticipants'])?(int) $row['maxParticipants']:500;
                    $maxBoth = isset($row['maxBoth'])?(int) $row['maxBoth']:500;
                    $avaStatus = $this->_avaStatus($leader, $follower, $both, $maxImbalance, $maxBoth, $maxParticipants);
                    $row['LEADER'] = $leader;
                    $row['FOLLOWER'] = $follower;
                    $row['BOTH'] = $both;
                    $row['avaStatus'] = $avaStatus;
                    $row['avaStatusText'] = AVA_STATUS_TEXT[$avaStatus];
                } else {
                    $row['avaStatus'] = 'AV';
                    $row['avaStatusText'] = 'Open (No counters)';
                }
                $row['maxParticipants'] = (int) $row['maxParticipants'];
                $row['maxImbalance'] = (int) $row['maxImbalance'];
                $row['maxBoth'] = (int) $row['maxBoth'];



                // If registration type is defined, filter only the entries where part of scheduleId string matches registration type 
                if (!isset($registrationType) || (isset($registrationType) && (strpos($row['scheduleId'], $registrationType) !== false))) {
                    $result[] = $row;
                }
            }    
            if (count($result) > 0) {
                return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found in database', 'result' => null),422);
            }
        }
        catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
    }



    protected function _copyWorkshopTemplate($templateName) 
    {
        $sql = "INSERT INTO `tbl_workshop`(
            templateName,
            eventType,
            year,
            courseType,
            productType,
            workshopId,
            productId,
            name,
            teacher1,
            teacher2,
            teachersShort,
            teachers,
            siteId,
            startDate,
            startTime,
            dayOfWeek,
            priceSEK,
            priceEUR,
            wsCount,
            `minutes`,
            active
        )
        SELECT
            te.templateName,
            te.eventType,
            te.year,
            te.courseType,
            te.productType,
            te.workshopId,
            CONCAT(
                DATE_FORMAT(te.startDate, '%y%m%d'),
                TIME_FORMAT(te.startTime, '%H%i'),
                te.siteId,
                te.workshopId
            ),
            te.name,
            te.teacher1,
            te.teacher2,
            CONCAT(
                IFNULL(te.teacher1, ''),
                IF(
                    te.teacher1 IS NULL OR te.teacher2 IS NULL,
                    '',
                    ' & '
                ),
                IFNULL(te.teacher2, '')
            ),
            CONCAT(
                IFNULL(t1.firstName, ''),
                IF(
                    te.teacher1 IS NULL OR te.teacher2 IS NULL,
                    '',
                    ' & '
                ),
                IFNULL(t2.firstName, '')
            ),
            te.siteId,
            te.startDate,
            te.startTime,
            WEEKDAY(te.startDate) + 1 AS dayOfWeek,
            priceSEK,
            priceEUR,
            wsCount,
            `minutes`,
            1 AS active
        FROM
            tbl_workshop_template te
        LEFT OUTER JOIN tbl_teacher t1 ON
            (t1.shortName = te.teacher1)
        LEFT OUTER JOIN tbl_teacher t2 ON
            (t2.shortName = te.teacher2)
        WHERE te.templateName = '" . $templateName . "'";

        $this->logger->addDebug('_copyWorkshopTemplate, sql:' . $sql);
        if (!isset($templateName)) {
            return false;
        }    
        if ($this->_sqlExecute($sql)) {
            return true;
        } else {
            return false;
        }
    }

    protected function _copyPackageTemplate($templateName) 
    {
        $this->logger->addDebug('scheduleId = ' . $templateName);
        $sql = "REPLACE
                    INTO tbl_package(
                        templateName,
                        eventType,
                        `year`,
                        packageId,
                        productId,
                        `name`,
                        `productType`,
                        `allWorkshops`,
                        `wsCount`,
                        `minutes`,
                        priceSEK,
                        priceEUR,
                        sequenceNumber,
                        register
                    )
                    SELECT
                        templateName,
                        eventType,
                        `year`,
                        packageId,
                        CONCAT(SUBSTR(eventType, 1, 3), 'PKG', '_', `year`, '_', packageId),
                        `name`,
                        `productType`,
                        `allWorkshops`,
                        `wsCount`,
                        `minutes`,
                        priceSEK,
                        priceEUR,
                        sequenceNumber,
                        register
                    FROM
                        tbl_package_template
                    where templateName ='" . $templateName . "'";

        $this->logger->addDebug('_copyPackageTemplate, sql:' . $sql);

        if ($this->_sqlExecute($sql)) {
            return true;
        } else {
            return false;
        }
    }

    public function copyPackageTemplate($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (!isset($payload['templateName'])) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'templateName is not defined in payload'),422);
        }    
        $templateName=$payload['templateName'];

        $con = $this->db;
        if ($this->_copyPackageTemplate($templateName)) {
            return $response->withJson(array('status' => 'OK','message'=>'Copy of tables successful') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }
    




    public function copyWorkshopTemplate($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (!isset($payload['templateName'])) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'templateName is not defined in payload'),422);
        }    
        $templateName=$payload['templateName'];

        $con = $this->db;
        if ($this->_copyWorkshopTemplate($templateName)) {
            return $response->withJson(array('status' => 'OK','message'=>'Copy of tables successful') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    protected function _getMarathonRangeSql() {
        $sql = "select * from v_ref_marathon"; 
        return($sql);
    }

    public function getMarathonRange($request, $response) {
        $con = $this->db;
        $sql = $this->_getMarathonRangeSql();    

        $result=array();
        foreach ($con->query($sql) as $row) {
            $result[] = $row;
        }
        if ($result !== null) {
            return $response->withJson(array('status' => 'OK','result'=>$result),200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),204);
        }
    }

    protected function _partnerMissRegistration() {
        $sql = "SELECT
                A.dateRange,
                A.firstNamePartner,
                A.lastNamePartner,
                A.emailPartner
                FROM
                    `tbl_registration_marathon` A
                WHERE emailPartner NOT IN(SELECT  email FROM tbl_registration_marathon B WHERE B.dateRange = A.dateRange)";
                return($sql);
    }

    public function partnerMissRegistration($request, $response) {
        $con = $this->db;
        $sql = $this->_partnerMissRegistration();    

        $result=array();
        foreach ($con->query($sql) as $row) {
            $result[] = $row;
        }
        if ($result !== null) {
            return $response->withJson(array('status' => 'OK','result'=>$result),200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),204);
        }
    }

    protected function _formFieldsSQL($formName) {
        $whereClause="";
        if (isset($formName)) {
            $whereClause=" where formName='$formName'";
        }    

        $sql = "SELECT * from tbl_form_fields $whereClause order by formName, sequenceNumber"; 
        return($sql);
    }

    protected function _avaStatus($leader, $follower, $both, $maxImbalance, $maxBoth, $maxParticipants) {
        $maxPerGender = (int) ((int) $maxParticipants + (int) $maxImbalance  + 1)/ 2;

        $closed = 0;

        if ((int) $leader + (int) $follower + (int) $both >= (int) $maxParticipants) {
            $closed = $closed | CLOSED_ALL;
        } 
       
        if ($leader >= (int) $maxPerGender) {
            $closed = $closed | CLOSED_LEADER;
        } else if ((int)$leader >= (int) $follower + (int) $maxImbalance) {
            $closed = $closed | IMBALANCE_LEADER;
        } 

        if ($follower >= (int) $maxPerGender) {
            $closed = $closed | CLOSED_FOLLOWER;
        } else if ((int)$follower >= (int) $leader + (int) $maxImbalance) {
            $closed = $closed | IMBALANCE_FOLLOWER;
        } 

        if ($both >= (int) $maxBoth) {
            $closed = $closed | CLOSED_BOTH;
        } 


        if ($closed & CLOSED_ALL) {
            return 'CC'; // Completely closed
        } else if ($closed & CLOSED_LEADER && $closed && CLOSED_BOTH) {
            return 'OF'; // Only followers
        } else if ($closed & CLOSED_FOLLOWER && $closed & CLOSED_BOTH) {
            return 'OL'; // Only leaders
        } else if ($closed & CLOSED_LEADER && $closed & CLOSED_FOLLOWER) {
            return 'OB'; // Only both (probably not occur)
        } else if ($closed & CLOSED_FOLLOWER) {
            return 'CF'; // Closede only for followers
        } else if ($closed & CLOSED_LEADER) {
            return 'CL'; // Closed only for leaders
        }  else if ($closed & CLOSED_BOTH) {
            return 'CB'; // Closed only for both
        } else if ($closed & IMBALANCE_LEADER) {
            return 'IL'; // Imbalance for leaders 
        } else if ($closed & IMBALANCE_FOLLOWER) {
            return 'IF'; // Imbalance for followers
        } else {
            return 'AV';
        }    
    }    

    public function formFields($request, $response) {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['formName'])) {
            $formName=$allGetVars['formName'];
        } else {
            $formName=null;
        }
        $con = $this->db;
        $sql = $this->_formFieldsSQL($formName);    

        $result=array();

        foreach ($con->query($sql) as $row) {
            if ($row['active'] == 1) {
                $nullColumns = array('required');
                foreach($nullColumns as $col) {
                    if ($row[$col]==1) {
                        $row[$col]=true;
                    } else {
                        $row[$col]=false;
                    }    
                }
                unset($row['active']);
                $result[] = $row;
            }    
        }
        if (sizeof($result)!==0) {
            return $response->withJson(array('status' => 'OK','result'=>isset($eventType)?$result[0]:$result),200);
        } else{
            return $response->withJson(array('status' => 'WARNING', 'result'=>'No entries found in database'),204);
        }
    }


    protected function _scheduleEventSQL($eventType, $language) {
        $whereClause="";
        if (isset($eventType)) {
            $whereClause=" where sd.eventType='$eventType'";
        }    

        $sql = "SELECT sd.*, 
            CONCAT(DATE_FORMAT(sd.startDate, '%d%b%y'),'-', 
            DATE_FORMAT(endDate,'%d%b%y')) as `dateRange`,
            IF (now() < CAST(CONCAT(openRegDate, ' ', openRegTime) as datetime), 'NOT_OPEN_YET', 
                IF (now() > CAST(CONCAT(endDate, ' ', endTime) as datetime), 'FINISHED', 
                    IF (closed > 0, 'CLOSED', 'OPEN')
                ) 
            ) as status, 
            DATE_FORMAT(startDate, '%Y') as year
            FROM `tbl_schedule_def` as sd
            $whereClause order by sd.startDate"; 
        return($sql);
    }

    public function scheduleEvent($request, $response) {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['eventType'])) {
            $eventType=$allGetVars['eventType'];
        } else {
            $eventType=null;
        }
        if (isset($allGetVars['language'])) {
            $language=$allGetVars['language'];
        } else {
            $language='EN';
        }

        // Get counters for marathon and festivals
        $counters = array();

        $sql=$this->_getCountersMarathonSQL();
        $rows = $this->_fetchRows($sql); 
        foreach($rows as $row) {
            $counters[$row['eventType']][$row['dateRange']][$row['role']] = $row['cnt'];
        }    
        $sql=$this->_getCountersFestivalSQL();
        $rows = $this->_fetchRows($sql); 
        foreach($rows as $row) {
            $counters[$row['eventType']][$row['dateRange']][$row['role']] = $row['cnt'];
        }    
        
        // Get schedule event 
        $con = $this->db;
        $sql = $this->_scheduleEventSQL($eventType, $language);    

        $result=array();
        foreach ($con->query($sql) as $row) {
            if ($row['status'] === 'OPEN' || $row['status'] === 'CLOSED') {
                $row['active']=true;
            } else {
                $row['active']=false;
            }
            if ($row['menu1'] === '1') {
                $row['menu1']=true;
            } else {
                $row['menu1']=false;
            }
            if ($row['menu2'] === '1') {
                $row['menu2']=true;
            } else {
                $row['menu2']=false;
            }
            if ($row['includePaymentInfo'] === '1') {
                $row['includePaymentInfo']=true;
            } else {
                $row['includePaymentInfo']=false;
            }
            if ($row['closed'] === '1') {
                $row['closed']=true;
            } else {
                $row['closed']=false;
            }
            $row['name']=$row['name' . $language];

            // Add counters for LEADERS, FOLLOWERS, BOTH Marathon
            if (isset($counters[$row['eventType']][$row['dateRange']])) {
                $arr = $counters[$row['eventType']][$row['dateRange']];
                foreach ($arr as $key=>$value) {
                    $row[$key] = (int) $value;
                }
                $leader = isset($row['LEADER'])?(int) $row['LEADER']:0;
                $follower = isset($row['FOLLOWER'])?(int) $row['FOLLOWER']:0;
                $both = isset($row['BOTH'])?(int) $row['BOTH']:0;
                if ($leader > $follower) {
                    $row['overflowLeader'] =(int)$leader - (int) $follower; 
                }
                if ($leader < $follower) {
                    $row['overflowFollower'] =(int)$follower - (int) $leader; 
                }  

                $maxImbalance = isset($row['maxImbalance'])?(int) $row['maxImbalance']:500;
                $maxParticipants = isset($row['maxParticipants'])?(int) $row['maxParticipants']:500;
                $maxBoth = isset($row['maxBoth'])?(int) $row['maxBoth']:500;
                $avaStatus = $this->_avaStatus($leader, $follower, $both, $maxImbalance, $maxBoth, $maxParticipants);
                $row['amount'] = (int) $row['amount'];
                $row['avaStatus'] = $avaStatus;
                $row['avaStatusText'] = AVA_STATUS_TEXT[$avaStatus];
            } else {
                $row['avaStatus'] = 'AV';
                $row['avaStatusText'] = 'Open';
            }
            $row['maxParticipants'] = (int) $row['maxParticipants'];
            $row['maxImbalance'] = (int) $row['maxImbalance'];
            $row['maxBoth'] = (int) $row['maxBoth'];
            $result[] = $row;
        }
        
        if (sizeof($result)!==0) {
            return $response->withJson(array('status' => 'OK','result'=>isset($eventType)?$result[0]:$result),200);
        } else{
            return $response->withJson(array('status' => 'WARNING', 'result'=>'No entries found in database'),204);
        }
    }

    protected function _sendMailFestivalConfirmation($reg) 
    {
        $arr = array('email', 'firstName', 'emailResponsible', 'year', 'eventType');
        foreach ($arr as $key => $value) {
            if (!isset($reg[$value])) {
                $this->logger->error("ERROR in _sendMailFestivalConfirmation: Failed to find key $value");
                return false;
            }   
        }


        $orderId = $reg['orderId'];
        $email=$reg['email'];
        $emailResponsible=$reg['emailResponsible'];
        $firstName = $reg['firstName'];
        $language='EN';
        $year = $reg['year'];
        $eventType = $reg['eventType'];
        $amount = isset($reg['amount'])?$reg['amount']:0;
        if (isset($reg['imageUrl'])) {
            $imageUrl = $reg['imageUrl'];
            unset($reg['imageUrl']);
        }
        $color = $reg['color'];
        $title=$eventType;
        $tableName = 'tbl_registration_festival';


        $baseUrl = $this->container['settings']['tk']['url'];

        $token = isset($reg['token'])?$reg['token']:null;
        $cancellationLink = isset($token)?"$baseUrl/cancelRegistration/$token/$tableName":null;
        $clickableUrl = isset($token)?$this->_clickableUrl($cancellationLink, "Cancel Registration"):null;
        

        $subject = $this->_getHtmlFromTblText($eventType, 'MAILSUBJECT', $language);


        $body ="<div style='color:$color;'>";
        $body .="<h1>Registration for $title year $year</h1>"; 
        $body .= $this->_getHtmlFromTblText($eventType, 'MAILBODY', $language);
        $body .= "<h3>Details of your registration</h3>";
        //$body .= $this->_getHtmlFromTblText('MARATHON', 'MAILBODY', $language);

        # We do not want the following in the reply mail
        unset($reg['eventType']);
        unset($reg['dateRange']);
        unset($reg['havePartner']);
        //unset($reg['firstNamePartner']);
        //unset($reg['lastNamePartner']);
        unset($reg['emailPartner']);
        unset($reg['phonePartner']);
        unset($reg['addressPartner']);
        unset($reg['token']);
        
        $body .= $this->_build_html_from_object($reg, false);
        $body .=isset($reg['extendedProductList'])?$this->_build_html_from_product_array($reg['extendedProductList']):null;
        $body .=isset($clickableUrl)?'<p/><p/>' . TOKEN[$language] . ' : ' . $clickableUrl . '<p/><p/>':'';
        $body .='<p/>';
        $body .= $this->_getHtmlFromTblText($eventType, 'LEGAL', $language);

        $body .= isset($imageUrl)?"<img src='$imageUrl' width='50%' alt='Image with url $imageUrl not found' /><p/>":null;

        $body .= (($reg['includePaymentInfo']===true) && isset($reg['amount']))?$this->_paymentInfo($reg['amount'], $language):null;

        $body .= "<h3>" . isset(THANK_YOU[$eventType])?THANK_YOU[$eventType]:THANK_YOU['DEFAULT'] . "</h3>";
        $body .= "</div>";

        $returnArray = array();

        if ($this->_sendMail($subject, $body, $email)) {         
            $this->logger->addDebug('Successful sending mail to ' . $email);
            $this->logger->addDebug('Body ' . $body);
            $subjectResponsible = "Registration $eventType $year OK";
            if ($this->_sendMail($subjectResponsible, $body, $emailResponsible)) {         
                $this->logger->addDebug('Successful sending of mail to ' . $emailResponsible);
                $this->logger->addDebug('Body ' . $body);
                $returnArray['mailStatus'] = 'OK';
                $returnArray['mailSubject'] = $subjectResponsible;
                $returnArray['mailBody'] = $body;
            } else{
                $this->logger->warning('Failed mail sending to ' . $email);
                $returnArray['mailStatus'] = 'OK';
                $returnArray['mailSubject'] = $subjectResponsible;
                $returnArray['mailBody'] = $body;
            }
        } else{
            $this->logger->warning('Failed sending mail to ' . $email);
            $subjectResponsible = "PROBLEM: Failed to send mail for registration $eventType $year";
            $returnArray['mailStatus'] = 'ERROR';
            $returnArray['mailSubject'] = $subjectResponsible;
            $returnArray['mailBody'] = $body;
        }
        return $returnArray;
    }
   
    protected function _sendMailMarathonConfirmation($reg) 
    {
        $arr = array('email', 'firstName', 'emailResponsible', 'year', 'eventType');
        foreach ($arr as $key => $value) {
            if (!isset($reg[$value])) {
                $this->logger->error("ERROR in _sendMailFestivalConfirmation: Failed to find key $value");
                return false;
            }   
        }


        $orderId = $reg['orderId'];
        $email=$reg['email'];
        $emailResponsible=$reg['emailResponsible'];
        $firstName = $reg['firstName'];
        $language='EN';
        $year = $reg['year'];
        $eventType = $reg['eventType'];
        $amount = isset($reg['amount'])?$reg['amount']:0;
        $color = $reg['color'];

        if (isset($reg['imageUrl'])) {
            $imageUrl = $reg['imageUrl'];
            unset($reg['imageUrl']);
        }
        if ($eventType === 'MARATHON') {
            $title='Malmö Tangomaraton';
            $tableName = 'tbl_registration_marathon';
        } else {
            $title=$eventType;
            $tableName = 'tbl_registration_festival';
        }

        $baseUrl = $this->container['settings']['tk']['url'];

        $token = isset($reg['token'])?$reg['token']:null;
        $cancellationLink = isset($token)?"$baseUrl/cancelRegistration/$token/$tableName":null;
        $clickableUrl = isset($token)?$this->_clickableUrl($cancellationLink, "Cancel Registration"):null;
        
        $subject = $this->_getHtmlFromTblText($eventType, 'MAILSUBJECT', $language);

        $body ="<div style='color:$color;'>";
        $body .="<h1>Registration for $title year $year</h1>"; 
        $body .= $this->_getHtmlFromTblText($eventType, 'MAILBODY', $language);
        $body .= "<h3>Details of your registration</h3>";
        //$body .= $this->_getHtmlFromTblText('MARATHON', 'MAILBODY', $language);

        # We do not want the following data of the reg object in the reply mail
        unset($reg['eventType']);
        unset($reg['dateRange']);
        unset($reg['havePartner']);
        //unset($reg['firstNamePartner']);
        //unset($reg['lastNamePartner']);
        unset($reg['emailPartner']);
        unset($reg['phonePartner']);
        unset($reg['addressPartner']);
        unset($reg['token']);
        unset($reg['color']);
        unset($reg['token']);
        if ($reg['includePaymentInfo']===false) {
            unset($reg['includePaymentInfo']);
        }
        unset($reg['amount']);
        
        $body .= $this->_build_html_from_object($reg, false);
        $body .=isset($reg['extendedProductList'])?$this->_build_html_from_product_array($reg['extendedProductList']):null;
        $body .=isset($clickableUrl)?'<p/><p/>' . TOKEN[$language] . ' : ' . $clickableUrl . '<p/><p/>':'';
        $body .='<p/>';
        $body .= $this->_getHtmlFromTblText($eventType, 'LEGAL', $language);

        $body .= isset($imageUrl)?"<img src='$imageUrl' width='50%' alt='Image with url $imageUrl not found' /><p/>":null;

        $body .= (isset($reg['includePaymentInfo']) && isset($reg['amount']))?$this->_paymentInfo($reg['amount'], $language):null;

        $body .= "<h3>" . isset(THANK_YOU[$eventType])?THANK_YOU[$eventType]:THANK_YOU['DEFAULT'] . "</h3>";
        $body .= "</div>";

        $returnArray = array();

        if ($this->_sendMail($subject, $body, $email)) {         
            $this->logger->addDebug('Successful sending mail to ' . $email);
            $this->logger->addDebug('Body ' . $body);
            $subjectResponsible = "Registration $eventType $year OK";
            if ($this->_sendMail($subjectResponsible, $body, $emailResponsible)) {         
                $this->logger->addDebug('Successful sending of mail to ' . $emailResponsible);
                $this->logger->addDebug('Body ' . $body);
                $returnArray['mailStatus'] = 'OK';
                $returnArray['mailSubject'] = $subjectResponsible;
                $returnArray['mailBody'] = $body;
            } else{
                $this->logger->warning('Failed mail sending to ' . $email);
                $returnArray['mailStatus'] = 'OK';
                $returnArray['mailSubject'] = $subjectResponsible;
                $returnArray['mailBody'] = $body;
            }
        } else{
            $this->logger->warning('Failed sending mail to ' . $email);
            $subjectResponsible = "PROBLEM: Failed to send mail for registration $eventType $year";
            $returnArray['mailStatus'] = 'ERROR';
            $returnArray['mailSubject'] = $subjectResponsible;
            $returnArray['mailBody'] = $body;
        }
        return $returnArray;
    }
   


    // Post the registration only
    public function createRegistrationMarathon($request, $response) 
    {
        $reg = $request->getParsedBody();
        if (($ret=$this->_checkMandatoryInput($reg, ['firstName', 'lastName', 'email', 'dateRange', 'emailResponsible', 'year', 'eventType'])) !== null) {
            return $response->withJson(array('status'=>'ERROR',
            'message'=>"Failed to make registration for $firstName $lastName $email $emailResponsible to marathon due to missing $ret"), 204);
        }

        $firstName = $reg['firstName'];
        $lastName = $reg['lastName'];
        $email = $reg['email'];

        $orderId = $this->_createOrderId($reg);
        $reg['orderId'] = $orderId;

        // Set token
        $prefix = isset($reg['dateRange'])?$reg['dateRange']:'';
        $prefix .= isset($reg['email'])?$reg['email']:'';
        $token = uniqid($prefix, false);
        $reg['token'] = $token;
       

        // Insert order into tbl_order and get in return orderId
        if ($this->_insertEventReg(TBL_REGISTRATION_MARATHON, $reg)) {
            $mailReturnValue = $this->_sendMailMarathonConfirmation($reg);
            return $response->withJson(array('status'=>'OK', 
                    'orderId'=>$orderId,
                    'mailStatus'=>$mailReturnValue['mailStatus'],
                    'mailSubject'=>$mailReturnValue['mailSubject'],
                    'mailBody'=>$mailReturnValue['mailBody'],
                    'message'=>"Successful registration of marathon for  $firstName $lastName $email"), 200);
        } else {    
            $this->logger->error('createRegistrationMarathon: FAILED');
            $subject='Registration to MARATHON FAILED';
            $body="<p>Please contact tangokompaniet and make registration manually</p>";    
            $mailReturnValue=$this->_sendMail($subject, $body, $email);         

            return $response->withJson(array(
                'status'=>'ERROR',
                'orderId'=> $orderId, 
                'message'=>"Failed to make registration for $firstName $lastName $email to marathon"), 204);
        } 
    }

    protected function _insertExtendedProductList($table, $extendedProductList, $token) 
    {
        $returnValue = false;

        foreach ($extendedProductList as $product) {
            $productAdjusted = array();

            foreach ($product as $key=>$value) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $productAdjusted[$key] = strtolower($value);
                } else {   
                    $productAdjusted[$key] = $value;
                }
            } 
            $productAdjusted['token'] = $token;
            unset($productAdjusted['year']);
    
            /* Insert or concat a registration */
            if (!$this->_replaceRow($table, $productAdjusted)) {
                $this->logger->error('Failed go insert or concat into ' . $table);
                return false;
            }    
        }    
        return(true);
    }    

    protected function _insertEventReg($table, $reg) 
    {
        $returnValue = false;

        $regReduced = array();
        foreach ($reg as $key=>$value) {
            if (!is_array($value)) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $regReduced[$key] = strtolower($value);
                } else {   
                    $regReduced[$key] = $value;
                }
            }
        } 
        if (isset($regReduced['havePartner'])) {
            unset($regReduced['havePartner']);
        }
        if (isset($regReduced['emailResponsible'])) {
            unset($regReduced['emailResponsible']);
        }
        if (isset($regReduced['year'])) {
            unset($regReduced['year']);
        }
        if (isset($regReduced['imageUrl'])) {
            unset($regReduced['imageUrl']);
        }
        if (isset($regReduced['newsletter'])) {
            unset($regReduced['newsletter']);
        }
        
        /* Insert or concat a registration */
        if (!$this->_replaceRow($table, $regReduced)) {
            $this->logger->error('Failed go insert or concat into ' . $table);
            return false;
        }    
        // Insert into phonebook
        $colsPhonebook=['firstName', 'lastName', 'email', 'phone', 'newsletter'];
        $phonebook=$this->_intersectionOfKeys($reg, $colsPhonebook);
        $updKeys = ['phone','newsletter'];
        $this->logger->addDebug('before _insertOrUpdateRowInTable:');

        if (!$this->_insertOrUpdateRowInTable(TBL_PHONEBOOK, $phonebook, $updKeys)) {
            $this->logger->error('Failed go insert registrant into ' . TBL_PHONEBOOK);
            return false;
        }    

        /* If partner is filled in, then register partner as well */
        if (isset($reg['firstNamePartner']) && isset($reg['lastNamePartner'])) {
            $regPartner = $this->_createRegPartner($regReduced);
    
            // Insert partner into phonebook
            $this->logger->addDebug('_insertSingleReg: before _insertOrIgnoreRowsIntoTable');
            $colsPhonebook=['firstName', 'lastName', 'email', 'phone'];
            $phonebook=$this->_intersectionOfKeys($regPartner, $colsPhonebook);
            if (!$this->_insertOrIgnoreRowIntoTable(TBL_PHONEBOOK, $phonebook)) {
                $this->logger->error('Failed go insert partner into ' . TBL_PHONEBOOK);
                return false;
            }

        } 
        $this->logger->addDebug('END _insertEventReg:');
        return true;
    }    

    // Post the registration only
    public function createRegistrationFestival($request, $response) 
    {
        $reg = $request->getParsedBody();
        if (($ret=$this->_checkMandatoryInput($reg, ['firstName', 'lastName', 'email', 'emailResponsible', 'dateRange', 'year', 'eventType'])) !== null) {
            return $response->withJson(array('status'=>'ERROR',
            'message'=>"Failed to make registration for $firstName $lastName $email to marathon due to missing $ret"), 204);
        }
        $orderId = $this->_createOrderId($reg);
        $reg['orderId'] = $orderId;
        $firstName = $reg['firstName'];
        $lastName = $reg['lastName'];
        $email = $reg['email'];
        $eventType = $reg['eventType'];

        // Set token
        $prefix = isset($reg['dateRange'])?$reg['dateRange']:'';
        $prefix .= isset($reg['email'])?$reg['email']:'';
        $token = uniqid($prefix, false);
        $reg['token'] = $token;


        $this->logger->addDebug('createRegistrationFestival: before _insertExtendedProductList');

        if (!$this->_insertExtendedProductList(TBL_REGISTRATION_FESTIVAL_PRODUCT, $reg['extendedProductList'], $token)) {
            $this->logger->error('createRegistrationFestival: FAILED');
            return $response->withJson(array(
                'status'=>'ERROR',
                'orderId'=> $orderId, 
                'message'=>"Failed to insert products to" . TBL_REGISTRATION_FESTIVAL_PRODUCT . " for $firstName $lastName $email to marathon"), 204);
        }
        
        // Insert order into tbl_order and get in return orderId
        if ($this->_insertEventReg(TBL_REGISTRATION_FESTIVAL, $reg)) {
            $mailReturnValue = $this->_sendMailFestivalConfirmation($reg);
            return $response->withJson(array(
                'status'=>'OK', 
                'orderId'=> $orderId, 
                'mailStatus'=>$mailReturnValue['mailStatus'],
                'mailSubject'=>$mailReturnValue['mailSubject'],
                'mailBody'=>$mailReturnValue['mailBody'],
                'message'=>"Successful registration"), 200);
        } else {    
            $this->logger->error('createRegistrationFestival: FAILED');
            $subject="WARNING: Registration to $eventType FAILED";
            $body="<p>Please contact tangokompaniet and make registration manually</p>";    
            if ($this->_sendMail($subject, $body, $email)) {         
                $this->logger->addDebug('Successful mail sending to ' . $email);
                $this->logger->addDebug('Body ' . $body);
                $mailStatus = 'OK';
            } else{
                $mailStatus = 'ERROR';
                $this->logger->warning('Failed sending mail to ' . $email);
            }
            return $response->withJson(array(
                'status'=>'ERROR',
                'orderId'=> $orderId, 
                'message'=>"Failed to make registration on $eventType for $firstName $lastName $email"), 200);
        } 
    }
}
