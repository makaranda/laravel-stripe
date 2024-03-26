<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Payments;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//use Session;
use Stripe;

class StripeController extends Controller
{
    protected $payments;

    public function __construct()
    {
        $this->payments = new Payments();
    }
    public function index()
    {
        $customer_details = Session::get('customer_details');
        //dd($customer_details);
        return view('index',['customer_details' => $customer_details]);
    }

    public function checkout(Request $request)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        $redirectUrl = route('stripe.success').'?session_id={CHECKOUT_SESSION_ID}';
        $cus_name = $request->name;
        $cus_pro_code = $request->pro_code;
        $cus_amount = $request->amount;
        $email = $request->email;
        $attachement = $request->file('attachement');

    // Retrieve the uploaded file
    $file = $request->file('attachement');

    // Generate a unique filename
    $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

    // Move the file to a temporary location (e.g., storage/app/uploads)
    $file->move('public/files', $fileName);

    // Store the file path in the session
    session()->put('filePath', 'public/files/' . $fileName);

        //$customer_details = session()->get('customer_details', []);

        $customer_details = [
            "cus_name" => $cus_name,
            "cus_pro_code" => $cus_pro_code,
            "cus_amount" => $cus_amount,
            "email" => $email,
            "attachement" => $fileName,
        ];
        Session::put('customer_details', $customer_details);
        //dd(Session::get('customer_details'));
        //return view('index',['customer_details' => Session::get('customer_details')]);
        $response = $stripe->checkout->sessions->create([
            'success_url' => $redirectUrl,
            'customer_email' => 'makarandapathirana@gmail.com',
            'payment_method_types' => ['link', 'card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'send a money',
                        ],
                        'unit_amount' => ($cus_amount * 100),
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'allow_promotion_codes' => true,
        ]);

        return redirect($response['url']);

        // public function checkout(){
        // \Stripe\Stripe::setApiKey(config(key:'stripe.sk'));
        // $session = \Stripe\Checkout\Session::create([
        //     'line_items'=> [
        //         [
        //             'price_data'=> [
        //                 'currency'=> 'usd',
        //                 'product_data'=> [
        //                     'name'=> 'send a money',
        //                 ],
        //                 'unit_amount'=> 500,
        //             ],
        //             'quantity'=> 1,
        //         ],
        //     ],
        //     'mode'=> 'payment',
        //     'success_url' => route('stripe.success'),
        //     'cancel_url' => route('stripe.index'),
        // ]);

        // return redirect()->away($session->url);
    }

    public function success(Request $request)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
        $formData = $request->all();
        $customer_details = Session::get('customer_details');
        //dd($customer_details['email']);
        $customer_email = $customer_details['email'];
        $customer_name = $customer_details['cus_name'];
        $customer_amount= $customer_details['cus_amount'];
        $customer_pro_code= $customer_details['cus_pro_code'];
        $customer_attachement= $customer_details['attachement'];

        $paymentData = [
            'name' => "$customer_name",
            'email' => $customer_email,
            'pro_code' => $customer_pro_code,
            'amount' => $customer_amount,
            'attachment' => $customer_attachement,
        ];

        Payments::create($paymentData);

        $mail = new PHPMailer(true);

        try{
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = env('MAIL_HOST');
            $mail->Username = env('MAIL_USERNAME');
            $mail->Password = env('MAIL_PASSWORD');
            $mail->SMTPSecure = env('MAIL_ENCRYPTION');
            $mail->Port = env('MAIL_PORT');

            $mail->setFrom(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $mail->addAddress($customer_email);

            $mail->isHTML(true);
   
            $mail->Subject = $request->subject;
            $mail->Body    = $request->body;
   
            if( !$mail->send() ) {
                return back()->with("error", "Email not sent.")->withErrors($mail->ErrorInfo);
            }
              
            else {
                return back()->with("success", "Email has been sent.");
            }
            
        }catch (Exception $e) {
            return back()->with('error','Message could not be sent.');
       }

        $to_email = "$customer_email";
        $subject = "Payment Completed - from $customer_name";
        $body = "Hello $customer_name, Your payment is Successfull received to us and we will contact you soon";
        $headers = "From: makaranda@damro.lk\r\n";


        if (mail($to_email, $subject, $body, $headers)) {
  
        } 

        $message_type = "success";
        $message = "Your payment is Successfull";

        Session::forget('customer_details');
        // dd($request->session_id);
        // $response = $stripe->checkout->sessions->retrieve($request->session_id);
        return redirect()->route('stripe.index')->with(''.$message_type.'', ''.$message.'');
        // return redirect()->route('stripe.success')->with('success','Payment Successfull');
        // return view('index');
    }
}
