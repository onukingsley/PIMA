<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\DrugSales;
use App\Models\Payment;
use App\Models\Restock;
use App\Models\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use mysql_xdevapi\Exception;
use Symfony\Component\Uid\UuidV7;

class SalesController extends Controller
{
    public function getSalesOverview(Request $request){

        $req = $request->all();

        /*get the dailysales, allDrugs,  allpendingrequest, lowstockdrug, outofstockdrug, all pending restock request*/

        $dailySales = Sales::with(['drugSale.drug','user'])->where('branch_id', $request->user()->branch->id)->whereDate('created_at',today())->get();

        $allDrugs = Drug::with(['drugBatch'])->where('branch_id', $request->user()->branch->id)->get();
        $availableDrugs = Drug::with([
            'drugBatch' => function($q){
                $q->whereNot('expiring_date', '<=' ,today());
            }

        ])->where('branch_id', $request->user()->branch->id)->whereIn('status',['lowStock','inStock'])->get();

        $lowStock = $allDrugs->where('quantity', '<', '30')->where('quantity', '>', '0');
        $outOfStock = $allDrugs->where('quantity', 0);

        $pendingRequest = $allDrugs->where('status','pendingApproval');

        $pendingRestock = Restock::with(['user','drug'])->where('branch_id', $request->user()->branch->id)->where('status','pending')->get();

        $almostExpiredDrug = DrugBatch::with(['drug'])->wherehas('drug', function ($q)use($request){
            $q->where('branch_id', $request->user()->branch->id);
        })->whereBetween('expiring_date',[today(),today()->addMonth(3)])->get();
        $expiredDrug = DrugBatch::with(['drug'])->wherehas('drug', function ($q)use($request){
            $q->where('branch_id', $request->user()->branch->id);
        })->where('expiring_date', '<=' ,today())->get();


        $data = [
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'allDrugs' => $allDrugs,
            'availableDrugs' => $availableDrugs,
            'pendingRequest' => $pendingRequest,
            'pendingRestock' => $pendingRestock,
            'dailySales' => $dailySales,
            'almostExpiredDrug' => $almostExpiredDrug,
            'expiredDrug' => $expiredDrug,
        ];

        return response()->json(['message'=> "Data has been fetched successfully", 'data'=>$data]);


    }

