<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\Plan;
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

                $plan = Plan::where('id',$req['plan_id'])->first();

                if ( $plan->plan_name === 'trial'){
                    $pharmacyPayload =  [
                        'pharmacy_name' => $req['pharmacy_name'],
                        'reg_ID' => $req['reg_ID'],
                        'phone_no' => $req['pharmacy_phone_no'],
                        'email' => $req['pharmacy_email'],
                        'PIMA_ID' => Str::random(7),
                        'subscription_date' =>  now()->addDays(15) ,
                        'subscription_name' => $plan->plan_name,
                        'plan_id' => $req['plan_id'],
                        'subscription_amount' => $plan->plan_amount,

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
                        'date_of_birth' => $req['dateOfBirth'],
                        'license_no' => $req['reg_ID'],
                        'specialization' => 'pharmacy',



                    ];


                    $admin = User::create($payload);

                    $subscriptionPayload = [
                        'pharmacy_id' => $pharmacy->id,
                        'subscription_name' => $plan->plan_name,
                        'amount' =>   $plan->plan_amount,
                        'duration' => $plan->duration,
                        'expiring_date'=> $pharmacy->subscription_date,
                        'plan_id' => $req['plan_id']

                    ];

                    $subsription = Subscription::create($subscriptionPayload);



                    return redirect(env('FRONT_END_URL')."/onboarding?pharmacy_regID=$pharmacy->reg_ID&pharmacy_id=$pharmacy->id&plan_id={$plan->id}&message=success");


                    return response()->json(['message'=>'Your Free SignUp has been Activated','data'=> $pharmacy],200);
                }



                $enrollmentPaymentInit = HTTP::withToken(env('PAYSTACK_SECRET_KEY'))
                    ->post("https://api.paystack.co/transaction/initialize",[
                        'email' => $req['pharmacy_email'],
                        'amount' => $plan['plan_amount'] *100,
                        'callback_url' => url("/api/enrollmentPaymentCallback?plan_id={$req['plan_id']}&pharmacy_name={$req['pharmacy_name']}&reg_ID={$request['reg_ID']
                        }&pharmacy_phone_no={$request['pharmacy_phone_no']}&pharmacy_email={$request['pharmacy_email']}&subscription_date={$plan['duration']
                        }&subscription_name={$plan['plan_name']}&branch_phone_no={$req['branch_phone_no']}&branch_name={$request['branch_name']}&branch_address={$request['branch_address']
                        }&user_name={$request['user_name']}&user_email={$request['user_email']}&password={$request['password']}&user_phone_no={$request['user_phone_no']
                        }&user_address={$request['user_address']}&dateOfBirth={$request['dateOfBirth']}&amount={$plan['plan_amount']}")
                    ]);


                return response()->json($enrollmentPaymentInit->json(),200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }


    public function initializeSubscriptionPayment (Request $request){

            $req = $request->all();

            try {

                $pharmacy = Pharmacy::with(['plan','users','branch'])->where('id',$req['pharmacy_id'])->first();

                if (!$pharmacy){
                    return response()->json(['message'=>'Pharmacy record not found'],200);

                }


                $plan = Plan::where('id',$req['plan_id'])->first();

                if (($pharmacy->branch->count()+1) > $plan->max_branch){
                    return response()->json(['message'=>'Your Business has outgrown this Plan, Please upgrade to the next Plan'],200);
                }
                if (($pharmacy->users->count() + 1) > $plan->max_user){
                    return response()->json(['message'=>'Your Staff record has outgrown this Plan, Please upgrade to the next Plan'],200);
                }


                /*todo fetch the user email and password and check for authentication before initializing payment*/

                $initalizeSubscriptionPayment = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                    ->post("https://api.paystack.co/transaction/initialize",[
                        'email' => $pharmacy->email,
                       /* 'amount' => $req['amount'] *100,*/
                        'amount' => $plan->plan_amount *100,
                        'callback_url' => url("/api/subscriptionPaymentCallback?pharmacy_id={$pharmacy->id}&subscription_date={$plan->duration}&subscription_name={$plan->plan_name}&amount={$plan->plan_amount}&plan_id={$plan->id}")
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

                        $planID = $req['plan_id'];
                        $isTrial = $req['subscription_name'] === 'trial';



                        $pharmacyPayload =  [
                            'pharmacy_name' => $req['pharmacy_name'],
                            'reg_ID' => $req['reg_ID'],
                            'phone_no' => $req['pharmacy_phone_no'],
                            'email' => $req['pharmacy_email'],
                            'PIMA_ID' => Str::random(7),
                            'subscription_date' => $isTrial ? now()->addDays(15) : now()->addMonths((int)$req['subscription_date']),
                            'subscription_name' => $req['subscription_name'],
                            'subscription_amount' => $req['amount'],
                            'plan_id' => $planID,
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
                            'date_of_birth' => $req['dateOfBirth'],
                            'license_no' => $req['reg_ID'],
                            'specialization' => 'pharmacy',


                        ];


                        $admin = User::create($payload);

                        /*create subscription record */

                        $subscriptionPayload = [
                          'pharmacy_id' => $pharmacy->id,
                          'subscription_name' => $req['subscription_name'],
                          'amount' =>   $req['amount'],
                          'duration' => $req['subscription_date'],
                            'expiring_date'=> $pharmacy->subscription_date,
                            'plan_id' => $req['plan_id']

                        ];

                        $subsription = Subscription::create($subscriptionPayload);





                        return redirect(env('FRONT_END_URL')."/onboarding?pharmacy_regID=$pharmacy->reg_ID&pharmacy_id=$pharmacy->id&plan_id=$planID&message=success");


                        return response()->json(['message'=>'','data'=> ['admin'=>$admin,'branch'=>$branch,'pharmacy'=>$pharmacy],'subscription'=>$subsription],200);


                    }  else{
                        return redirect(env('FRONT_END_URL')."/onboarding?pharmacy_regID={$req['reg_ID']}&message=failed");

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
                return DB::transaction(function ()use($req,$request){

                    $planID = $req['plan_id'];
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
                                'subscription_date' => now()->addMonths((int)$req['subscription_date']),
                                'subscription_name' => $req['subscription_name'],
                                'subscription_amount' => $req['amount'],
                                'plan_id' => $req['plan_id']
                            ]);
                        }else{
                            $pharmacy->update([
                                'subscription_date' => Carbon::parse($pharmacy->subscription_date)->addMonths((int)$req['subscription_date']),
                                'subscription_name' => $req['subscription_name'],
                                'subscription_amount' => $req['amount'],
                                'plan_id' => $req['plan_id']
                            ]);
                        }



                        $pharmacy->refresh();

                        /*create the subscription record*/

                        $subscriptionPayload = [
                            'pharmacy_id' => $pharmacy->id,
                            'subscription_name' => $req['subscription_name'],
                            'amount' =>   $req['amount'],
                            'duration' => $req['subscription_date'],
                            'plan_id' => $req['plan_id'],
                            'expiring_date'=> $pharmacy->subscription_date,
                        ];

                        $subscription = Subscription::create($subscriptionPayload);






                        return redirect(env('FRONT_END_URL')."/subscription?pharmacy_regID=$pharmacy->reg_ID&pharmacy_id=$pharmacy->id&plan_id=$planID&message=success");

                        /*return response()->json(['message'=>'Subscription Successful','data'=> ['pharmacy'=> $pharmacy, 'subscription'=> $subscription]],200);*/

                    }
                    else{
                        return redirect(env('FRONT_END_URL')."/subscription?pharmacy_regID=$pharmacy->reg_ID&pharmacy_id=$pharmacy->id&message=failed");

                        /*todo redirect*/
                        /*return response()->json(['message'=>'Subscription Failed'],200);*/

                    }
                });








            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }
}
