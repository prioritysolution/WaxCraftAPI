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

class ProcessInventory extends Controller
{
    public function convertToObject($array) {
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->convertToObject($value);
            }
            $object->$key = $value;
        }
        return $object;
    }

    public function get_debtor_list(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Party_Name,Party_Add,Party_Mob,Party_Gst From mst_party_master Where Party_Type=1;");

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

    public function get_order_design(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Concat(Design_Name,' - ',Design_No) As Design_Name From mst_design_master Where Is_Active=1;");

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

    public function get_design_details(Int $org_id,Int $design_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select m.Id,m.Design_Name,m.Design_No,m.WT,m.Polish,d.Item_Id,d.Qnty,i.Item_Name,i.Item_Sh_Name,UDF_GET_ITEM_RATE(d.Item_Id) As Item_Rate,(UDF_GET_ITEM_RATE(d.Item_Id) * d.Qnty) As Item_Tot From mst_design_master m Join mst_design_details d On d.Design_Id=m.Id Join mst_item_master i On i.Id=d.Item_Id Where m.Id=?;",[$design_id]);

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

    public function process_order(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'ord_date' => 'required',
            'party_id' => 'required',
            'tot_amt' => 'required',
            'year_id' => 'required',
            'order_array' => 'required'
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

                $order_details = $this->convertToObject($request->order_array);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists temporddetails;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table temporddetails
                                                                (
                                                                    Design_Id		Int,
                                                                    Qnty			Numeric(18,2),
                                                                    Wt_Rate			Numeric(18,2),
                                                                    Tot_Wt			Numeric(18,2),
                                                                    Polish_Rate		Numeric(18,2),
                                                                    Tot_Polish		Numeric(18,2),
                                                                    Item_Id			Int,
                                                                    Item_Qnty		Numeric(18,2),
                                                                    Item_Rate		Numeric(18,2),
                                                                    Item_Tot		Numeric(18,2)
                                                                );");
                foreach ($order_details as $order_data) {
                   DB::connection('wax')->statement("Insert Into temporddetails (Design_Id,Qnty,Wt_Rate,Tot_Wt,Polish_Rate,Tot_Polish,Item_Id,Item_Qnty,Item_Rate,Item_Tot) Values (?,?,?,?,?,?,?,?,?,?);",[$order_data->design_id,$order_data->qnty,$order_data->wt_rate,$order_data->tot_wt,$order_data->polish_rate,$order_data->tot_polish,$order_data->item_id,$order_data->item_qnty,$order_data->item_rate,$order_data->item_tot]);
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ORDER(?,?,?,?,?,?,?,@error,@message);",[null,$request->ord_date,$request->party_id,$request->tot_amt,$request->year_id,auth()->user()->Id,1]);

                if(!$sql){
                    throw new Exception;
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
                DB::rollBack(); 
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