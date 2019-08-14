<?php
// This module is more than a normal payment gateway
// It needs dashboard and all


class Instamojo_Imojo_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract{
    /**
     * Availability options
     */ 

    private $LOG_FILE_NAME = 'imojo.log';

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
        $redirect_url = Mage::getUrl('imojo/payment/redirect');
        Mage::Log("Step 2 Process: Getting the redirect URL: $redirect_url", Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        return $redirect_url;      
    }

    public function authorize(Varien_Object $payment, $amount){
        Mage::Log('Step 0 Process: Authorize', Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        return $this;               
    }
    /**
     * this method is called if we are authorising AND
     * capturing a transaction
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Mage::Log('Step 1 Process: Create and capture the process', Zend_Log::DEBUG, $this->LOG_FILE_NAME);
        return $this;
    }

}

// Suggestions from
// http://stackoverflow.com/questions/6058430/magento-redirect-checkout-payment-to-a-3rd-party-gateway
// 