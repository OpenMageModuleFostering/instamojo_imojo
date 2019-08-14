<?php
// This module is more than a normal payment gateway
// It needs dashboard and all

$LOG_FILE_NAME = 'imojo.log';

class Instamojo_Imojo_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract{
    /**
     * Availability options
     */ 
    protected $_code = 'imojo';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_isInitializeNeeded      = false;
    
    // TO DO NOTES
    // Implement safer signed links
    // Create link first? Manually created in this case.

    /**
    * @return Mage_Checkout_Model_Session
    */
    protected function _getCheckout()
    {
       return Mage::getSingleton('checkout/session');
    }

    // Construct the redirect URL
    public function getOrderPlaceRedirectUrl()
    {   
        global $LOG_FILE_NAME;
        $redirect_url = Mage::getUrl('imojo/payment/redirect');
        Mage::Log('Step 2 Process: Getting the redirect URL: $redirect_url', Zend_Log::DEBUG, $LOG_FILE_NAME);
        return $redirect_url;      
    }

    //  Check why capture is not working. I clearly specified it in the config.xml file. WTF!
    // Need to look back into this issue more cleanly
    public function authorize(Varien_Object $payment, $amount){
        global $LOG_FILE_NAME;
        Mage::Log('Step 0 Process: Authorize', Zend_Log::DEBUG, $LOG_FILE_NAME);
        // Haha interestingly this is working. Lolapa
        // $order = $payment->getOrder();
        // Create and capture transaction. How do we use it? Lets see.\
        // $transactionId = time();
        // $payment->setTransactionId($transactionId) // Make it unique. This is Rentomojo Transaction ID
                // ->setIsTransactionClosed(0) // Close the transaction on return?
                // ->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array('Context'=>'Token payment','Amount'=>'900','Status'=>0)); 
        return $this;               
    }
    // I think Instamojo can do this :). We need to authorize and capture for later reference
    /**
     * this method is called if we are authorising AND
     * capturing a transaction
     */
    public function capture(Varien_Object $payment, $amount)
    {
        global $LOG_FILE_NAME; 
        Mage::Log('Step 1 Process: Create and capture the process', Zend_Log::DEBUG, $LOG_FILE_NAME);
        // parent::capture();
        // $order = $payment->getOrder();
        // Create and capture transaction. How do we use it? Lets see.\
        // $transactionId = time();
        // $payment->setTransactionId($transactionId) // Make it unique. This is Rentomojo Transaction ID
        //         ->setIsTransactionClosed(0) // Close the transaction on return?
        //         ->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array('key1'=>'value1','key2'=>'value2')); 
        return $this;
    }

}

// Suggestions from
// http://stackoverflow.com/questions/6058430/magento-redirect-checkout-payment-to-a-3rd-party-gateway
// 