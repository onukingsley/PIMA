<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticationController extends Controller
{


    public function login (Request $request){

        $req = $request->all();

        $user = User::with('branch.pharmacy')->whereAny(['email','phone_no','regID'],$req['details'])->first();

        if ($user && Hash::check($req['password'],$user->password)){

            /* get the subscription date and check if it's due/active*/
            $dueDate = $user->branch->pharmacy->subscription_date;

            if ($dueDate < now()){
                return response()->json(['message'=>'Your Subscription has elapsed']);
            }


            auth()->login($user);

            $token = $user->createToken('auth_token')->plainTextToken;


            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Login Successful'
            ]);


        }
        else{
            return response()->json(['message'=>'Unauthorised User Credential']);

        }



    }


    public function register (Request $request){
        $req = $request->all();

        $pharmacy_id = $request->user()->branch->pharmacy->id;

        $pharmacy = Pharmacy::with(['branch','user','plan'])->where('id', $pharmacy_id)->first();

        if (!$pharmacy){
            return response()->json(['message'=>'No pharmacy Record Found'],200);

        }

        if ((count($pharmacy->user)+1) > $pharmacy->plan->max_user){
            return response()->json(['message'=>'Your User record has outgrown this Plan, Please upgrade to the next Plan'],200);
        }


        $idPrefix = [
            'sales'  => 'SID',
            'admin'  => 'ADMIN',
            'pharmasist'  => 'PHID',

        ];

        $payload = [
            'name' => $req['name'],
            'email' => $req['email'],
            'password' => Hash::make( $req['password']),
            'user_role' => $req['user_role'],
            'regID' => $idPrefix[$req['user_role']].Str::random(4),
            'phone_no'=> $req['phone_no'],
            'branch_id'=> $req['branch_id'],
            'address' => $req['address'],
            'date_of_birth' => $req['dateOfBirth']


        ];

        if ($request->hasFile('image')){
            $image = $request->file('image')->store('userImage','public');

            $payload['profile_image'] = $image;

        }
        try {
            $user = User::create($payload);

            $token = $user->createToken($user['email'])->plainTextToken;

            return response()->json(['message' => 'Registration Successful ', 'user' => $user,  'token'=>$token],200);




        }catch (\Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }

    }

    public function logout(Request $request){
        try {
            $user = $request->user();

            $user->currentAccessToken()->delete();



            return response()->json(['message' => 'Logged out Successfully']);


        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }
}
