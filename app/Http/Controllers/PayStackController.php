<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PayStackController extends Controller
{
    public function initializeEnrollmentPayment (Request $request){

            $req = $request->all();

            /*
             * verify all possible field that it's unique
             * initiate payment
             * parse all the necessary field into the something
             * */

            try {

                $request->validate([
                    'pharmacy_email' => [
                        'required', Rule::unique('pharmacies','email')
                    ],
                    'pharmacy_phone_no' => [
                        'required', Rule::unique('pharmacies','phone_no')
                    ],
                    'reg_ID' => [
                        'required', Rule::unique('pharmacies','reg_ID')
                    ],
                    'user_phone_no' => [
                        'required', Rule::unique('users','phone_no')
                    ],
                    'user_email' => [
                        'required', Rule::unique('users','email')
                    ],
                    'branch_phone_no' => [
                        'required', Rule::unique('branches','phone_no')
                    ],
                ]);

                if ( $req['subscription_name'] === 'trial'){
                    $pharmacyPayload =  [
                        'pharmacy_name' => $req['pharmacy_name'],
                        'reg_ID' => $req['reg_ID'],
                        'phone_no' => $req['pharmacy_phone_no'],
                        'email' => $req['pharmacy_email'],
                        'PIMA_ID' => Str::random(7),
                        'subscription_date' =>  now()->addDays(15) ,
                        'subscription_name' => $req['subscription_name'],
                    ];


                    $pharmacy = Pharmacy::create($pharmacyPayload);


                    $branchPayload =  [
                        'branch_name' => $req['branch_name'],
                        'address' => $req['branch_address'],
                        'phone_no' => $req['branch_phone_no'],
                        'pharmacy_id' => $pharmacy->id,

                    ];


                    $branch = Branch::create($branchPayload);

                    $payload = [
                        'name' => $req['user_name'],
                        'email' => $req['user_email'],
                        'password' => Hash::make( $req['password']),
                        'user_role' => "admin",
                        'regID' => "ADMIN".Str::random(4),
                        'phone_no'=> $req['user_phone_no'],
                        'branch_id'=> $branch->id,
                        'address' => $req['user_address'],
                        'date_of_birth' => $req['dateOfBirth']


                    ];


                    $admin = User::create($payload);

                    return response()->json(['message'=>'Your Free SignUp has been Activated','data'=> $pharmacy],200);
                }

                $enrollmentPaymentInit = HTTP::withToken(env('PAYSTACK_SECRETE_KEY'))
                    ->post("https://api.paystack.co/transaction/initialize",[
                        'email' => $req['pharmacy_email'],
                        'amount' => $req['amount'] *100,
                        'callback_url' => url("/api/enrollmentPaymentCallback?pharmacy_name={$req['pharmacy_name']}&reg_ID={$request['reg_ID']
                        }&phone_no={$request['phone_no']}&email={$request['email']}&PIMA_ID={$request['PIMA_ID']}&subscription_date={$request['subscription_date']
                        }&subscription_name={$request['subscription_name']}&branch_name={$request['branch_name']}&branch_address={$request['branch_address']
                        }&user_name={$request['user_name']}&user_email={$request['user_email']}&password={$request['password']}&user_phone_no={$request['user_phone_no']
                        }&user_address={$request['user_address']}&dateOfBirth={$request['dateOfBirth']}&amount={$request['amount']}")
                    ]);


                return response()->json($enrollmentPaymentInit->json(),200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }


    public function intializeSubscriptionPayment (Request $request){

            $req = $request->all();

            try {

                $pharmacy = Pharmacy::where('id',$req['pharmacy_id'])->first();

                if (!$pharmacy){
                    return response()->json(['message'=>'Pharmacy record not found'],200);

                }

                /*todo fetch the user email and password and check for authentication before initializing payment*/

                $initalizeSubscriptionPayment = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                    ->post("https://api.paystack.co/transaction/initialize",[
                        'email' => $pharmacy->email,
                        'amount' => $req['amount'] *100,
                        'callback_url' => url("/api/subscriptionPaymentCallback?pharmacy_id={$pharmacy->id}&subscription_date={$request['subscription_date']}&subscription_name={$request['subscription_name']}&amount={$request['amount']}")
                    ]);



                return response()->json($initalizeSubscriptionPayment->json(),200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function enrollmentPaymentCallback (Request $request){

            $req = $request->all();

            try {

                return DB::transaction(function ()use($req,$request){
                    $confirmPayment = Http::withToken(env("PAYSTACK_SECRET_KEY"))
                        ->get("https://api.paystack.co/transaction/verify/{$req['reference']}");

                    /*        return response($confirmPayment);*/

                    if ( $confirmPayment['data']['status'] === 'success') {

                        $isTrial = $req['subscription_name'] === 'trial';



                        $pharmacyPayload =  [
                            'pharmacy_name' => $req['pharmacy_name'],
                            'reg_ID' => $req['reg_ID'],
                            'phone_no' => $req['pharmacy_phone_no'],
                            'email' => $req['pharmacy_email'],
                            'PIMA_ID' => Str::random(7),
                            'subscription_date' => $isTrial ? now()->addDays(15) : now()->addMonths($req['subscription_date']),
                            'subscription_name' => $req['subscription_name'],
                        ];


                        $pharmacy = Pharmacy::create($pharmacyPayload);


                        $branchPayload =  [
                            'branch_name' => $req['branch_name'],
                            'address' => $req['branch_address'],
                            'phone_no' => $req['branch_phone_no'],
                            'pharmacy_id' => $pharmacy->id,

                        ];


                        $branch = Branch::create($branchPayload);

                        $payload = [
                            'name' => $req['user_name'],
                            'email' => $req['user_email'],
                            'password' => Hash::make( $req['password']),
                            'user_role' => "admin",
                            'regID' => "ADMIN".Str::random(4),
                            'phone_no'=> $req['user_phone_no'],
                            'branch_id'=> $branch->id,
                            'address' => $req['user_address'],
                            'date_of_birth' => $req['dateOfBirth']


                        ];


                        $admin = User::create($payload);

                        /*create subscription record */

                        $subscriptionPayload = [
                          'pharmacy_id' => $pharmacy->id,
                          'subscription_name' => $req['subscription_name'],
                          'amount' =>   $req['amount'],
                          'duration' => $req['duration'],
                            'expiring_date'=> $pharmacy->subscription_date,
                        ];

                        $subsription = Subscription::create($subscriptionPayload);





                        return response()->json(['message'=>'','data'=> ['admin'=>$admin,'branch'=>$branch,'pharmacy'=>$pharmacy],'subscription'=>$subsription],200);


                    }  else{

                        /*todo redirect*/
                        return response()->json(['message'=>'Subscription Failed'],200);

                    }
                });




            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function subscriptionPaymentCallback (Request $request){

            $req = $request->all();

            try {
                DB::transaction(function ()use($req,$request){
                    $pharmacy = Pharmacy::where('id',$req['pharmacy_id'])->first();

                    /*if (!$pharmacy){
                        return response()->json(['message'=>'Pharmacy record not found'],200);

                    }*/

                    $confirmPayment = Http::withToken(env("PAYSTACK_SECRET_KEY"))
                        ->get("https://api.paystack.co/transaction/verify/{$req['reference']}");

                    /*        return response($confirmPayment);*/

                    if ( $confirmPayment['data']['status'] === 'success') {

                        if ($pharmacy->subscription_date < now()){
                            $pharmacy->update([
                                'subscription_date' => now()->addMonths($req['subscription']),
                                'subscription_name' => $req['subscription_name']
                            ]);
                        }else{
                            $pharmacy->update([
                                'subscription_date' => Carbon::parse($pharmacy->subscription_date)->addMonths($req['subscription']),
                                'subscription_name' => $req['subscription_name']
                            ]);
                        }

                        $pharmacy->refresh();

                        /*create the subscription record*/

                        $subscriptionPayload = [
                            'pharmacy_id' => $pharmacy->id,
                            'subscription_name' => $req['subscription_name'],
                            'amount' =>   $req['amount'],
                            'duration' => $req['duration'],
                            'expiring_date'=> $pharmacy->subscription_date,
                        ];

                        $subscription = Subscription::create($subscriptionPayload);




                        /*todo: redirect*/

                        return response()->json(['message'=>'Subscription Successful','data'=> ['pharmacy'=> $pharmacy, 'subscription'=> $subscription]],200);

                    }
                    else{

                        /*todo redirect*/
                        return response()->json(['message'=>'Subscription Failed'],200);

                    }
                });








            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }
}
