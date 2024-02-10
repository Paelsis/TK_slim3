<?php

namespace App\Controllers;

define("EARLY_BIRD", "EB");
define("CAMPAIGN_CODE", "CC");
define("MULTIPLE_COURSE", "MC");

define("COURSE_TYPE_BASIC", "GR");
define("COURSE_TYPE_CONT", "FK");
define("COURSE_TYPE_ADV", "AV");
define("COURSE_TYPE_TEMA", "TE");

final class Discount extends Controller
{
    protected function _atLeastNBasicCourses($leastN, $regs)
    {

        // Two basic courses with identical scheduleId
        $basicCount = array();
        foreach($regs as $reg) {
            if ($reg['courseType'] === 'GR') {
                $scheduleId = $reg['scheduleId'];
                if (!empty($basicCount[$scheduleId])) {
                    $basicCount[$scheduleId]++;                
                    if ($basicCount[$scheduleId] >= $leastN)
                        return(true);
                } else {
                    $basicCount[$scheduleId] = 1;                
                }
            }    
        }    
        return(false);
    }

    protected function _leastNIdenticalCourses($numberOfIdentical, $regs)
    {
        // Two basic courses with identical scheduleId
        $arr = array();
        foreach($regs as $reg) {
            $templateId = $reg['templateId'];
            if (isset($arr[$templateId])) {
                $arr[$templateId]++;                
                if ($arr[$templateId] >= $numberOfidentical) {
                    return(true);
                } else {
                    $arr[$templateId] = 1;                
                }
            }    
        }    
        return(false);
    }

    protected function _leastNIdenticalBasicCourses($numberOfIdentical, $regs)
    {
        // Two basic courses with identical scheduleId
        $arr = array();
        foreach($regs as $reg) {
            if ($reg['courseType'] === 'GR') {
                $templateId = $reg['templateId'];
                if (!empty($arr[$templateId])) {
                    $arr[$templateId]++;                
                    if ($arr[$templateId] >= $numberOfidentical) {
                        return(true);
                    } else {
                        $arr[$templateId] = 1;                
                    }
                }    
            }    
        }    
        return(false);
    }

    protected function _earlyBird($dis, $reg) 
    {
        if (!empty($dis['earlyBirdDays']) && !empty($reg['daysUntilStart']) ) {
            if ((int) $reg['daysUntilStart'] >= (int) $dis['earlyBirdDays']) {
                if ($this->_foundInBothOrUnset($dis, $reg, 'scheduleId')) {
                    if ($this->_foundInBothOrUnset($dis, $reg, 'productId')) {
                        return(true);
                    }
                }        
            } 
        }
        return(false);
    }

    protected function _foundInBoth($dis, $reg, $key) {
        if (!empty($dis[$key]) && !empty($reg[$key])) {
            return($dis[$key] === $reg[$key]);
        } else {
            return(false);
        }    
    }

    protected function _foundInBothOrUnset($dis, $reg, $key) {
        if (empty($dis[$key]) || empty($reg[$key])) {
            return(true);
        } else {
            return($this->_foundInBoth($dis, $reg, $key));
        }
    }

    protected function _campaignCode($dis, $reg) 
    {
        $currentDate=date('Y-m-d');
        if ($this->_foundInBothOrUnset($dis, $reg, 'scheduleId')) {
            if ($this->_foundInBothOrUnset($dis, $reg, 'productId')) {
                if ($this->_foundInBoth($dis, $reg, 'campaignCode')) {
                    if ($this->_betweenDates($currentDate, $dis['startDate'], $dis['endDate'])) {
                        return(true);
                    } 
                }
            }        
        }  
        return(false);  
    }

    protected function _default($dis, $reg) {
        return(true);
    }



    protected function _logDiscount($dis, $amount, $productId) 
    {
        if ($productId != null) {
            $this->logger->info('Discount:' . 
                ' productId=' . $productId . 
                ' type=' . $dis['discountType'] . 
                ' scheduleId=' . $dis['scheduleId'] . 
                ' amount=' . $amount);
        } else {
            $this->logger->info('Discount:' . 
                ' type=' . $dis['discountType'] . 
                ' scheduleId=' . $dis['scheduleId'] . 
                ' amount=' . $amount);
        }       
    }

