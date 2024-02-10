<?php

namespace App\Controllers;

const TEST_FORM = array('formId'=>12, 'adam'=>12, 'bertil'=>15);

const ORDER_TABLE = 'tbl_order';
const REG_PRODUCT_TABLE = 'tbl_order_product';
    
const JSON_STRING = 'jsonString';    
const MANDATORY_KEYS = ['scheduleId', 'packageId', 'firstName', 'lastName', 'email', 'phone'];
const EMAIL_ORDER="order@tangokompaniet.com";

class TkFormController extends Controller
{
    function anything_to_utf8($var,$deep=TRUE){
        if(is_array($var)){
            foreach($var as $key => $value){
                if($deep) {
                    $var[$key] = anything_to_utf8($value,$deep);
                }elseif(!is_array($value) && !is_object($value) && !mb_detect_encoding($value,'utf-8',true)){
                     $var[$key] = utf8_encode($var);
                }
            }
            return $var;
        } elseif(is_object($var)){
            foreach($var as $key => $value){
                if($deep){
                    $var->$key = anything_to_utf8($value,$deep);
                } elseif(!is_array($value) && !is_object($value) && !mb_detect_encoding($value,'utf-8',true)){
                     $var->$key = utf8_encode($var);
                }
            }
            return $var;
        }else{
            return (!mb_detect_encoding($var,'utf-8',true))?utf8_encode($var):$var;
        }
    }

    protected function _insertRegIntoOrder($reg) 
    {
        $table='tbl_order';
        $reducedParams = $this->_objectReduceToKeys($reg, ['firstName', 'lastName', 'email', 'phone']); 
        if (isset($reg['message'])) {
            $reducedParams['message'] = $reg['message'];
        }    
        if (isset($reg['campaignCode'])) {
            $reducedParams['campaignCode'] = $reg['campaignCode'];
        }    

        $sql = $this->_build_sql_insert($table, $reducedParams);
        if ($this->_sqlInsert($sql)) {
            $con = $this->db;
            return $con->lastInsertId();
        } else {
            return -222;
        }
        return -888;
    }


    protected function _insertForm($table, $input, $mandatoryKeys, $jsonString) 
    {
        $reducedForm = $this->_objectReduceToKeys($input, $mandatoryKeys); 
        $reducedForm += [JSON_STRING=>$jsonString];
        $sql = $this->_build_sql_insert($table, $reducedForm);
        
        $this->logger->addDebug('sql' . $sql);
        //mysql_set_charset("utf8");
        if ($this->_sqlInsert($sql)) {
            $con = $this->db;
            return $con->lastInsertId();
        } else {
            return null;
        }
    }

