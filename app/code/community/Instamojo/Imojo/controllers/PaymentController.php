<?php

if (!function_exists('boolval')) {
    function boolval($val) {
            return (bool) $val;
    }
}

class Instamojo_Imojo_PaymentController extends Mage_Core_Controller_Front_Action
{
    // Redirect to instamojo 
    private $LOG_FILE_NAME = 'imojo.log';

    public function redirectAction()
    {
        try {
            Mage::Log('Step 5 Process: Loading the redirect.html page', Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $this->loadLayout();
            // Get latest order data
            $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            

            // Set status to payment pending
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true)->save();
            $amount = $order-> getBaseGrandTotal();
            $email = $order->getCustomerEmail();
            $name = $order->getCustomerName();          
            $phone = substr(str_replace(' ', '', $order->getBillingAddress()->getTelephone()), 0, 20);
            $rmTranid = time();

            $index = strpos($amount, '.');
            if ($index !== False){
                $amount = substr($amount, 0, $index+3);  
            }

            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $url = Mage::getStoreConfig('payment/imojo/payment_url', $storeId);
            $api_key = Mage::getStoreConfig('payment/imojo/api_key', $storeId);
            $auth_token = Mage::getStoreConfig('payment/imojo/auth_token', $storeId);
            $private_salt = Mage::getStoreConfig('payment/imojo/private_salt', $storeId);
            $custom_field = Mage::getStoreConfig('payment/imojo/custom_field', $storeId);
            $disable_phone = boolval(Mage::getStoreConfig('payment/imojo/disable_phone', $storeId));

            Mage::log("Data from Backend: $url | $api_key | $auth_token | $private_salt | $custom_field | $disable_phone", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $data = Array();
            $data['data_email'] = substr($email, 0, 75);
            $data['data_name'] = substr($name, 0, 20);
            if(!$disable_phone){
                $data['data_phone'] = $phone;
            }
            $data['data_amount'] = $amount;
            $data['data_' . $custom_field] = $rmTranid . "-". $orderId;

            Mage::log("Transaction-order ID: " . ($rmTranid . "-". $orderId), Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $ver = explode('.', phpversion());
            $major = (int) $ver[0];
            $minor = (int) $ver[1];
            if($major >= 5 and $minor >= 4){
                ksort($data, SORT_STRING | SORT_FLAG_CASE);
            }
            else{
                uksort($data, 'strcasecmp');
            }

            $message = implode('|', $data);
            $sign = hash_hmac("sha1", $message, $private_salt);
            $data['data_sign'] = $sign;
            $data['data_phone'] = $phone;
            
            Mage::log("Signature is: $sign", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            if(!$disable_phone){
                $link = $url . "?embed=form&data_readonly=data_phone&";
            }
            else{
                $link = $url . "?";   
            }

            $link .= "data_readonly=data_email&data_readonly=data_amount&data_readonly=data_name&data_readonly=data_$custom_field&data_hidden=data_$custom_field";
            $link.="&data_amount=$amount&data_name=$name&data_email=$email&data_phone=$phone&data_$custom_field=$custom_field&data_sign=$sign";
            $payment = $order->getPayment();
            $payment->setTransactionId($rmTranid); // Make it unique.
            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                                                    null,
                                                    false,
                                                    'I am good');
            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                                                   array('Context'=>'Token payment',
                                                         'Amount'=>$amount,
                                                         'Status'=>0,
                                                         'Url'=>$link));
            $transaction->setIsTransactionClosed(false); // Close the transaction on return?
            $transaction->save();
            $order->save();

            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'imojo', array('template' => 'imojo/redirect.phtml'))
                          ->assign(array_merge($data, array('url'=>$url, 'custom_field_name'=>'data_' . $custom_field, 'disable_phone'=> $disable_phone)));
            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        } catch (Exception $e){
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->LOG_FILE_NAME);
            parent::_redirect('checkout/cart');
        }
    }

    // Redirect from Instamojo
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction() {

        Mage::log("Running response action", Zend_Log::DEBUG, $this->LOG_FILE_NAME); 

        $storeId = Mage::app()->getStore()->getStoreId();
        $storeCode = Mage::app()->getStore()->getCode();
        Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        $custom_field = Mage::getStoreConfig('payment/imojo/custom_field', $storeId);
        $status = $this->getRequest()->getParam('status');
        $insta_id = $this->getRequest()->getParam('payment_id');
        $this->loadLayout();

        Mage::log("Status: $status| Payment ID: $insta_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

        $data = $this->_getcurlInfo($insta_id);
        $payment_status = $data['payment']['status'];

        if($payment_status === "Credit" || $payment_status === "Failed" || $payment_status === "Initiated"){
            $tr_ord_id = $data['payment']['custom_fields'][$custom_field]['value'];
            Mage::log("Value of Tran-order ID: $tr_ord_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
            $order_tran_id = explode('-',  $tr_ord_id);
            $transactionId = $order_tran_id[0];
            $orderId = $order_tran_id[1];
            $amount = $data['payment']['amount'];

            // Get order details
            $order = Mage::getModel('sales/order');
            $cart = Mage::getSingleton('checkout/cart');
            $order->loadByIncrementId($orderId);
            $session = Mage::getSingleton('checkout/session');

            if($payment_status === "Credit"){
                Mage::log("Payment was successfull for $insta_id", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                Mage::log("Pending payment is set to False", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
                $order->sendNewOrderEmail();
                $order->setEmailSent(true);
                $order->save();

                $payment = $order->getPayment();
                $transaction = $payment->getTransaction($transactionId);  
                $data = $transaction->getAdditionalInformation();
                $url = $data['raw_details_info']['Url'];
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                                    array('InstmojoId'=> $insta_id,
                                          'Context'=>'Token payment',
                                          'Amount'=>$amount,
                                          'Status'=>1,
                                          'Url'=>$url))->save();
                $transaction->setParentTxnId($insta_id)->save();
                $payment->setIsTransactionClosed(1);
                $this->_redirect('checkout/onepage/success', array('_secure'=>true));

            }
            else if($payment_status === "Failed"){
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payment failed.')->save();
                $items = $order->getItemsCollection();

                foreach ($items as $item) {
                    try {
                        $cart->addOrderItem($item);
                    } catch (Mage_Core_Exception $e) {
                        $session->addError($this->__($e->getMessage()));
                        Mage::logException($e);
                        continue;
                    }
                }
                $cart->save();
                Mage::getSingleton('core/session')->addError('Your payment failed. Please try again later');
                $this->_redirect('checkout/cart');
                return;

            }
            else if($payment_status === "Initiated"){
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payment was initiated but never completed.')->save();
                $this->_redirect('checkout/onepage/failure', array('_secure'=>true));
            }
            else{
                $this->_redirect('checkout/onepage/failure', array('_secure'=>true));
            }

        }
        else{
            if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
                $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
                if($order->getId()) {
                    $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Invalid transaction ID or request.')->save();
                }
            }
            $this->_redirect('');
            
        }
        
    }

    // Get the order id from Instamojo based the transaction id
    private function _getcurlInfo($iTransactionId){
         try {

            $storeId = Mage::app()->getStore()->getStoreId();
            $storeCode = Mage::app()->getStore()->getCode();
            Mage::log("Store ID and Code: $storeId | $storeCode", Zend_Log::DEBUG, $this->LOG_FILE_NAME);

            $cUrl = 'https://www.instamojo.com/api/1.1/payments/' . $iTransactionId . '/';
            $api_key = Mage::getStoreConfig('payment/imojo/api_key', $storeId);
            $auth_token = Mage::getStoreConfig('payment/imojo/auth_token', $storeId);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Api-Key:$api_key",
                                                       "X-Auth-Token:$auth_token"));
            $response = curl_exec($ch);
            $error_number = curl_errno($ch);
            $error_message = curl_error($ch);
            $response_obj = json_decode($response, true);

            Mage::log("Error number: $error_number", Zend_Log::ERR, $this->LOG_FILE_NAME);
            Mage::log("Error number: $error_message", Zend_Log::ERR, $this->LOG_FILE_NAME);

            $res = json_decode($response, true);
            curl_close($ch);   
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log($e, Zend_Log::ERR, $this->LOG_FILE_NAME);
            throw $e;
        }
        return $res;
    }

} 