    private function _getDiscounts($groupByDiscount, $discountType) {
        if (isset($groupByDiscount[$discountType])) {
            return($discounts=$groupByDiscount[$discountType]);
        } else {
            return(null);
        }    
    }    

    protected function _calcAmount($dis, $regPrice) 
    {
        // If an amount is set i discount table, use this 
        if (!empty($dis['amount'])) {
            return $dis['amount'];
        }
        // If a amount > 0 was not found, use percentage if it existsble, use this 
        if (!empty($dis['percent'])) {
            return $dis['percent'] * $regPrice / 100;
        }
        return 0;
    }

    
    protected function _discountCampaignCode($discounts, $reg)
    {
        $regPrice = 0;
        if (!empty($reg['price'])) {
            $regPrice = $reg['price'];
        } 

        foreach ($discounts as $dis) { 
            if ($this->_foundInBothOrUnset($dis, $reg, 'scheduleId')) {
                if ($this->_foundInBothOrUnset($dis, $reg, 'productId')) {
                    if ($this->_campaignCode($dis, $reg)) {
                        $amount = $this->_calcAmount($dis, $regPrice);
                        $this->_logDiscount($dis, $amount, $reg['productId']);
                        return $amount;
                    }
                }
            }        
        }    
        return(0);
    }    

    protected function _discountEarlyBird($discounts, $reg)
    {
        $regPrice = 0;
        if (!empty($reg['price'])) {
            $regPrice = $reg['price'];
        } 

        foreach ($discounts as $dis) { 
            if ($this->_foundInBothOrUnset($dis, $reg, 'scheduleId')) {
                if ($this->_foundInBothOrUnset($dis, $reg, 'productId')) {
                    if ($this->_earlyBird($dis, $reg)) {
                        $amount = $this->_calcAmount($dis, $regPrice);
                        $this->_logDiscount($dis, $amount, $reg['productId']);
                        return $amount;
                    }
                }
            }        
        }    
        return(0);
    }

    protected function _sumPriceAndCourseCount($dis, $regs)
    {
        // Initialize counter
        $courseCount = 0;
        $sumPrice = 0;
        $objPriceCount = array();

        // Count courses and sum price
        foreach($regs as $reg) {
            if (!empty($dis['scheduleId']) && !empty($reg['scheduleId'])) {
                if ($dis['scheduleId'] !== $reg['scheduleId']) {
                    continue;
                }
            }        
            if (!empty($dis['courseType']) && !empty($reg['courseType'])) {
                if ($dis['courseType'] !== $reg['courseType']) {
                    continue;
                }
            }
            $price = 0;
            if (!empty($reg['price'])) {
                $price = $reg['price'];
            } 

            $courseCount++;
            $sumPrice += $price;
        }    
        $objPriceCount += ['courseCount'=>$courseCount, 'sumPrice'=>$sumPrice];

        return($objPriceCount);
    }    
    
    protected function _aggregatedDiscount($dis, $sumPrice, $courseCount)
    {
        // Min courseCount basic courses 
        if ($courseCount >= $dis['courseCount']) {
            $amount = $this->_calcAmount($dis, $sumPrice);
            $this->_logDiscount($dis, $amount, null);
            return $amount;
        } else {
            return 0;
        }
    }    

    protected function _discountMultipleCourse($discounts, $regs) {
        $discount = 0;
        foreach($discounts as $dis) {
            $objPrice = $this->_sumPriceAndCourseCount($dis, $regs);
            $sumPrice = $objPrice['sumPrice'];
            $courseCount = $objPrice['courseCount'];
    
            $discount += $this->_aggregatedDiscount($dis, $sumPrice, $courseCount);

            if ($discount > 0) {
                $this->_logDiscount($dis, $discount, null);
                break;
            }
        }    
        return $discount;
    }

