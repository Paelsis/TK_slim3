<?php

namespace App\Controllers;

class TkFestivalController extends Controller
{
    public function packageDef($request, $response) 
    {
        try{
            $con = $this->db;
            $result = null;
            $sql = "SELECT * from tbl_package as p order by id";

            foreach ($con->query($sql) as $row) {
                unset($row['id']);
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



    protected function _getRegistrationMarathonSQL()
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
        ORDER BY id");
    }    

    
    protected function _getRegistrationMarathon($sql) 
    {    
        $this->logger->info('SQL-statement in Marathon:' . $sql);
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

    public function getRegistrationMarathon($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistration social');
        $sql=$this->_getRegistrationMarathonSQL();
        $regs = array();
        $regs = array_merge($regs, $this->_getRegistrationMarathon($sql)); 


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


    protected function _getRegistrationFestivalSQL()
    {
        return("SELECT
        A.*,
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
    ORDER BY
        A.id ASC");
    }    




    protected function UNUSED_getRegistrationFestivalByProductSQL()
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
        `A`.`firstName`
    DESC
        ");
    }              

    protected function _getRegistrationFestivalByProductSQL()
    {
        return ("SELECT A.*,
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
        order by A.email, A.productType, A.product");
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

    protected function _getRegistrationFestival($sql) 
    {    
        $this->logger->info('SQL-statement in _getReistrationFestival:' . $sql);
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

    public function getRegistrationFestival($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistrationFestival');
        $sql=$this->_getRegistrationFestivalSQL();
        $regs = array();
        $regs = $this->_getRegistrationFestival($sql); 
        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
        }
    }

    public function getRegistrationFestivalByProduct($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];
        } else {
            $language = 'SV';
        }
        $this->_setLcTimeNames($language);

        $this->logger->info('getRegistrationFestival');
        $sql=$this->_getRegistrationFestivalByProductSQL();
        $regs = array();
        $regs = $this->_getRegistrationFestival($sql); 

        if(($cnt = count($regs)) > 0){
            return $response->withJson(array('status' => 'true','result'=>$regs, 'count'=>$cnt) , 200);
        } else{
            return $response->withJson(array('status' => 'false', 'message'=>'No entries found in database', 'result:'=>null),200);
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
            'workshop' as productType, 
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
                // If registration type is defined, filter only the entries where part of scheduleId string matches registration type 
                if (!isset($registrationType) || (isset($registrationType) && (strpos($row['scheduleId'], $registrationType) !== false))) {
                    $result[] = $row;
                }
            }    
            if (count($result) > 0) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries found in database'),422);
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
            active
        )
        SELECT
            te.templateName,
            te.eventType,
            te.year,
            te.courseType,
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
            1 AS active
        FROM
            tbl_workshop_template te
        LEFT OUTER JOIN tbl_teacher t1 ON
            (t1.shortName = te.teacher1)
        LEFT OUTER JOIN tbl_teacher t2 ON
            (t2.shortName = te.teacher2)
        and te.templateName ='" . $templateName . "'";

        $this->logger->addDebug('_copyWorkshopTemplate, sql:' . $sql);

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
                        NAME,
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
                        NAME,
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

        $sql = "SELECT * from tbl_form_fields $whereClause order by id"; 
        return($sql);
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
                IF (now() > CAST(CONCAT(endDate, ' ', endTime) as datetime), 'FINISHED', 'OPEN')
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
        $con = $this->db;
        $sql = $this->_scheduleEventSQL($eventType, $language);    

        $result=array();
        foreach ($con->query($sql) as $row) {
            if ($row['status'] === 'OPEN') {
                $row['active']=TRUE;
            } else {
                $row['active']=FALSE;
            }
            $row['name']=$row['name' . $language];
            $result[] = $row;
        }
        if (sizeof($result)!==0) {
            return $response->withJson(array('status' => 'OK','result'=>isset($eventType)?$result[0]:$result),200);
        } else{
            return $response->withJson(array('status' => 'WARNING', 'result'=>'No entries found in database'),204);
        }
    }
}
