<?php
namespace App\Controllers;

class TestMailController extends Controller
{
    protected function _addressAsText($customer) 
    {
        // address
        $address='';
        $address=$address . ' ' . $customer['firstName'] . ' ' . $customer['lastName'] . '<p />';
        $address=$address . $customer['address'] . '<p />';
        $address=$address . $customer['zip'] . '  ' . $customer['city'] . '<p />';
        $address=$address . $customer['country'] . '<p /><p /><p />';
        $address=$address . 'The mail address we have received is ' . $customer['email'];
        return($address);
    }  
    

    protected function _createMyMail($request, $response) 
    {

        if ($this->_sendMail($subject, $body, $email)) {         
            $this->logger->addDebug('Successful mail sending to ' . $email);
            return $response->withJson(array('status' => 'OK', 
            'message'=>'Successful mail sending',
            'subject'=>$subject, 
            'body'=>$body),200);
        } else{
            $this->logger->error('Failed mail sending to ' . $email);
            return $response->withJson(array('status' => 'ERROR',
                'message'=>'Failing to send mail',
                'subject'=>$subject, 
                'body'=>$body),200);
        }
        if (isset($respoinsibleEmail)) {
            if ($this->_sendMail($subject, $body, $recipient)) {         
                $this->logger->addDebug('Successful mail sending to ' . $respoinsibleEmail);
                return $response->withJson(array('status' => 'OK', 
                'message'=>'Successful mail sending',
                'subject'=>$subject, 
                'body'=>$body),200);
            } else{
                $this->logger->error('Failed mail sending to ' . $respoinsibleEmail);
                return $response->withJson(array('status' => 'ERROR',
                    'message'=>'Failing to send mail',
                    'subject'=>$subject, 
                    'body'=>$body),200);
            }
        }    
    }

    public function testEchoMail($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        /*
        $email = $queryParams['email'];
        $subject = $queryParams['subject'];
        $body = $queryParams['body'];
        */
        if (isset($queryParams['email'])) {
            $email = $queryParams['email'];
        } else {
            echo 'Failing to send mail du to missing field email in request';
            return;
        }

        if (isset($queryParams['subject'])) {
            $subject = $queryParams['subject'];
        } else {
            echo 'WARNING: subject missing';
            return;
        }

        if (isset($queryParams['body'])) {
            $body = $queryParams['body'];
        } else {
            echo 'Warning: body missing';
            return;
        }

        $html =  "<h2>email</h2>";
        $html .= $email;
        $html .=  "<h2>Subject</h2>";
        $html .= $subject;
        $html .="<h2>Body</h2>";
        $html .= $body;
        echo $html;

    }    

}    

