<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SuperAdminController extends Controller
{
    public function getPharmacy (Request $request){

            $req = $request->all();

            try {

                /*get Pharmacy by regID or pharmacy name and load all models*/

                $pharmacy = Pharmacy::with(['plan','users','branch'=>function($q){
                    $q->with(['user','sales','payment','drug','restock']);
                }])->whereAny(['reg_ID','pharmacy_name'], $req['detail'])->get();




                $user = User::with('branch')->whereHas('branch.pharmacy',function ($q)use($req,$request){
                    $q->whereAny(['reg_ID','pharmacy_name'], $req['detail']);
                })->get();


                return response()->json(['message'=>'pharmacy Fetched Successfully','data'=> ['user'=>$user,'pharmacy'=>$pharmacy]],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }
    public function getPlan (Request $request){

            $req = $request->all();

            try {

                /*get Pharmacy by regID or pharmacy name and load all models*/

                $plan = Plan::where('plan_name','!=', 'trial')->get();




                return response()->json(['message'=>'Plan Fetched Successfully','data'=> ['plan'=>$plan]],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }
    public function getOnBoardingPlan (Request $request){

            $req = $request->all();

            try {

                /*get Pharmacy by regID or pharmacy name and load all models*/

                $plan = Plan::all();




                return response()->json(['message'=>'Plan Fetched Successfully','data'=> ['plan'=>$plan]],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function createPharmacy (Request $request){

            $req = $request->all();

            return DB::transaction(function () use($req,$request){
                try {

                    /*create pharmacy, create branch, create Admin*/

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







                    return response()->json(['message'=>'','data'=> $pharmacy],200);

                }catch (Throwable $e) {
                    report($e);

                    // Handle race condition on unique constraints
                    if ($e instanceof \Illuminate\Database\QueryException && $e->getCode() === '23000') {
                        return response()->json([
                            'message' => 'One of the unique fields is already taken.',
                        ], 409);
                    }
                }
            });


    }

    public function addSubscription (Request $request){

            $req = $request->all();

            try {

                $pharmacy = Pharmacy::where('id',$req['pharmacy_id'])->first();

                if (!$pharmacy){
                    return response()->json(['message'=>'Pharmacy record not found'],200);

                }


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





                return response()->json(['message'=>'Subscription Successful','data'=> $pharmacy],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function addBranch (Request $request){

            $req = $request->all();

            try {

                $pharmacy = Pharmacy::with(['branch','user','plan'])->where('id', $req['id'])->first();

                if (!$pharmacy){
                    return response()->json(['message'=>'No pharmacy Record Found'],200);

                }

                if ((count($pharmacy->branch)+1) > $pharmacy->plan->max_branch){
                    return response()->json(['message'=>'Your Business has outgrown this Plan, Please upgrade to the next Plan'],200);
                }

                $payload = [
                    'branch_name' => $req['branch_name'],
                    'address' => $req['address'],
                    'pharmacy_id' => $pharmacy->id,
                    'phone_no' => $req['phone_no'],
                ];

                $branch  = Branch::create($payload);

                return response()->json(['message'=>'New branch Created successfully','data'=> $branch],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function deleteBranch (Request $request){

            $req = $request->all();

            try {
                $branch = branch::with(['pharmacy'])->where('id', $req['id'])->first();

                if (!$branch){
                    return response()->json(['message'=>'No branch Record Found'],200);

                }
                if ($request->user()->branch->pharmacy !== $branch->pharmacy->id && $request->user()->user_role !== 'admin'){
                    return response()->json(['message'=>'You are not authorised to delete a Branch!'],200);

                }

                $branch->delete();



                return response()->json(['message'=>'Branch Record Deleted Successfully!!'],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function editBranch (Request $request){

            $req = $request->all();

            try {
                $branch = branch::with(['pharmacy'])->where('id', $req['id'])->first();

                if (!$branch){
                    return response()->json(['message'=>'No branch Record Found'],200);

                }
                if ($request->user()->branch->pharmacy !== $branch->pharmacy->id && $request->user()->user_role !== 'admin'){
                    return response()->json(['message'=>'You are not authorised to edit this Branch!'],200);

                }

                $branch->update($req);

                $branch->refresh();

                return response()->json(['message'=>'branch has been updated successfully','data'=> $branch],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function editPharmamcy (Request $request){

        $req = $request->all();

        try {
            $pharmacy = Pharmacy::with(['branch'])->where('id', $req['id'])->first();

            if (!$pharmacy){
                return response()->json(['message'=>'No Pharmacy Record Found'],200);

            }
            if ($request->user()->branch->pharmacy !== $pharmacy->id && $request->user()->user_role !== 'admin'){
                return response()->json(['message'=>'You are not authorised to edit this pharmacy!'],200);

            }

            $pharmacy->update($req);

            $pharmacy->refresh();

            return response()->json(['message'=>'branch has been updated successfully','data'=> $pharmacy],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

}