    public function getSalesDetails(Request $request){
        $req = $request->all();

        try {


            $patientPrescription = Sales::with(['drugSale.drug','drugSale.drugBatch','user','payment'])->where('id',$req['sales_id'])->first();


            if (!$patientPrescription){
                return response()->json(['message' => "No Record found for this Patient"],203);
            }

            return response()->json(['data' => $patientPrescription,'message'=>'User has been fetched successfully']);




        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    /*todo salesDrug function*/

    public function salesDrug (Request $request){

        $req = $request->all();

        try{
            return DB::transaction(function ()use($req,$request){

          /*      $payload = [
                    'user_id' => $request->user()->id,
                    'payment_id' => $req['payment_id']? $req['payment_id'] : Str::uuid(),
                    'total_amount' => $req['total_amount'],
                    'payment_status' => $req['payment_status'] ?? 'completed',
                    'delivery_status' => $req['delivery_status'] ?? 'delivered',
                ];

                $sales = Sales::create($payload);*/


                $paymentPayload = [
                    'title' => $req['title'] ?? "drug Sales for ". today(),
                    'description' => $req['description'] ?? "drug sales",
                    'payment_type' => 'DrugSale',
                    'user_id' => $request->user()->id ,
                    'invoice_id' => Str::uuid(),
                    'amount' => $req['total_amount'],
                    'status' => 'credit',
                ];


                $payment = Payment::create($paymentPayload);



                $payload = [
                    'user_id' => $request->user()->id,
                    'total_amount' => $req['total_amount'],
                    'payment_id' => $payment->id,
                    'payment_status' => $req['payment_status'] ?? 'completed',
                    'delivery_status' => $req['delivery_status'] ?? 'delivered',
                ];

                $sales = Sales::create($payload);


                foreach ($req['drug_items'] as $item){
                    $drugPayload = [
                        'sales_id' => $sales->id,
                        'drug_id' => $item['drug_stock_id'],
                        'quantity' => $item['quantity'],
                        'dosage' => $item['dosage'],
                        'duration' => $item['duration'],
                        'drug_batch_id' => $item['batch_id'],
                        'route' => $item['route'],
                        'instruction' => $item['instruction'],
                        'unit_price' => $item['unit_price'],

                    ];

                    DrugSales::create($drugPayload);
                }

                $sales->load('drugSale.drug', 'drugSale.drugBatch');

                $drugItem = $sales->drugSale;

                foreach ($drugItem as $item){

                    $drugName = $item->drug->name;

                    $drugQuan = $item->drug->quantity;
                    $drugBatchQuan = $item->drugBatch->quantity;
                    $salesQuan = $item->quantity;

                    if ($drugQuan - $salesQuan < 0){
                        throw new Exception("Insufficient Quantity for $drugName",401);
                    }
                    if ($drugQuan - $salesQuan == 0){
                        $item->drug()->update([
                            'status' => 'outOfStock'
                        ]);
                    }
                    else if ($drugQuan - $salesQuan  <= 30){
                        $item->drug()->update([
                            'status' => 'lowStock'
                        ]);
                    }else if ($drugQuan - $salesQuan  > 30){
                        $item->drug()->update([
                            'status' => 'inStock'
                        ]);
                    }

                    $item->drug()->update([
                        'quantity' => $drugQuan - $salesQuan
                    ]);

                    $item->drugBatch()->update([
                        'quantity' => $drugBatchQuan - $salesQuan
                    ]);


                }
                $sales->refresh();

                $fullSales = Sales::with(['drugSale.drug','drugSale.drugBatch','payment','user'])->where('id',$sales->id)->first();




                return response()->json(['message'=>'Prescription has been dispensed to the patient','data'=>['sales'=>$fullSales]],200);


            });
        }catch (Exception $exception){
            return response()->json(['message'=> "Data has been fetched successfully"]);

        }


    }
    public function salesDrugUpdated (Request $request){

        $req = $request->all();

        try{
            return DB::transaction(function ()use($req,$request){

                /* add payment record*/

                if (count($req['drug_items']) == 0){
                    return response()->json(['message'=>'Prescription is Empty'],200);

                }

                if (Hash::check($req['password'],$request->user()->password)){
                    $paymentPayload = [
                        'title' => $req['title'] ?? "drug Sales for ". today(),
                        'description' => $req['description'] ?? "drug sales",
                        'payment_type' => 'DrugSale',
                        'user_id' => $request->user()->id ,
                        'invoice_id' => Str::uuid(),
                        'branch_id' => $request->user()->branch->id ,
                        'amount' => $req['total_amount'],
                        'status' => 'credit',
                    ];

                    $payment = Payment::create($paymentPayload);



                    $payload = [
                        'user_id' => $request->user()->id,
                        'total_amount' => $req['total_amount'],
                        'payment_id' => $payment->id,
                        'branch_id' => $request->user()->branch->id ,
                        'payment_status' => $req['payment_status'] ?? 'completed',
                        'delivery_status' => $req['delivery_status'] ?? 'delivered',
                    ];

                    $sales = Sales::create($payload);





                    foreach ($req['drug_items'] as $item){

                        $singleDrug = Drug::where('id',$item['drug_stock_id'])->lockForUpdate()->first();
                        $batch = DrugBatch::where('id',$item['batch_id'])->lockForUpdate()->first();

                        $newDrugQuantity = $singleDrug->quantity - $item['quantity'];
                        $newBatchQuantity = $batch->quantity - $item['quantity'];

                        if ($batch['quantity'] < $item['quantity']){
                            throw new \Exception('This drug is outOfStock');
                        }

                        $status = $singleDrug->status;

                        if ($newDrugQuantity == 0){
                            $status = 'OutOfStock';
                        }
                        else if ($newDrugQuantity  <= 30){
                            $status = 'lowStock';
                        }

                        $drugPayload = [
                            'sales_id' => $sales->id,
                            'drug_id' => $item['drug_stock_id'],
                            'drug_batch_id' => $item['batch_id'],
                            'quantity' => $item['quantity'],
                            'dosage' => $item['dosage'],
                            'duration' => $item['duration'],
                            'route' => $item['route'],
                            'instruction' => $item['instruction'],
                            'unit_price' => $item['unit_price'],

                        ];

                        DrugSales::create($drugPayload);

                        $singleDrug->update([
                            'status' => $status,
                            'quantity'=> $newDrugQuantity
                        ]);

                        $batch->update([
                            'quantity'=> $newBatchQuantity
                        ]);

                    }


                    $sales->refresh();

                    $fullSales = Sales::with(['drugSale.drug','drugSale.drugBatch','payment','user'])->where('id',$sales->id)->first();

                    return response()->json(['message'=>'Prescription has been dispensed to the patient','data'=>['sales'=>$fullSales]],200);

                }
                else{
                    return response()->json(['message'=>'Unauthorised user access'],200);

                }





            });
        }catch (Exception $exception){
            return response()->json(['message'=> "Data has been fetched successfully"]);

        }


    }

    public function newDrugRequest (Request $request){

            $req = $request->all();

            try {

                $payload = [
                  'category_id' => $req['category_id'] ?? 1 ,
                  'name' => $req['name'],
                  'dosage' => $req['dosage'],
                  'generic' => $req['generic'],
                  'quantity' => $req['quantity'],
                  'branch_id' => $request->user()->branch->id ,
                  'description' => $req['description'],
                ];

                $newDrug = Drug::create($payload);


                return response()->json(['message'=>'Drug has been sent for approval','data'=> $newDrug],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function restockRequest (Request $request){

            $req = $request->all();

            try {

                $payload = [
                  'drug_id' => $req['drug_id'],
                  'user_id' => $request->user()->id,
                  'quantity' => $req['quantity'],
                  'title' => $req['title'],
                  'branch_id' => $request->user()->branch->id ,
                  'notes' => $req['notes'],
                ];

                $restock = Restock::create($payload);



                return response()->json(['message'=>'Request has been forwarded to your supervisor for approval','data'=> $restock],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

}
