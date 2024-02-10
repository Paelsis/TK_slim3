<?php

namespace App\Controllers;

final class TkDiscountController extends Controller
{
    public function testDiscount($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['discount'])) {
            $discount=$queryParams['discount'];
        }    

        $Discount = $this->container['Discount'];

        // $discounts = $Discount->getDiscounts();
        // $discounts=$Discount->getSampleDiscounts();
        $discounts = $Discount->getDiscounts();
        $regs=$Discount->getSampleRegistrations();
        $reply = $Discount->calcDiscount($discounts, $regs);

        if (true) {
            return $response->withJson($reply, 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    public function testDiscountShoppingCart($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['discount'])) {
            $discount=$queryParams['discount'];
        }    

        $Discount = $this->container['Discount'];
        // $discounts = $this->getDiscounts();
        $discounts=$Discount->getSampleDiscounts();
        $shoppingCartList=$Discount->getSampleRegistrations();
        $reply = $Discount->calcDiscountFromShoppingCart($discounts, $shoppingCartList);

        if (true) {
            return $response->withJson($reply, 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }
    
    public function Discount($request, $response) 
    {
        $input = $request->getParsedBody();
        $data=$input['data'];


        $regs=$data['products'];
        //$sql = $this->_getDiscountSql();
        //$discounts = $this->_selectRows($sql);

        $Discount = $this->container['Discount'];
        $discounts=$Discount->getSampleDiscounts();
        // $regs=$Discount->getSampleRegistrations();
        $reply = $Discount->calcDiscount($discounts, $regs);

        if (true) {
            return $response->withJson($reply, 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }

    public function DiscountShoppingCartList($request, $response) 
    {
        $input = $request->getParsedBody();
        $this->_logObject($input);
        if (isset($input['payload'])) {
            $shoppingCartList=$input['payload'];
        } else {
            $shoppingCartList = array();
            $this->logger->error('No shoppingCartList in input'); 
        }
        $Discount = $this->container['Discount'];
        $discounts=$Discount->getDiscounts();
        // $discounts=$Discount->getSampleDiscounts();
        $reply = $Discount->calcDiscountFromShoppingCart($discounts, $shoppingCartList);

        if ($reply != null) {
            return $response->withJson($reply, 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Failed to copy table'),422);
        }
    }
}   
