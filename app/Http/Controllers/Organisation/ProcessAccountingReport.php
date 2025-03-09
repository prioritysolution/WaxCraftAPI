<?php

namespace App\Http\Controllers\Organisation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Exceptions\HttpResponseException;
use Hash;
use Exception;
use Session;
use DB;

class ProcessAccountingReport extends Controller
{
    public function process_daybook(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_DAYBOOK(?);",[$request->date]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $daubook_data = [];
           
            foreach ($sql as $daybook) {
                if($daybook->Open_Balance){
                    $daubook_data['Opening_Balance']=[
                        'Opening_Cash' => $daybook->Open_Balance,
                        'Receipt_Data' => [],
                        'Payment_Data'=>[],
                        'Closing_Cash'=>'',
                    ];
                }

                if($daybook->Rec_Vouch){
                    $daubook_data['Opening_Balance']['Receipt_Data'][]=[
                        'Vouch_No' => $daybook->Rec_Vouch,
                        'Ledger_Name' => $daybook->Ledger_Name,
                        'Cash_Amt' => $daybook->Receipt_Cash,
                        'Trf_Amt' => $daybook->Receipt_Trf,
                        'Tot_Amt' => $daybook->Tot_Receipt
                    ];
                }

                if($daybook->Payment_Vouch){
                    $daubook_data['Opening_Balance']['Payment_Data'][]=[
                        'Vouch_No' => $daybook->Payment_Vouch,
                        'Ledger_Name' => $daybook->Ledger_Name,
                        'Cash_Amt' => $daybook->Payment_Cash,
                        'Trf_Amt' => $daybook->Payment_Transfer,
                        'Tot_Amt' => $daybook->Payment_Total
                    ];
                }

                 if($daybook->Closing_Balance){
                    $daubook_data['Opening_Balance']['Closing_Cash']=$daybook->Closing_Balance;
                }
            }
            $daubook_data = array_values($daubook_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $daubook_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_bank_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_BANK_BOOK(?,?,?);",[$request->frm_date,$request->to_date,$request->bank_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $bank_data = [];
            foreach ($sql as $bank) {
               if(!isset($bank_data[$bank->Account_Id])){
                $bank_data[$bank->Account_Id]=[
                    'Bank_Name' => $bank->Bank_Name,
                    'Branch_Name' => $bank->Branch_Name,
                    'Bank_IFSC' => $bank->Bank_IFSC,
                    'Account_No' => $bank->Account_No,
                    'Transaction_Data'=>[],
                ];
               }
               if($bank->Trans_Id){
                if(!isset($bank_data[$bank->Account_Id]['Transaction_Data'][$bank->Trans_Id])){
                    $bank_data[$bank->Account_Id]['Transaction_Data'][] = [
                        'Trans_Date' => $bank->Trans_Date,
                        'Particular' => $bank->Particular,
                        'Debit' => $bank->Dr_Amt,
                        'Credit' => $bank->Cr_Amt,
                        'Balance' => $bank->Balance,
                        'Balance_Type' => $bank->Bal_Type,
                    ];
               }
               }
            }
           
            $bank_data = array_values($bank_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $bank_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Start query
            $query = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Ledger_Name')
                ->where('Id', '<>', 3);
        
            // Apply keyword filter if provided
            if (!empty($request->keyword)) {
                $query->where('Ledger_Name', 'like', '%' . $request->keyword . '%');
            }
        
            // Paginate results (10 per page)
            $ledgers = $query->paginate(10);
        
            if ($ledgers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $ledgers->items(),
                'pagination' => [
                    'total' => $ledgers->total(),
                    'per_page' => $ledgers->perPage(),
                    'current_page' => $ledgers->currentPage(),
                    'last_page' => $ledgers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function process_acct_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_ACCT_LEDGER(?,?,?);",[$request->form_date,$request->to_date,$request->ledger_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_cashbook(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_CASH_BOOK(?);",[$request->date]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $cashbook_data = [];
           
            foreach ($sql as $cashbook) {
                if($cashbook->Opening_Balance){
                    $cashbook_data['Opening_Balance']=[
                        'Opening_Cash' => $cashbook->Opening_Balance,
                        'Receipt_Data' => [],
                        'Payment_Data'=>[],
                        'Closing_Cash'=>'',
                    ];
                }

                if($cashbook->Rec_Vouch_No){
                    $cashbook_data['Opening_Balance']['Receipt_Data'][]=[
                        'Vouch_No' => $cashbook->Rec_Vouch_No,
                        'Manual_Voucher' =>$cashbook->Rec_Vouch_No,
                        'Ledger_Name' => $cashbook->Ledger_Name,
                        'Particular' => $cashbook->Rec_Particular,
                        'Amount' => $cashbook->Rec_Amount,
                    ];
                }

                if($cashbook->Pay_Particular){
                    $cashbook_data['Opening_Balance']['Payment_Data'][]=[
                        'Vouch_No' => $cashbook->Rec_Vouch_No,
                        'Manual_Voucher' =>$cashbook->Rec_Vouch_No,
                        'Ledger_Name' => $cashbook->Ledger_Name,
                        'Particular' => $cashbook->Pay_Particular,
                        'Amount' => $cashbook->Pay_Amount,
                    ];
                }

                 if($cashbook->Closing_Balance){
                    $cashbook_data['Opening_Balance']['Closing_Cash']=$cashbook->Closing_Balance;
                }
            }
            $cashbook_data = array_values($cashbook_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $cashbook_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_tlrbook(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_TLR_CASH_BOOK(?,?);",[$request->user_id,$request->date]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $cashbook_data = [];
           
            foreach ($sql as $cashbook) {
                if($cashbook->Opening_Balance){
                    $cashbook_data['Opening_Balance']=[
                        'Opening_Cash' => $cashbook->Opening_Balance,
                        'Receipt_Data' => [],
                        'Payment_Data'=>[],
                        'Closing_Cash'=>'',
                    ];
                }

                if($cashbook->Rec_Vouch_No){
                    $cashbook_data['Opening_Balance']['Receipt_Data'][]=[
                        'Vouch_No' => $cashbook->Rec_Vouch_No,
                        'Manual_Voucher' =>$cashbook->Rec_Vouch_No,
                        'Ledger_Name' => $cashbook->Ledger_Name,
                        'Particular' => $cashbook->Rec_Particular,
                        'Amount' => $cashbook->Rec_Amount,
                    ];
                }

                if($cashbook->Pay_Particular){
                    $cashbook_data['Opening_Balance']['Payment_Data'][]=[
                        'Vouch_No' => $cashbook->Rec_Vouch_No,
                        'Manual_Voucher' =>$cashbook->Rec_Vouch_No,
                        'Ledger_Name' => $cashbook->Ledger_Name,
                        'Particular' => $cashbook->Pay_Particular,
                        'Amount' => $cashbook->Pay_Amount,
                    ];
                }

                 if($cashbook->Closing_Balance){
                    $cashbook_data['Opening_Balance']['Closing_Cash']=$cashbook->Closing_Balance;
                }
            }
            $cashbook_data = array_values($cashbook_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $cashbook_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }
}