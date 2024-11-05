<?php
/**
 * Created by PhpStorm.
 * User: USER PC
 * Date: 1/24/2017
 * Time: 2:08 PM
 */

namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Lib\HelperTrait;
use Illuminate\Http\Request;

use App\Invoice;
use App\Transaction;
use App\V2\Model\CountryTable;
use App\V2\Model\InvoiceTransactionTable;
use App\V2\Model\PaymentMethodFieldTable;
use App\V2\Model\PaymentMethodTable;
use App\V2\Model\SessionTable;
use App\V2\Model\TransactionTable;
use App\Lib\UtilityFunctions;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\JsonModel;



class MethodController extends Controller {

    use HelperTrait;
    private $data = [];

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $controller->layout('layout/student');
        }, 100);
    }

    /**
     * @return array
     * This displays the paystack payment method page
     */
    public function paystack(Request $request)
    {

        $id = getCart()->getInvoice();

        $this->loadValues('paystack',$id,true);



        return $this->data;

    }

    /**
     * @return array
     * This displays the stripe payment method page
     */
    public function stripe(Request $request)
    {

        $id = getCart()->getInvoice();

        $this->loadValues('stripe',$id);
        $this->data['currency'] = $this->getCurrencyCode();


        return $this->data;

    }

    /*
     * This displays bank deposit payment method.
     */
    public function bank(Request $request){

        $id = getCart()->getInvoice();

        $this->loadValues('bank',$id,true);


        $title = __lang('Payment for Enrollment');
        $student = $this->getStudent();
        $this->data['bank_instructions'] = '<h2>'.__lang('Your Invoice id').': '.$id.'</h2><h2>'.__lang('Amount').': '.price($this->invoice()->amount).'</h2>'.$this->data['bank_instructions'];
        $this->sendEmail($student->email,$title,$this->data['bank_instructions']);

        return $this->data;
    }

    /**
     * Handles the paypal payment. Uses omnipay library
     */
    public function paypal(Request $request)
    {
        $session = new Container('paypal');
        $id = getCart()->getInvoice();
        $this->loadValues('paypal',$id);
       // return redirect()->route('application/default',['controller'=>'callback','action'=>'paypal']);


        $gateway = \Omnipay\Omnipay::create('PayPal_Rest');
        $gateway->initialize(array(
            'clientId' => trim($this->data['clientid']),
            'secret'   => trim($this->data['secret']),
            'testMode' => empty($this->data['mode']), // Or false when you are ready for live transactions

        ));

        $transaction = $gateway->authorize(array(
            'amount'        => number_format(floatval($this->invoice()->amount), 2, '.', ''),
            'currency'      => $this->getCurrencyCode(),
            'description'   => __lang('Enrollment for').' '.getCart()->getTotalItems().' '.__lang('items'),
            'returnUrl' => $this->url()->fromRoute('application/default',['controller'=>'callback','action'=>'paypal'],['force_canonical' => true]),
            'cancelUrl' => $this->url()->fromRoute('cart'),

        ));


        $response = $transaction->send();
        $session->transactionRef = $response->getTransactionReference();

       // print_r($response);
      //  exit();
        if ($response->isRedirect()) {
            // Yes it's a redirect.  Redirect the customer to this URL:
            $redirectUrl = $response->getRedirectUrl();
            $this->redirect()->toUrl($redirectUrl);
        }
        else{

            return redirect()->route('cart');
        }
    }


    /**
     * Handles twocheckout gateway. Uses omnipay library
     */
    public function twocheckout(Request $request)
    {
        $id = getCart()->getInvoice();
        $this->loadValues('twocheckout',$id);
        $session = new Container('twocheckout');
        // return redirect()->route('application/default',['controller'=>'callback','action'=>'paypal']);


        $gateway = \Omnipay\Omnipay::create('TwoCheckoutPlus');
        $gateway->initialize(array(
            'accountNumber' => trim($this->data['accountNumber']),
            'secretWord'   => trim($this->data['secretWord']),
            'testMode' => empty($this->data['mode'])
        ));



        $transaction = $gateway->purchase(array(
            'amount'        => number_format(floatval($this->invoice()->amount), 2, '.', ''),
            'currency'      => $this->getCurrencyCode(),
            'description'   => 'Enrollment for '.getCart()->getTotalItems().' items',
            'returnUrl' => $this->getBaseUrl().'/student/callback/twocheckout',
            'cancelUrl' => $this->getBaseUrl().'/student/payment',
            'cart'=>        [
                    [
                    'type'     => 'product',
                    'name'     => __lang('Enrollment for').' '.getCart()->getTotalItems().' '.__lang('items'),
                    'quantity' => 1,
                    'price'    => number_format(floatval($this->invoice()->amount), 2, '.', ''),
                    'tangible' => 'N'
                ]
            ]
        ));


        $response = $transaction->send();
        $session->transactionRef = $response->getTransactionReference();

        // print_r($response);
        //  exit();
        if ($response->isRedirect()) {
            // Yes it's a redirect.  Redirect the customer to this URL:
            $redirectUrl = $response->getRedirectUrl();
            $this->redirect()->toUrl($redirectUrl);
        }
        else{

            return redirect()->route('cart');
        }
    }


    public function payu(Request $request){
        $id = getCart()->getInvoice();

        $this->loadValues('payu',$id,true);



        return $this->data;


    }

    public function payusend(Request $request)
    {
        $id = getCart()->getInvoice();
        $this->loadValues('payu',$id,true);


        $setTransactionData = array();
        $setTransactionData['TransactionType'] = $this->data['payu_easyplus_transaction_type'];

        // Creating Basket Array
        $basket = array();
        $basket['amountInCents'] = $this->invoice()->amount*100;
        if (strpos($basket['amountInCents'],'.') !== false) {
            list($basket['amountInCents'],$tempVar) = explode(".", $basket['amountInCents'], 2);
            $basket['amountInCents'] = $basket['amountInCents']+1;
        }
        $basket['description'] = 'Enrollment: '.getCart()->getTotalItems().' items';
        $basket['currencyCode'] = $this->data['payu_easyplus_payment_currency'];
        $setTransactionData = array_merge($setTransactionData, array('Basket' => $basket));
        $basket = null;
        unset($basket);

        $student = $this->getStudent();
        // Creating Customer Array
        $customer = array();
        $customer['firstName'] = $student->first_name;
        $customer['lastName'] = $student->last_name;
        $customer['mobile'] = $student->mobile_number;
        $customer['email'] = $student->email;
        $customer['ip'] = getClientIp();
        $setTransactionDataArray = array_merge($setTransactionData, array('Customer' => $customer));
        $customer = null;
        unset($customer);

        //Creating Additional Information Array
        $additionalInformation = array();

        $paymentMethods ='';
        if(!empty($this->data['payu_easyplus_method_credit_card']))
        {
            $paymentMethods.= 'CREDITCARD,';
        }
        if(!empty($this->data['payu_easyplus_method_discovery_miles']))
        {
            $paymentMethods.= 'DISCOVERYMILES,';
        }
        if(!empty($this->data['payu_easyplus_method_ebucks']))
        {
            $paymentMethods.= 'EBUCKS,';
        }
        if(!empty($this->data['payu_easyplus_method_eft']))
        {
            $paymentMethods.= 'EFT,';
        }
        if(!empty($this->data['payu_easyplus_method_masterpass']))
        {
            $paymentMethods.= 'MASTERPASS,';
        }
        if(!empty($this->data['payu_easyplus_method_rcs']))
        {
            $paymentMethods.= 'RCS,';
        }
        if(!empty($this->data['payu_easyplus_method_eft_pro']))
        {
            $paymentMethods.= 'EFT_PRO,';
        }
        if(!empty($this->data['payu_easyplus_method_creditcard_vco']))
        {
            $paymentMethods.= 'CREDITCARD_VCO,';
        }
        if(!empty($this->data['payu_easyplus_method_mobicred']))
        {
            $paymentMethods.= 'MOBICRED,';
        }

        $additionalInformation['supportedPaymentMethods'] = $paymentMethods;
        $additionalInformation['cancelUrl'] = $this->url()->fromRoute('application/default',['controller'=>'payment','action'=>'index'],['force_canonical' => true]);
        $additionalInformation['notificationUrl'] = $this->url()->fromRoute('application/payu-ipn',['id'=>$this->data['tid']],['force_canonical' => true]);
        $additionalInformation['returnUrl'] = $this->url()->fromRoute('application/default',['controller'=>'callback','action'=>'payu','id'=>$this->data['tid']],['force_canonical' => true]);
        $additionalInformation['merchantReference'] = $this->data['tid'];
        $setTransactionData = array_merge($setTransactionData, array('AdditionalInformation' => $additionalInformation));
        $additionalInformation = null;
        unset($additionalInformation);

        //Creating a config array for RPP instantiation
        $config = array();
        $config['safe_key'] = $this->data['payu_easyplus_safe_key']; ;
        $config['api_username'] = $this->data['payu_easyplus_api_username'];
        $config['api_password'] = $this->data['payu_easyplus_api_password'];

        $config['logEnable'] = true;
        $config['extended_debug'] = true;

        if(strtolower($this->data['payu_easyplus_transaction_mode']) == 'production') {
            $config['production'] = true;
            $config['logEnable'] = false;
            $config['extended_debug'] = false;
        }

        $json['error'] = 'Unable to contact PayU service. Please contact merchant.';
        $message = '';
        try{
            require_once 'vendor/payu/classes/PayUEasyPlus.php';
            $payUEasyPlus = new \PayUEasyPlus($config);
            $setTransactionResponse = $payUEasyPlus->doSetTransaction($setTransactionData);
            if(isset($setTransactionResponse['payu_easyplus_url'])) {
                $json['redirect'] = $setTransactionResponse['payu_easyplus_url'];
                $message = 'Redirected to PayU for payment, ';
                $message .= 'PayU Reference: ' . $setTransactionResponse['soap_response']['payUReference'];

            } else {

                flashMessage($json['error']);
                return back();

            }
        } catch(\Exception $e) {
            $json['error'] = $e->getMessage().$e->getTraceAsString();
        }

        if(isset($json['redirect'])) {
            unset($json['error']);
        }

        exit(json_encode($json));

    }

    public function payfast(Request $request){
        $id = getCart()->getInvoice();

        $this->loadValues('payfast',$id,true);

        $pfHost = ($this->data['payfast_sandbox'] ? 'sandbox' : 'www') . '.payfast.co.za';
        if($this->data['payfast_debug']){$debug = true;}else{$debug = false;}
        define( 'PF_DEBUG', $debug );

        $this->data['sandbox'] = $this->data['payfast_sandbox'];

        $this->data['action'] = 'https://'.$pfHost.'/eng/process';

        if(!$this->data['payfast_sandbox'] || !empty($this->data['payfast_merchant_id']))
        {
            $merchant_id = $this->data['payfast_merchant_id'];
            $merchant_key = $this->data['payfast_merchant_key'];

        }
        else{
            $merchant_id = '10000100';
            $merchant_key = '46f0cd694581a';

        }

        $student = $this->getStudent();
        $return_url = $this->url()->fromRoute('application/default',['controller'=>'callback','action'=>'payfast','id'=>$this->data['tid']],['force_canonical' => true]);//$this->url->link('checkout/success','',$config->get('config_use_ssl')?'SSL':'NONSSL');
        $cancel_url = $this->url()->fromRoute('application/default',['controller'=>'payment','action'=>'index'],['force_canonical' => true]);;// $this->url->link('checkout/checkout','',$config->get('config_use_ssl')?'SSL':'NONSSL');
        $notify_url = $this->url()->fromRoute('application/payfast-ipn',['id'=>$this->data['tid']],['force_canonical' => true]);// $this->url->link('payment/payfast/callback','',$config->get('config_use_ssl')?'SSL':'NONSSL');
        $name_first = html_entity_decode($student->first_name, ENT_QUOTES, 'UTF-8');
        $name_last = html_entity_decode($student->last_name, ENT_QUOTES, 'UTF-8');
        $email_address = $student->email;
        $m_payment_id = $this->data['tid'];
        $amount = $this->invoice()->amount;
        $item_name = 'Enrollment: '.getCart()->getTotalItems().' items';
        $item_description = 'Enrollment: '.getCart()->getTotalItems().' items';
        $custom_str1 = $this->data['tid'];


        $payArray = array(
            'merchant_id'=>$merchant_id,
            'merchant_key'=>$merchant_key,
            'return_url'=>$return_url,
            'cancel_url'=>$cancel_url,
            'notify_url'=>$notify_url,
            'name_first'=>$name_first,
            'name_last'=>$name_last,
            'email_address'=>$email_address,
            'm_payment_id'=>$m_payment_id,
            'amount'=>$amount,
            'item_name'=>html_entity_decode( $item_name ),
            'item_description'=>html_entity_decode( $item_description ),
            'custom_str1'=>$custom_str1
        );
        $secureString = '';
        foreach($payArray as $k=>$v)
        {
            $secureString .= $k.'='.urlencode(trim($v)).'&';
            $this->data[$k] = $v;
        }


        $passphrase = $this->data['payfast_passphrase'];
        if( !empty( $passphrase ) && !$this->data['payfast_sandbox'] )
        {
            $secureString = $secureString.'passphrase=' . urlencode( $this->data['payfast_passphrase'] );
        }
        else
        {
            $secureString = substr( $secureString, 0, -1 );
        }

        $securityHash = md5($secureString);
        $this->data['signature'] = $securityHash;


        return $this->data;


    }


    public function payumoney(Request $request){
        $id = getCart()->getInvoice();

        $this->loadValues('payumoney',$id,true);
        $url = '';
        if($this->data['payumoney_sandbox']==1){
            $url = 'https://sandboxsecure.payu.in';
        }
        else{
            $url = 'https://secure.payu.in';
        }

        $student = $this->getStudent();
        $transaction = Transaction::find($this->data['tid']);
        $this->data['action'] = $url.'/_payment';
        $this->data['surl'] = $this->url()->fromRoute('application/default',['controller'=>'callback','action'=>'payumoney'],['force_canonical' => true]);//HTTP_SERVER.'/index.php?route=payment/payu/callback';
        $this->data['furl'] = $this->url()->fromRoute('application/default',['controller'=>'payment','action'=>'index'],['force_canonical' => true]);
        $this->data['curl'] = $this->url()->fromRoute('application/default',['controller'=>'callback','action'=>'payumoney'],['force_canonical' => true]);
        $key          =  trim($this->data['payumoney_merchant_key']);
        $amount       =  $transaction->amount;
        $productInfo  = 'Enrollment: '.getCart()->getTotalItems().' items';;
        $firstname    = $student->first_name;
        $email        = $student->email;
        $salt         = trim($this->data['payumoney_salt']);
        $udf5 		  = "traineasy";
        $Hash=hash('sha512', $key.'|'.$this->data['tid'].'|'.$amount.'|'.$productInfo.'|'.$firstname.'|'.$email.'|||||||||||'.$salt);
        $data['user_credentials'] = $key.':'.$email;
        $this->data['udf5'] = $udf5;
        $this->data['hash'] = $Hash;
        $this->data['key'] = $key;
        $this->data['student'] = $student;
        $service_provider = 'payu_paisa';
        $this->data['service_provider'] = $service_provider;
        $this->data['amount'] = $amount;
        $this->data['productinfo'] = $productInfo;
        $this->data['phone'] = $student->mobile_number;
        $this->data['lastname'] = $student->last_name;
        $this->data['firstname'] = $firstname;
        $this->data['udf5']= $udf5;
        $this->data['email'] = $email;


        return $this->data;

    }

    public function ipay(Request $request)
    {

        $id = getCart()->getInvoice();

        $this->loadValues('ipay',$id,true);



        return $this->data;

    }

    /**
     * @return array
     * This displays the paystack payment method page
     */
    public function rave(Request $request)
    {

        $id = getCart()->getInvoice();

        $this->loadValues('rave',$id,true);
        $id = $this->getSetting('country_id');
        if(empty($id)){
            $id = 223;
        }
        $countryTable = new CountryTable();
        $this->data['country'] = $countryTable->getRecord($this->invoice()->currency->country_id);


        return $this->data;

    }

    public function paytabs(Request $request){
        $id = getCart()->getInvoice();

        $this->loadValues('paytabs',$id,true);

        $countryTable = new CountryTable();
        $this->data['country'] = $countryTable->getRecord($this->invoice()->currency->country_id);

        //get country dial code
        $this->data['dialCode'] = getCountryDialCode($this->data['country']->iso_code_2);

        //get list of products in cart
        $cartItems = getCart()->getSessions();
        $itemList = '';
        foreach ($cartItems as $session){
            $itemList.= $session->session_name.',';
        }

        $this->data['sessionList'] = $itemList;

        return $this->data;
    }

    /**
     * @param $code
     * @param $id
     * @param bool $createTransaction
     * @throws \Exception
     * This loads the values of a gateway into the $data property of the class.
     * It can also optionally create a unique transaction id.
     */
    private function loadValues($code,$id,$createTransaction=false){

        $paymentMethodTable = new PaymentMethodTable();
        $sessionTable = new SessionTable();
        $paymentFieldsTable = new PaymentMethodFieldTable();
        $rowset = $paymentFieldsTable->getCodeValues($code);
        $methodRow = $paymentMethodTable->getMethodWithCode($code);

        foreach($rowset as $row){
            $this->data[$row->key]=$row->value;
            $this->data['pageTitle'] = $row->method_label;
        }


        $invoice = Invoice::find($id);
        $this->data['invoice'] = $invoice;
        if($createTransaction){

        $transactionTable = new InvoiceTransactionTable();
        $tid = $transactionTable->addRecord([
            'date'=>time(),
            'amount'=>$invoice->amount,
            'invoice_id'=>$id,
            'status'=>'p',
        ]);

        $this->data['tid'] = $tid;

        }

        $this->data['siteUrl'] = $this->getBaseUrl();
        $this->data['student'] = $this->getStudent();

    }


    private function invoice(){
        return Invoice::find(getCart()->getInvoice());
    }

    public function getCurrencyCode(){
        return $this->invoice()->currency->country->currency_code;
    }
}