    protected function _discountBySchedule($discounts, $regs)
    {
        
        $scheduleId = $regs[0]['scheduleId'];
        $productType = $regs[0]['productType'];
        $sumDiscount = 0;
        $arrDiscount = array();
        $arrDiscount += ['scheduleId'=>$scheduleId];
        $arrDiscount += [constant('CAMPAIGN_CODE')=>0];
        $arrDiscount += [constant('EARLY_BIRD')=>0];
        $arrDiscount += [constant('MULTIPLE_COURSE')=>0];
        $groupByDiscount = $this->_groupByArr($discounts, 'discountType');

        $this->logger->addDebug('Inside _discountBySchedule');

        // Discounts for each registration, i.e. CAMPAIGN CODE and EARLY BIRD
        foreach($regs as $reg) {
            $discount=0;
            $discountType = constant('CAMPAIGN_CODE');
            $discounts=$this->_getDiscounts($groupByDiscount, $discountType);
            if ($discounts != null) { 
                $discount = $this->_discountCampaignCode($discounts, $reg);
                if ($discount > 0) {
                    $arrDiscount[$discountType] +=$discount;
                    $sumDiscount += $discount;
                    continue;
                }    
            } 
            $discountType = constant("EARLY_BIRD");
            $discounts=$this->_getDiscounts($groupByDiscount, $discountType);
            if ($discounts != null) {
                $discount = $this->_discountEarlyBird($discounts, $reg);
                if ($discount > 0) {
                    $arrDiscount[$discountType] += $discount;
                    $sumDiscount += $discount;
                }   
            }        
        }
        $this->logger->addDebug('Before _discountMultipleCourse');

        // Count the number of courses and get discount if number of basic courses is high enough
        if (!$arrDiscount[constant('CAMPAIGN_CODE')]) {
            $discountType = constant("MULTIPLE_COURSE");
            $discounts=$this->_getDiscounts($groupByDiscount, $discountType);
            if ($discounts != null) { 
                $discount = $this->_discountMultipleCourse($discounts, $regs);
                if ($discount > 0) {
                    $arrDiscount[$discountType] += $discount;
                    $sumDiscount += $discount;
                }  
            }     
        }    
        return($sumDiscount);
    }   
    
