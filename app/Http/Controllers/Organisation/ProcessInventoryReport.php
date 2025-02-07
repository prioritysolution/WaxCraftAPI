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
}