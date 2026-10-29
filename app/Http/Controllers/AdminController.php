<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\DrugSales;
use App\Models\Payment;
use App\Models\Restock;
use App\Models\Sales;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use mysql_xdevapi\Exception;

class AdminController extends Controller
{
    public function getAdminOverview(Request $request){

        $req = $request->all();






        /*get the dailysales, allDrugs,  allpendingrequest, lowstockdrug, outofstockdrug, all pending restock request*/



        $allDrugs = Drug::with(['drugBatch'])->where('branch_id', $request->user()->branch->id)->get();

        $lowStock = $allDrugs->where('quantity', '<', '30');
        $outOfStock = $allDrugs->where('quantity', 0);

        $pendingRequest = $allDrugs->where('status','pending');

        $allRestock = Restock::with(['user','drug'])->get();
        $pendingRestock = $allRestock->where('status','pending');

        $almostExpiredDrug = DrugBatch::with(['drug'])->wherehas('drug', function ($q)use($request){
            $q->where('branch_id', $request->user()->branch->id);
        })->whereBetween('expiring_date',[today(),today()->addMonth(3)])->get();
        $expiredDrug = DrugBatch::with(['drug'])->wherehas('drug', function ($q)use($request){
            $q->where('branch_id', $request->user()->branch->id);
        })->where('expiring_date', '<=' ,today())->get();



        if (isset($req['start_date']) || isset($req['end_date'])){
            $startDate = Carbon::parse($req['start_date'])->startOfDay();
            $endDate = Carbon::parse($req['end_date'])->endOfDay();

            $drugStock = Drug::with(['sales' => function($q) use($startDate,$endDate,$request){
                $q->whereBetween('sales.created_at',[$startDate, $endDate])->where('branch_id', $request->user()->branch->id);
            }])->where('branch_id', $request->user()->branch->id)->get();



            $payments = Payment::with(['user','sales.drugSale'])->where('branch_id', $request->user()->branch->id)->whereBetween('created_at',[$startDate,$endDate])->get();

            $sales = Sales::with(['drugSale.drug'])->where('branch_id', $request->user()->branch->id)->whereBetween('created_at',[$startDate,$endDate])->get();

            /*$expenses = Payment::with(['user'])->where('status','debit')->whereBetween('created_at',[$startDate,$endDate])->get();
            $revenue = Payment::with(['sales.drugSale','user'])->where('status','credit')->where('status','debit')->whereBetween('created_at',[$startDate,$endDate])->get();*/

            $expenses = $payments->where('status','debit');
            $revenue = $payments->where('status','credit');


            $rankingDrugs = $drugStock;

            $drugSalesCount= [];
            $drugRevenue = [];
            $drugLeft = [];
            $drugQuantity = [];


            foreach ($rankingDrugs as $drug){
                $drugSalesCount = [...$drugSalesCount, [
                    'name' => $drug['name'],
                    'no_of_sales' => $drug->sales->count(),

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugRevenue = [...$drugRevenue, [
                    'name' => $drug['name'],
                    /*'revenue' => $drug->sales[0]->pivot->sum(function ($sale){
                        return $sale->unit_price * $sale->quantity;
                    }),*/
                    'revenue' => $drug->sales->sum(function ($sale){
                        return $sale->pivot->unit_Price * $sale->pivot->quantity;
                    })

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugLeft = [...$drugLeft, [
                    'name' => $drug['name'],
                    'stock' => $drug->quantity,

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugQuantity = [...$drugQuantity, [
                    'name' => $drug['name'],
                    'quantity_sold' => $drug->sales->sum('pivot.quantity'),
                ]];
            }

            $drugSalesCollection = collect($drugSalesCount);
            $drugRevenueCollection = collect($drugRevenue);
            $drugLeftCollection = collect($drugLeft);
            $drugQuantityCollection = collect($drugQuantity);

            $sortedNumberOfSales = $drugSalesCollection->sortByDesc('value')->take(5);
            $sortedRevenue = $drugRevenueCollection->sortByDesc('revenue')->take(5);
            $sortedLeft = $drugLeftCollection->sortBy('stock')->take(5);
            $sortedQuantity = $drugQuantityCollection->sortByDesc('quantity_sold')->take(5);


            $pnl = $payments->sortBy('created_at')->groupBy(function ($item){
                return $item->created_at->format('d-M');
            })->map(function ($items){
                return [
                    'revenue' => $items->where('status','credit')->sum('amount'),
                    'expenses' => $items->where('status','debit')->sum('amount')
                ];
            });

            $drugPnl = $payments->sortBy('created_at')->groupBy(function ($item){
                return $item->created_at->format('d-M');
            })->map(function ($items){
                return [
                    'revenue' => $items->where('status','credit')->where('payment_type','drugSales')->sum('amount'),
                    'expenses' => $items->where('status','debit')->where('payment_type','drugStock')->sum('amount')
                ];
            });

            $areaChat = [];

            $drugAreaChat = [];

            foreach ($pnl as $item => $value){
                $areaChat = [...$areaChat, [
                    'date' => $item,
                    'expenses' => $value['expenses'],
                    'revenue' => $value['revenue']
                ]];
            }

            foreach ($drugPnl as $item => $value){
                $drugAreaChat = [...$drugAreaChat, [
                    'date' => $item,
                    'expenses' => $value['expenses'],
                    'revenue' => $value['revenue']
                ]];
            }


            $data = [
                'lowStock' => $lowStock,
                'outOfStock' => $outOfStock,
                'allDrugs' => $allDrugs,
                'pendingRequest' => $pendingRequest,
                'pendingRestock' => $pendingRestock,
                'sales' => $sales,

                '$allRestock' => $allRestock,
                '$almostExpiredDrug' => $almostExpiredDrug,
                '$expiredDrug' => $expiredDrug,
                '$totalRevenue' => $revenue->sum('amount'),
                '$revenue' => $revenue,
                '$totalExpenses' => $expenses->sum('amount'),
                '$expenses' => $expenses,


                'drugSalesChart' => $sortedNumberOfSales,
                'drugRevenueChart' => $sortedRevenue,
                'drugRemainingChart' => $sortedLeft,
                'drugQuantitySoldChart' => $sortedQuantity,

                'drugPnlAreaChart' => $drugAreaChat,
                'pnlAreaChart' => $areaChat,



            ];
            /*todo: add all the chart fields to the data*/


            return response()->json(['message'=> "Data has been fetched successfully", 'data'=>$data]);




        } else{
            $drugStock = Drug::with(['sales' => function($q) use($request) {
                $q->where('payment_status','paid')->where('branch_id', $request->user()->branch->id)->whereYear('sales.created_at', now()->year)->whereMonth('sales.created_at', now()->month);
            }])->get();


            $payments = Payment::with(['user','sales.drugSale'])->where('branch_id', $request->user()->branch->id)->whereYear('created_at',now()->year)->whereMonth('created_at', now()->month)->get();
            $sales = Sales::with(['drugSale.drug'])->where('branch_id', $request->user()->branch->id)->whereYear('created_at',now()->year)->whereMonth('created_at', now()->month)->get();


           /* $expenses = Payment::with(['user'])->where('status','debit')->whereYear('created_at',now()->year)->whereMonth('created_at', now()->month)->get();
            $revenue = Payment::with(['sales.drugSale','user'])->where('status','credit')->where('status','debit')->whereYear('created_at',now()->year)->whereMonth('created_at', now()->month)->get();*/


            $expenses = $payments->where('status','debit');
            $revenue = $payments->where('status','credit');


            $rankingDrugs = $drugStock;

            $drugSalesCount= [];
            $drugRevenue = [];
            $drugLeft = [];
            $drugQuantity = [];


            foreach ($rankingDrugs as $drug){
                $drugSalesCount = [...$drugSalesCount, [
                    'name' => $drug['name'],
                    'no_of_sales' => $drug->sales->count(),

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugRevenue = [...$drugRevenue, [
                    'name' => $drug['name'],
                    /*'revenue' => $drug->sales[0]->pivot->sum(function ($sale){
                        return $sale->unit_price * $sale->quantity;
                    }),*/
                    'revenue' => $drug->sales->sum(function ($sale){
                        return $sale->pivot->unit_Price * $sale->pivot->quantity;
                    })

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugLeft = [...$drugLeft, [
                    'name' => $drug['name'],
                    'stock' => $drug->quantity,

                ]];
            }
            foreach ($rankingDrugs as $drug){
                $drugQuantity = [...$drugQuantity, [
                    'name' => $drug['name'],
                    'quantity_sold' => $drug->sales->sum('pivot.quantity'),
                ]];
            }

            $drugSalesCollection = collect($drugSalesCount);
            $drugRevenueCollection = collect($drugRevenue);
            $drugLeftCollection = collect($drugLeft);
            $drugQuantityCollection = collect($drugQuantity);

            $sortedNumberOfSales = $drugSalesCollection->sortByDesc('value')->take(5);
            $sortedRevenue = $drugRevenueCollection->sortByDesc('revenue')->take(5);
            $sortedLeft = $drugLeftCollection->sortBy('stock')->take(5);
            $sortedQuantity = $drugQuantityCollection->sortByDesc('quantity_sold')->take(5);


        }

        $pnl = $payments->sortBy('created_at')->groupBy(function ($item){
            return $item->created_at->format('d-M');
        })->map(function ($items){
            return [
                'revenue' => $items->where('status','credit')->sum('amount'),
                'expenses' => $items->where('status','debit')->sum('amount')
            ];
        });

        $drugPnl = $payments->sortBy('created_at')->groupBy(function ($item){
            return $item->created_at->format('d-M');
        })->map(function ($items){
            return [
                'revenue' => $items->where('status','credit')->where('payment_type','drugSales')->sum('amount'),
                'expenses' => $items->where('status','debit')->where('payment_type','drugStock')->sum('amount')
            ];
        });

        $areaChat = [];

        $drugAreaChat = [];

        foreach ($pnl as $item => $value){
            $areaChat = [...$areaChat, [
                'date' => $item,
                'expenses' => $value['expenses'],
                'revenue' => $value['revenue']
            ]];
        }

        foreach ($drugPnl as $item => $value){
            $drugAreaChat = [...$drugAreaChat, [
                'date' => $item,
                'expenses' => $value['expenses'],
                'revenue' => $value['revenue']
            ]];
        }


        $data = [
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock,
            'allDrugs' => $allDrugs,
            'pendingRequest' => $pendingRequest,
            'pendingRestock' => $pendingRestock,
            'sales' => $sales,

            '$allRestock' => $allRestock,
            '$almostExpiredDrug' => $almostExpiredDrug,
            '$expiredDrug' => $expiredDrug,
            '$totalRevenue' => $revenue->sum('amount'),
            '$revenue' => $revenue,
            '$totalExpenses' => $expenses->sum('amount'),
            '$expenses' => $expenses,


            'drugSalesChart' => $sortedNumberOfSales,
            'drugRevenueChart' => $sortedRevenue,
            'drugRemainingChart' => $sortedLeft,
            'drugQuantitySoldChart' => $sortedQuantity,

            'drugPnlAreaChart' => $drugAreaChat,
            'pnlAreaChart' => $areaChat,



        ];
        /*todo: add all the chart fields to the data*/


        return response()->json(['message'=> "Data has been fetched successfully", 'data'=>$data]);


    }


    public function adminSalesDrug (Request $request){

        $req = $request->all();

        try{
            return DB::transaction(function ()use($req,$request){

                /* add payment record*/

                $paymentPayload = [
                    'title' => $req['title'],
                    'description' => $req['description'],
                    'payment_type' => 'DrugSale',
                    'user_id' => $request->user()->id,
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

                    $singleDrug = Drug::where('id',$item['id'])->lockForUpdate()->first();
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
                        'drug_id' => $item['id'],
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


            });
        }catch (Exception $exception){
            return response()->json(['message'=> "Data has been fetched successfully"]);

        }


    }


    public function adminRestockApproval (Request $request){

        $req = $request->all();

        try {
            $drugRestock = Restock::with(['drug','user','payment'])->where('id',$req['restock_id'])->first();

            if ($req['status'] == 'approved'){
                $paymentPayload = [
                    'title' => $req['title'],
                    'user_id' => $request->user()->id,
                    'description' => $req['description'],
                    'payment_type' => 'drugStock',
                    'branch_id' => $request->user()->branch->id ,
                    'invoice_id' => $req['invoice_id'],
                    'amount' => $req['amount'],
                    'status' => 'debit',
                ];

                $payment = Payment::create($paymentPayload);


                $drugRestock->update([
                    'quantity' => $req['quantity'] ,
                    'unit_price' => $req['unit_price'],
                    'status' => 'approved'
                ]);
            }else{

                $drugRestock->update([
                    'status' => 'rejected'
                ]);
                return response()->json(['message'=>'Drug Request was rejected and wasnt taken into inventory'],200);

            }

            return response()->json(['message'=>'Drug Request has been approved recorded in inventory','data'=> ''],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }


    }

    public function adminApproveNewDrug (Request $request){

        $req = $request->all();

        try {
            $pendingDrug = Drug::with('drugBatch')->where('id',$req['drug_id'])->first();

            $newQuantity =  $pendingDrug->quantity + $req['quantity'];


            if ($req['status'] == 'approved'){

                $pendingDrug->update([
                    'status' => $req['status'],
                    'quantity' => $newQuantity,
                    'amount' => $req['amount'],

                ]);

                $paymentPayload = [
                    'title' => $req['title'],
                    'user_id' => $request->user()->id,
                    'description' => $req['description'],
                    'payment_type' => 'drugStock',
                    'branch_id' => $request->user()->branch->id ,
                    'invoice_id' => $req['invoice_id'],
                    'amount' => $req['amount'] * $req['quantity'],
                    'status' => 'debit',
                ];

                $payment = Payment::create($paymentPayload);


                $batchPayload = [
                    'title' => $req['title'],
                    'batch_no' => \str($pendingDrug->name)->substr(0,4).Str::random(3),
                    'cost_price' => $req['amount'],
                    'selling_price' => $req['selling_price'] ?? $req['amount'] + $req['amount']*0.4 ,
                    'expiring_date' => $req['expiring_date'],
                    'quantity' => $req['quantity'],
                    'drug_id' => $pendingDrug->id,
                ];

                $newDrugBatch = DrugBatch::create($batchPayload);
                $pendingDrug->refresh();

                return response()->json(['message'=>'New Drug has been Approved and New Batch Created','data'=> [
                    'newDrugBatch' => $newDrugBatch,
                    '$payment' => $payment,
                    'pendingDrug' => $pendingDrug,
                ]],200);



            }else{

                $pendingDrug->update([
                    'status' => 'rejected',
                    'quantity' => 0,
                    'amount' => 0,

                ]);


                return response()->json(['message'=>'New Drug Request was rejected and quantity nullified','data'=> ''],200);
            }





        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    public function adminPaymentEntry (Request $request){

        $req = $request->all();

        try {

            $paymentPayload = [
                'title' => $req['title'],
                'user_id' => $request->user()->id,
                'description' => $req['description'],
                'payment_type' => $req['payment_type'],
                'invoice_id' => $req['invoice_id'],
                'branch_id' => $request->user()->branch->id ,
                'amount' => $req['amount'],
                'status' => $req['status'],
            ];

            $payment = Payment::create($paymentPayload);



            return response()->json(['message'=>'Payment entry has been made successfully','data'=> $payment],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    public function adminEditPayment (Request $request){

        $req = $request->all();

        try {

            $payment = Payment::with(['user'])->whereAny(['id','invoice_id'],$req['payment_detail'])->first();

            /*check if payment with given id exists? */
            if (!$payment){

                return response()->json(['message'=>'No such payment Record was Found' ],200);

            }

            $payment->update([
                'description' => $req['description'],
                'title' => $req['title'],
                'payment_type' => $req['payment_type'],
                'amount' => $req['amount'],
                'status' => $req['status'],
                'completion_status' => $req['completion_status'],
                'outStanding_balance' => $req['outStanding_balance'],
            ]);

            $payment->refresh();

            return response()->json(['message'=>'Payment Record was Edited Successfully','data'=> $payment ],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    public function updateDrugPrice (Request $request){

        $req = $request->all();

        try {

            $batch = DrugBatch::with(['drug'])->where('id',$req['batch_id'])->first();

            if (!$batch){

                return response()->json(['message'=>'No Batch Record was Found ' ],200);

            }

            $batch->update([
                'selling_price' => $req['selling_price'],
                'cost_price' => $req['cost_price'],
                'expiring_date' => $req['expiring_date'] ?? $batch->expiring_date,
                'quantity' => $req['quantity'] ?? $batch->quantity,
            ]);



            if ($req['change_price'] === true){
                $batch->drug()->update([
                    'amount' => $req['selling_price']
                ]);
            }

            $batch->refresh();

            return response()->json(['message'=>'','data'=> ''],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }



    public function adminNewDrugRequest (Request $request){

        $req = $request->all();

        try {

            $payload = [
                'category_id' => $req['category_id'] ?? "N/A",
                'name' => $req['name'],
                'dosage' => $req['dosage'],
                'generic' => $req['generic'],
                'quantity' => $req['quantity'],
                'branch_id' => $request->user()->branch->id ,
                'description' => $req['description'],
                'status' => $req['status'],
                'amount' => $req['amount'],
            ];

            $newDrug = Drug::create($payload);

            /* new payment record entry*/
            $paymentPayload = [
                'title' => $req['title'],
                'user_id' => $request->user()->id,
                'description' => $req['description'],
                'payment_type' => 'drugStock',
                'invoice_id' => $req['invoice_id'],
                'branch_id' => $request->user()->branch->id ,
                'amount' => $req['amount'] * $req['quantity'],
                'status' => 'debit',
            ];

            $payment = Payment::create($paymentPayload);

            $batchPayload = [
                'title' => $req['title'],
                'batch_no' => \str($newDrug->name)->substr(0,4).Str::random(3),
                'cost_price' => $req['amount'],
                'selling_price' => $req['selling_price'] ?? $req['amount'] + $req['amount']*0.4 ,
                'expiring_date' => $req['expiring_date'],
                'quantity' => $req['quantity'],
                'drug_id' => $newDrug->id,
            ];

            $newDrugBatch = DrugBatch::create($batchPayload);


            return response()->json(['message'=>'New Drug has been created and New Batch Created','data'=> [
                'newDrugBatch' => $newDrugBatch,
                '$payment' => $payment,
                'newDrug' => $newDrug,
            ]]);


        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    public function adminAddDrugBatch (Request $request){

        $req = $request->all();

        try {

            $batchNo = 'BCH'.Str::random(3);
            foreach ($req['drugBatchList'] as $item){

                $batchPayload = [
                    'title' => $item['title'],
                    'batch_no' => $batchNo,
                    'cost_price' => $item['amount'],
                    'selling_price' => $item['selling_price'] ?? $item['amount'] + $item['amount']*0.4 ,
                    'expiring_date' => $item['expiring_date'],
                    'quantity' => $item['quantity'],
                    'drug_id' => $item['id'],
                ];

                $newDrugBatch = DrugBatch::create($batchPayload);

                $newDrugBatch->load(['drugs']);


                $newDrugBatch->drug()->increment('quantity',$item['quantity']);


            }

            $paymentPayload = [
                'title' => $req['title'],
                'user_id' => $request->user()->id,
                'description' => $req['description'],
                'payment_type' => 'drugStock',
                'invoice_id' => $req['invoice_id'],
                'branch_id' => $request->user()->branch->id ,
                'amount' => $req['amount'] * $req['quantity'],
                'status' => 'debit',
            ];

            $payment = Payment::create($paymentPayload);


            return response()->json(['message'=>'All Drug Batches has been Successfully Added','data'=> $newDrugBatch],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }

    public function editUser (Request $request){

            $req = $request->all();

            try {

                $user = User::where('id',$req['user_id'])->first();

                $user->update($req['editPayload']);

                $user->refresh();


                return response()->json(['message'=>'','data'=> $user],200);

            }catch (Exception $exception){
                return response()->json(['message' => $exception->getMessage()]);
            }
    }

    public function getUser (Request $request){

        $req = $request->all();

        try {

            $user = User::whereAny(['email','phone_no','regID'],$req['details'])->first();



            return response()->json(['message'=>'','data'=> $user],200);

        }catch (Exception $exception){
            return response()->json(['message' => $exception->getMessage()]);
        }
    }




}