    public function getSampleRegistrations()
    {
        $regs = array();

        // Campaign code, course (3 OK)
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'CC_1', 'campaignCode'=>'FLOWER', 'price'=>4); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'CC_2', 'campaignCode'=>'FLOWER', 'price'=>4); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'CC_3', 'campaignCode'=>'FLOWER', 'price'=>4);
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'FALSE_CC_4', 'campaignCode'=>'NON_FLOWER'); 

        // Early bird, 5 less than 30 days and 5 30 days and more. (3 OK, 3 FALSE)
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'EB_1','daysUntilStart'=>32); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'EB_2','daysUntilStart'=>31); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'EB_3','daysUntilStart'=>30); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'FALSE_EB_4','daysUntilStart'=>29); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'FALSE_EB_5','daysUntilStart'=>28); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'FALSE_EB_6','daysUntilStart'=>27); 

        // Course count (4 OK, 1 FALSE)
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'PC_1', 'courseType'=>'GR', 'price'=>400); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'PC_2', 'courseType'=>'GR', 'price'=>400); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'PC_3', 'courseType'=>'GR', 'price'=>400); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'PC_4', 'courseType'=>'GR', 'price'=>400); 
        $regs[]=array('productType'=>'course', 'scheduleId'=>'Spring 2019', 'productId'=>'FALSE_PC_3', 'courseType'=>'FK', 'price'=>400); 


        // Mara CC (2 OK, 1 FALSE)        
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'MARA_CC_1', 'campaignCode'=>'MARA'); 
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'MARA_CC_2', 'campaignCode'=>'MARA'); 
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'FALSE_MARA_CC_3', 'campaignCode'=>'OTHER'); 
        // Mara EB (2 OK, 1 FALSE)        
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'MARA_EB_4', 'daysUntilStart'=>35); 
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'MARA_EB_5', 'daysUntilStart'=>35); 
        $regs[]=array('productType'=>'marathon', 'scheduleId'=>'Marathon 2019', 'productId'=>'FALSE_MARA_EB_6', 'daysUntilStart'=>29); 

        // WS CC (2 OK, 1 FALSE)
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'EAST_CC_1', 'campaignCode'=>'EAST'); 
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'EAST_CC_2',  'campaignCode'=>'EAST'); 
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'FALSE_EAST_CC_3', 'campaignCode'=>'FALSE_EAST'); 
        
        // WS EB (2 OK, 1 FALSE)
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'EAST_EB_1','daysUntilStart'=>30); 
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'EAST_EB_2','daysUntilStart'=>30); 
        $regs[]=array('productType'=>'workshop', 'scheduleId'=>'Easter 2019', 'productId'=>'FALSE_EB_3','daysUntilStart'=>28); 

        return($regs);
    }

    public function getSampleDiscounts()
    {
        $arr = array();

        // Courses
        $arr[]=array('discountType'=>'CC', 'scheduleId'=>'Spring 2019', 'campaignCode'=>'FLOWER', 'startDate'=>'2018-06-01', 'endDate'=>'2020-06-01','amount'=>1); 
        $arr[]=array('discountType'=>'EB', 'scheduleId'=>'Spring 2019', 'earlyBirdDays'=>30,'amount'=>10); 
        $arr[]=array('discountType'=>'MC', 'scheduleId'=>'Spring 2019', 'courseType'=>'GR', 'courseCount'=>2, 'amount'=>100); 

        // marathon
        $arr[]=array('discountType'=>'CC', 'scheduleId'=>'Marathon 2019', 'campaignCode'=>'MARA', 'startDate'=>'01-JAN-18', 'endDate'=>'31-DEC-20', 'amount'=>1000); 
        $arr[]=array('discountType'=>'EB', 'scheduleId'=>'Marathon 2019','earlyBirdDays'=>30, 'amount'=>10000); 
        
        // Easter
        $arr[]=array('discountType'=>'CC', 'scheduleId'=>'Easter 2019', 'campaignCode'=>'EAST', 'startDate'=>'2018-01-01', 'endDate'=>'2020-12-31', 'amount'=>100000); 
        $arr[]=array('discountType'=>'EB', 'scheduleId'=>'Easter 2019', 'earlyBirdDays'=>30, 'amount'=>1000000); 
        return($arr);
    }    

    protected function _getDiscountSql() 
    {
        // Note that productType and productId is important for this SELECT since we are saving it in tbl_products
        $sql = "SELECT * from tbl_discount order by scheduleId asc, courseCount desc";
        return ($sql);
    }    

    public function getDiscounts() 
    {
        $sql = $this->_getDiscountSql();
        $discounts = $this->_selectRows($sql);
        return($discounts);
    }


    protected function _totalDiscount($discounts, $regs)        
    {
        $groupByRegs = $this->_groupByArr($regs, 'scheduleId');

        // Loop over all schedules
        $totalDiscount = 0;
        foreach ($groupByRegs as $key=>$regs) {
            // $regs is registrations for one schedule
            $discount = $this->_discountBySchedule($discounts, $regs);
            $totalDiscount += $discount;
        }
        return $totalDiscount;
    }    

    protected function _extractRegistrations($shoppingCartList) {
        $groupByReg = $this->_groupByArr($shoppingCartList, 'productType');
        $regs = array();
        if (isset($groupByReg['course'])) {
            $a=$groupByReg['course'];
            $regs = array_merge($regs, $a);
        }    
        if (isset($groupByReg['workshop'])) {
            $a=$groupByReg['workshop'];
            $regs = array_merge($regs, $a);
        }    
        if (isset($groupByReg['marathon'])) {
            $a=$groupByReg['marathon'];
            $regs = array_merge($regs, $a);
        }    
        $filterRegs = array();
        foreach($regs as $reg) {
            if (isset($reg['deleted'])) {
                if ($reg['deleted']) {
                    continue;
                }    
            }
            if (isset($reg['debitable'])) {
                if (!$reg['debitable']) {
                    continue;
                }    
            }
            if (isset($reg['amount'])) {
                if ($reg['amount']===0) {
                    continue;
                }    
            }
            $filterRegs[] = $reg;
        }

        return($filterRegs);
    }    

    
    public function calcDiscountFromShoppingCart($discounts, $shoppingCartList) {
        $regs = $this->_extractRegistrations($shoppingCartList); 
        $totalDiscount =  $this->_totalDiscount($discounts, $regs);       
        $this->logger->addDebug('Ready with getDiscounts, totalDiscount=' . $totalDiscount);
     
        return array('status' => 'OK', 
            'totalDiscount'=>$totalDiscount, 
            'groupByDiscounts'=>$this->_groupByArr($discounts, 'discountType'));
    }    

    public function calcDiscount($discounts, $regs) {

        $totalDiscount =  $this->_totalDiscount($discounts, $regs);       
        $this->logger->addDebug('Ready with getDiscounts, totalDiscount=' . $totalDiscount);
        return array('status' => 'OK', 
            'totalDiscount'=>$totalDiscount, 
            'groupByDiscounts'=>$this->_groupByArr($discounts, 'discountType')); 
    }    
}   