    protected function _insertProducts($table, $productList) 
    {
        if ($this->_replaceRowsInTable($table, $productList)) {
            return true;
        } else {
            return false;
        }
    }

 
    private function _expandArray($obj) {
        $arr = array();
        foreach ($obj as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $akey => $avalue) {
                    $obj[$key] = $avalue;
                    $arr[] = $obj;
                }
            }    
        }
        return($arr);
    }


    public function ShowForm($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        if (($str = $this->_checkMandatoryInput($allGetVars, ['formId']))!= null) {
            return $response->withJson(array('message' => $str, 'result'=>null),422);
        }

        foreach($allGetVars as $key=>$value) {
            $this->logger->addDebug('[' . $key . '] = [' . $value . ']');
        }

        $this->logger->addDebug('Successful get post:' . $allGetVars['formId']);
        return $response->withJson(array('status'=>'OK', 'message' => 'Form shown successfully', 'TEST_FORM'=>TEST_FORM) ,200);
    }

    public function GetForm($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $orderTable = ORDER_TABLE;
        $regProductTable = REG_PRODUCT_TABLE;
        if (isset($allGetVars['idByProduct'])) {
            $idByProduct = 'P.id as id,';  
        } else {
            $idByProduct = '';  
        } 
        if (isset($allGetVars['language'])) {
            $language = $allGetVars['language'];  
        } else {
            $language = 'EN';  
        } 

        $sql = "SELECT 
        IFNULL(O.eventType, 'NO EVENT TYPE') as eventType,
        CU.firstName, CU.lastName, CU.email, CU.phone, CU.role,
        DP.firstNamePartner, DP.lastNamePartner, DP.emailPartner, DP.phonePartner,
        PA.name$language as packageName,
        PA.priceSEK, 
        PA.priceEUR,          
        SD.nameSV as scheduleNameSV,
        SD.nameEN as scheduleNameEN,
        SD.nameES as scheduleNameES,
        SD.name$language as scheduleName,
        WS.nameEN as workshopName, WS.scheduleId, WS.startDate, DAYNAME(WS.startDate) as dayName, TIME_FORMAT(WS.startTime, '%H:%i') as startTime, WS.teachers, WS.teachersShort, 
        PR.productId, PR.productType, 
        SI.siteName, SI.city, SI.urlLocation 
       from tbl_order O
       left outer join tbl_order_customer CU on CU.orderId = O.orderId
       left outer join tbl_order_dance_partner DP on DP.orderId = O.orderId
       left outer join tbl_order_product PR on PR.orderId = O.orderId
       left outer join tbl_order_package OP on OP.orderId = O.orderId
       left outer join tbl_package as PA on PA.packageId = OP.packageId
       left outer join tbl_workshop WS on WS.productId = PR.productId  
       left outer join tbl_workshop_def WD on WD.workshopId = WS.workshopId
       left outer join tbl_site as SI on SI.siteId = WS.siteId
       left outer join tbl_schedule_def as SD on SD.scheduleId = WS.scheduleId
       ORDER BY `O`.`orderId`  DESC";

        try{
            $con = $this->db;
            $result=array();
            foreach ($con->query($sql) as $row) {
                if (isset($row['productId'])) {
                    $row += ['groupByProductId'=>$row['productId']];
                } else {
                    $row['productId'] = 'No product id';                    
                }
                if (!isset($row['scheduleId'])) {
                    $row['scheduleId'] = "No schedule ID";                    
                    $row['scheduleNameEN'] = 'No schedule';                    
                    $row['scheduleNameSV'] = 'Inget schema';                    
                }
                if (!isset($row['nameEN'])) {
                    $row['nameEN'] = 'No course name';                    
                }
                $result[] = $row;
            }
            if (count($result)>0) {
                return $response->withJson(array('status' => 'OK', 'result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'ERROR', 'result'=> []),422);
            }
        } catch(\Exception $ex){
            return $response->withJson(array('error' => $ex->getMessage()),422);
        }
        
    }

    protected function _sendMailLocal($orderId, $email, $emailPartner, $language) 
    {
        $requestParams = array('orderId'=>$orderId, 'language'=>$language);
        $subject = $this->_vmailSubject($requestParams);
        $body = $this->_vmailBody($requestParams);
        $this->logger->info("_sendMailLocal(): subject and body created");

        if (isset($email)) {
            if ($this->_sendMail($subject, $body, $email)) {
                $this->logger->info("Mail was sent to $email for orderId = $orderId");
            } else {
                $this->logger->warning("Failed to send mail to customer for  $orderId");
            }
        } else {
            $this->logger->warning("Email address was missing for orderId $orderId");
        }   
        $this->logger->info("Mail was sent to customer");

        if (isset($emailPartner)) {
            if ($this->_sendMail($subject, $body, $emailPartner)) {
                $this->logger->info("Mail was sent to $emailPartner for orderId = $orderId");
            } else {
                $this->logger->warning("Failed to send mail to customers partner for $orderId");
            }
        }    

        $this->logger->info("Mail was sent to partner");
        if ($this->_sendMail($subject, $body, EMAIL_ORDER)) {
            $this->logger->info("Mail was sent to " . EMAIL_ORDER . " for orderId $orderId");
        } else {
            $this->logger->warning("Failed to send mail to " . EMAIL_ORDER . " for orderId $orderId");
        }
        return true;
    }    

    public function PostForm($request, $response) 
    {
        $data = $request->getParsedBody();
        if (($str = $this->_checkMandatoryInput($data, MANDATORY_KEYS))!== null) {
            return $response->withJson(array('status'=>'WARNING', 'message' => $str, 'result'=>null),200);
        }
        $this->logger->info("Before _insertOrder");
        $orderId = $this->_insertOrder($data);
        
        if (isset($data['emailPartner'])) {
            $emailPartner=$data['emailPartner'];
        } else {
            $emailPartner = '';
        }    

        if (isset($data['language'])) {
            $language=$data['language'];
        } else {
            $language = 'EN';
        }    

        // Send mail to customer andl partner
        $this->_sendMailLocal($orderId, $data['email'], $emailPartner, $language);

        return $response->withJson(array('status'=>'OK', 'orderId'=>$orderId, 'message'=>'Form posted successfully'), 200);
    }
};    



