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
use \stdClass;

class ProcessAccounting extends Controller
{
    public function get_ledger_list(Request $request){
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
                ->where(function ($q) {
                    $q->whereNotIn('Sub_Head', [1, 2, 3, 9])
                      ->orWhereNull('Sub_Head');
                })
                ->where('Id', '<>', 3);
        
            // Check if keyword exists and apply filter
            if ($request->has('keyword') && !empty($request->keyword)) {
                $keyword = trim($request->keyword);
                $query->where('Ledger_Name', 'LIKE', "%{$keyword}%");
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

    public function get_ledger_party(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Party_Name From mst_party_master Where Ledger_Id=?;",[$request->ledger_id]);

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

    public function process_receipts_voucher(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'amount' => 'required',
            'particular' => 'required',
            'ledger_id' => 'required',
            'year_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_RECEIPT_VOUCHER(?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->trans_date,$request->amount,$request->particular,$request->manual_vouch,$request->ledger_id,$request->party_id,$request->bank_id,$request->year_id,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_recpt_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Fetch records with pagination (10 per page)
            $vouchers = DB::connection('wax')->table('trn_voucher_master as m')
                ->join('trn_voucher_details as d', function ($join) {
                    $join->on('d.Trans_Id', '=', 'm.Id')
                        ->where('d.Trns_Type', '=', 'D');
                })
                ->select('m.Id', 'm.Trans_Date', 'm.Vouch_No', 'm.Ref_Vouch_No', 'm.Particular', 'd.Amount')
                ->whereIn('m.Trans_Source', ['AR', 'JR'])
                ->paginate(10); // Paginate with 10 records per page
        
            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $vouchers->items(),
                'pagination' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function cancel_recpt_voucher(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_RECEIPT_VOUCHER(?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->trans_id,null,null,null,null,null,null,null,null,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Receipt Voucher Successfully Cancled !!',
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function process_payment_voucher(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'amount' => 'required',
            'particular' => 'required',
            'ledger_id' => 'required',
            'year_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PAYMENT_VOUCHER(?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->trans_date,$request->amount,$request->particular,$request->manual_vouch,$request->ledger_id,$request->party_id,$request->bank_id,$request->year_id,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_payment_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Fetch paginated records (10 per page)
            $vouchers = DB::connection('wax')->table('trn_voucher_master as m')
                ->join('trn_voucher_details as d', function ($join) {
                    $join->on('d.Trans_Id', '=', 'm.Id')
                        ->where('d.Trns_Type', '=', 'C');
                })
                ->select('m.Id', 'm.Trans_Date', 'm.Vouch_No', 'm.Ref_Vouch_No', 'm.Particular', 'd.Amount')
                ->whereIn('m.Trans_Source', ['AP', 'JP'])
                ->paginate(10); // 10 records per page
        
            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $vouchers->items(),
                'pagination' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function cancel_payment_voucher(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PAYMENT_VOUCHER(?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->trans_id,null,null,null,null,null,null,null,null,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Payment Voucher Successfully Cancled !!',
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_bank_balance(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select UDF_CAL_BANK_BALANCE(?,?) As Balance;",[$request->bank_id,$request->date]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            return response()->json([
                'message' => 'Data Found',
                'details' => $sql[0]->Balance,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_bank_deposit(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'bank_id' => 'required',
            'particular' => 'required',
            'amount' => 'required',
            'year_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_DEPOSIT(?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->trans_date,$request->bank_id,$request->particular,$request->ref_vouch,$request->amount,$request->year_id,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_bank_dep_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Fetch paginated records (10 per page)
            $vouchers = DB::connection('wax')->table('trn_voucher_master as m')
                ->join('trn_voucher_details as d', function ($join) {
                    $join->on('d.Trans_Id', '=', 'm.Id')
                        ->where('d.Trns_Type', '=', 'D');
                })
                ->select('m.Id', 'm.Trans_Date', 'm.Vouch_No', 'm.Ref_Vouch_No', 'm.Particular', 'd.Amount')
                ->where('m.Trans_Source', 'BP')
                ->paginate(10); // 10 records per page
        
            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $vouchers->items(),
                'pagination' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function cancel_bank_deposit(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_DEPOSIT(?,?,?,?,?,?,?,?,?,@error,@message);",[$request->trans_id,null,null,null,null,null,null,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Bank Deposit Successfully Canceled !!',
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function process_bank_withdrwan(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'bank_id' => 'required',
            'particular' => 'required',
            'amount' => 'required',
            'year_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_WITHDRWAN(?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->trans_date,$request->bank_id,$request->particular,$request->ref_vouch,$request->amount,$request->year_id,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_bank_with_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Fetch paginated records (10 per page)
            $vouchers = DB::connection('wax')->table('trn_voucher_master as m')
                ->join('trn_voucher_details as d', function ($join) {
                    $join->on('d.Trans_Id', '=', 'm.Id')
                        ->where('d.Trns_Type', '=', 'C');
                })
                ->select('m.Id', 'm.Trans_Date', 'm.Vouch_No', 'm.Ref_Vouch_No', 'm.Particular', 'd.Amount')
                ->where('m.Trans_Source', 'BR')
                ->paginate(10); // Fetch 10 records per page
        
            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $vouchers->items(),
                'pagination' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function cancel_bank_withdrwan(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_WITHDRWAN(?,?,?,?,?,?,?,?,?,@error,@message);",[$request->trans_id,null,null,null,null,null,null,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Bank Withdrwan Successfully Cancled !!',
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function process_bank_transfer(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'frm_bank' => 'required',
            'to_bank' => 'required',
            'particular' => 'required',
            'amount' => 'required',
            'year_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_TRANSFER(?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->trans_date,$request->frm_bank,$request->to_bank,$request->particular,$request->ref_vouch,$request->amount,$request->year_id,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function list_bank_transfer(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql || empty($sql[0]->db)) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Fetch paginated records (10 per page)
            $vouchers = DB::connection('wax')->table('trn_voucher_master as m')
                ->join('trn_voucher_details as d', function ($join) {
                    $join->on('d.Trans_Id', '=', 'm.Id')
                        ->where('d.Trns_Type', '=', 'D');
                })
                ->select('m.Id', 'm.Trans_Date', 'm.Vouch_No', 'm.Ref_Vouch_No', 'm.Particular', 'd.Amount')
                ->where('m.Trans_Source', 'BJ')
                ->paginate(10); // Fetch 10 records per page
        
            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $vouchers->items(),
                'pagination' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ], 200);
        
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        }
        
    }

    public function cancel_bank_transfer(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_id' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_TRANSFER(?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->trans_id,null,null,null,null,null,null,null,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;

            if($error_No<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Bank Transfer Successfully Canceled !!',
                    'details' => null,
                ],200);
            }
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }

    public function get_trailor_list(Request $request){
        try {
            
            $sql = DB::select("Select Id,User_Name From mst_org_user Where Org_Id=? And Role_Id<>1 And Is_Active=1;",[$request->org_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            return response()->json([
                'message' => 'Data Found',
                'details' => $sql
            ]);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function get_trailor_balance(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select UDF_CAL_TLR_BALANCE(?,?) As Balance;",[$request->user_Id,$request->date]);

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

    public function process_tlr_trans(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'trans_date' => 'required',
            'user_id' => 'required',
            'trans_type' => 'required',
            'amount' => 'required'
        ]);
        if($validator->passes()){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            DB::connection('wax')->beginTransaction();
            $sql = DB::connection('wax')->statement("Call USP_POST_TLR_TRANS(?,?,?,?,?);",[$request->trans_date,$request->user_id,$request->trans_type,$request->amount,auth()->user()->Id]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
           
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Transaction Is Successfully Posted !!',
                    'details' => null,
                ],200);
            
        } catch (Exception $ex) {
            DB::connection('wax')->rollBack();
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }
    else{
        $errors = $validator->errors();

            $response = response()->json([
                'message' => $errors->messages(),
                'details' => null,
            ],202);
        
            throw new HttpResponseException($response);
    }
    }
}