<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Currency;
use App\Invoice;
use App\Lib\Cart;
use App\PaymentMethod;
use App\V2\Model\PaymentMethodTable;
use Illuminate\Http\Request;
use App\Lib\HelperTrait;
use Psr\Http\Message\ResponseInterface as Response;

class InvoiceController extends Controller
{
    use HelperTrait;

    public function invoices(){

    }

    public function paymentMethods(Request $request){

        $params = $request->all();
        $isValid = $this->validateGump($params,[
            'currency_id'=>'required'
        ]);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        $params = $request->all();

        $currencyId = $params['currency_id'];
        $paymentMethodTable = new PaymentMethodTable();
        $paymentMethods = $paymentMethodTable->getMethodsForCurrency($currencyId);
        $output = $paymentMethods->toArray();
        foreach ($output as $key=>$value){
            $output[$key]['method_label'] = $output[$key]['label'];
        }
        return jsonResponse($output);
    }

    public function storeInvoice(Request $request){

        $data = $request->all();
        $isValid = $this->validateGump($data,[
            'sessions'=>'required',
            'payment_method_id'=>'required',
            'currency_id'=>'required'

        ]);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        //create cart
        $cart = new Cart();
        //  $cart->setStudent($this->getApiStudentId());

        //add items to cart
        foreach($data['sessions'] as $value){
            $cart->addSession($value);
        }

        //now create the invoice
        //first check if the student has an unpaid invoice recently created
        $student = $this->getApiStudent();
        $studentId = $student->id;
        $userId= $student->user_id;
        $currencyId = $data['currency_id'];

        //check coupon if exsits
        if(!empty($data['coupon']))
        {
            $code= $data['coupon'];
            // $coupon = Coupon::where('code',trim(strtolower($code)))->where('expires','>',time())->where('enabled',1)->first();
            $coupon = $cart->getCoupon($code);
            if(!$coupon){
                return jsonResponse([
                    'status'=>false,
                    'message'=>__lang('invalid-coupon')
                ]);
            }
            else{
                $cart->applyDiscount($code);
            }
        }

        if(!$cart->requiresPayment()){
            $cart->approve($userId);
            return jsonResponse([
                'payment_required'=> $cart->requiresPayment(),
                'status'=>true,
            ]);

        }

        $cart->setPaymentMethod($data['payment_method_id']);


        $invoice = Invoice::where('user_id',$userId)->orderBy('id','desc')->first();

        if($invoice && $invoice->paid ==0){
            //create new invoice
            $invoice->cart = serialize($cart);
            $invoice->amount = price($cart->getCurrentTotal(),$currencyId,true);
            $invoice->payment_method_id = $data['payment_method_id'];
            $invoice->currency_id = $data['currency_id'];
            $invoice->save();
        }
        else{
            $invoice = Invoice::create([
                'user_id'=>$userId,
                'currency_id'=>$data['currency_id'],
                'amount'=>price($cart->getCurrentTotal(),$currencyId,true),
                'cart' => serialize($cart),
                'paid'=> 0,
                'payment_method_id'=>$data['payment_method_id']
            ]);

        }
        $invoice->invoice_id = $invoice->id;
        //get payment method
        $paymentMethod = PaymentMethod::find($data['payment_method_id']);
        $paymentMethod->method_label = $paymentMethod->label;

        $sessions = [];
        foreach($cart->getSessions() as $session){
            $sessions[] = apiCourse($session);
        }
//        $cart->setInvoice($invoice->invoice_id);
        $currencyObj = Currency::find($currencyId);
        $currency= $currencyObj->toArray();
        $currency['currency_id'] = $currencyObj->id;
        $currency['currency_symbol']=$currencyObj->country->symbol_left;


        return jsonResponse([
            'invoice'=>$invoice->toArray(),
            'payment_method'=>$paymentMethod->toArray(),
            'payment_required'=> $cart->requiresPayment(),
            'sessions'=> $sessions,
            'status'=>true,
            'has_discount'=>$cart->hasDiscount(),
            'discount_applied'=>$cart->getDiscount(),
            'discount_type'=>$cart->discountType(),
            'currency'=>$currency
        ]);

    }

    public function getInvoice(Request $request,$id){

        $student = $this->getApiStudent();
        $invoice = Invoice::find($id);
        if($invoice){
            if($invoice->user_id != $student->user_id){
                return jsonResponse([
                    'status'=>false
                ]);
            }
            $data = $invoice->toArray();
            return jsonResponse([
                'invoice'=>$data,
                'status'=>true
            ]);
        }
        else{
            return jsonResponse([
                'status'=>false
            ]);
        }
    }
}
