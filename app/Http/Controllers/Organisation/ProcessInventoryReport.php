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

class ProcessInventoryReport extends Controller
{
    public function process_order_book(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_ORDER_BOOK(?,?,?);",[$request->form_date,$request->to_date,$request->party_id]);

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

    public function process_sales_register(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_SALES_REGISTER(?,?,?);",[$request->form_date,$request->to_date,$request->party_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $sales_data = [];
            foreach ($sql as $sale_detail) {
               if(!isset($sales_data[$sale_detail->Id])){
                $sales_data[$sale_detail->Id]=[
                    'Id' => $sale_detail->Id,
                    'Sales_Date' => $sale_detail->Sales_Date,
                    'Sale_No' => $sale_detail->Sales_No,
                    'Party_Name' => $sale_detail->Party_Name,
                    'Amount' => $sale_detail->Sales_Amount,
                    'Design_Data' => [],
                ];
               }

               if(!isset($sales_data[$sale_detail->Id]['Design_Data'][$sale_detail->Design_Id])){
                    $sales_data[$sale_detail->Id]['Design_Data'][] = [
                        'Design_Id' => $sale_detail->Design_Id,
                        'Design_Name' => $sale_detail->Design_Name,
                        'Qnty' => $sale_detail->Deg_Qnty,
                    ];
               }

            }
            $sales_data = array_values($sales_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $sales_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_purchase_register(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_PURCHASE_REGISTER(?,?,?);",[$request->form_date,$request->to_date,$request->party_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $purchase_data = [];
            foreach ($sql as $pur_data) {
               if(!isset($purchase_data[$pur_data->Id])){
                $purchase_data[$pur_data->Id]=[
                    'Id' => $pur_data->Id,
                    'Purchase_Date' => $pur_data->Pur_Date,
                    'Purchase_No' => $pur_data->Pur_No,
                    'Party_Name' => $pur_data->Party_Name,
                    'Amount' => $pur_data->Tot_Amount,
                    'Item_Data' => [],
                ];
               }

               if(!isset($purchase_data[$pur_data->Id]['Item_Data'][$pur_data->Item_Id])){
                    $purchase_data[$pur_data->Id]['Item_Data'][] = [
                        'Item_Id' => $pur_data->Item_Id,
                        'Item_Name' => $pur_data->Item_Name,
                        'Qnty' => $pur_data->Item_Qnty,
                        'Item_Rate' => $pur_data->Item_Rate,
                    ];
               }

            }
            $purchase_data = array_values($purchase_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $purchase_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_party_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_PARTY_LEDGER(?,?,?,?);",[$request->form_date,$request->to_date,$request->party_id,$request->type]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            
            $ledger_data = [];
            foreach ($sql as $ledg_data) {
               if(!isset($ledger_data[$ledg_data->Party_Id])){
                $ledger_data[$ledg_data->Party_Id]=[
                    'Party_Name' => $ledg_data->Party_Name,
                    'Party_Add' => $ledg_data->Party_Add,
                    'Party_Gst' => $ledg_data->Party_Gst,
                    'Party_Mob' => $ledg_data->Party_Mob,
                    'Ledger_Data'=>[],
                ];
               }
               
                if(!isset($ledger_data[$ledg_data->Party_Id]['Ledger_Data'][$ledg_data->Trans_Id])){
                    $ledger_data[$ledg_data->Party_Id]['Ledger_Data'][] = [
                        'Trans_Date' => $ledg_data->Trans_Date,
                        'Particular' => $ledg_data->Particular,
                        'Debit' => $ledg_data->Dr_Amt,
                        'Credit' => $ledg_data->Cr_Amt,
                        'Balance' => $ledg_data->Balance,
                        'Balance_Type' => $ledg_data->Balance_Type,
                    ];
               }
               
            }
            $ledger_data = array_values($ledger_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $ledger_data,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_party_item_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Call USP_RPT_PARTY_STONE(?,?,?);",[$request->party_id,$request->frm_date,$request->to_date]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            
            $ledger_data = [];
            foreach ($sql as $ledg_data) {
                if (!isset($ledger_data[$ledg_data->Party_Id])) {
                    $ledger_data[$ledg_data->Party_Id] = [
                        'Party_Name' => $ledg_data->Party_Name,
                        'Party_Add' => $ledg_data->Party_Address,
                        'Party_Gst' => $ledg_data->Party_GST,
                        'Party_Mob' => $ledg_data->Party_Mobile,
                        'ItemData' => [],
                    ];
                }
            
                if ($ledg_data->Item_Name) {
                    if (!isset($ledger_data[$ledg_data->Party_Id]['ItemData'][$ledg_data->Item_Id])) {
                        $ledger_data[$ledg_data->Party_Id]['ItemData'][$ledg_data->Item_Id] = [
                            "Item_Name" => $ledg_data->Item_Name,
                            "Trans_Details" => []
                        ];
                    }
                }
            
                if ($ledg_data->Trans_Date) {
                    $ledger_data[$ledg_data->Party_Id]["ItemData"][$ledg_data->Item_Id]["Trans_Details"][] = [
                        "Trans_Date" => $ledg_data->Trans_Date,
                        "Particular" => $ledg_data->Particular,
                        "Issue" => $ledg_data->Issue,
                        "Refund" => $ledg_data->Refund,
                        "Balance" => $ledg_data->Balance
                    ];
                }
            }
            
            // Convert Party Data to Indexed Array
            $ledger_data = array_values($ledger_data);
            
            // Convert ItemData to Indexed Array inside each party
            foreach ($ledger_data as &$party) {
                $party['ItemData'] = array_values($party['ItemData']);
            }
            unset($party);
            
            return response()->json([
                'message' => 'Data Found',
                'details' => $ledger_data,
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