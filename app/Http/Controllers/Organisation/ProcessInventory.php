<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\ImageUpload;
use Hash;
use Exception;
use Session;
use DB;
use \stdClass;

class ProcessInventory extends Controller
{
    use ImageUpload;
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

            $sql = DB::connection('wax')->select("Select m.Id,m.Design_Name,m.Design_No,m.WT,m.Wt_Rate,m.Polish,d.Item_Id,d.Qnty,i.Item_Name,i.Item_Sh_Name,UDF_GET_ITEM_RATE(d.Item_Id) As Item_Rate,d.Making_Rate,(UDF_GET_ITEM_RATE(d.Item_Id) * d.Qnty)+(d.Qnty*d.Making_Rate) As Item_Tot,m.Image,i.Purchase_Gl From mst_design_master m Join mst_design_details d On d.Design_Id=m.Id Join mst_item_master i On i.Id=d.Item_Id Where m.Id=?;",[$design_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $menu_set = [];
            
            foreach ($sql as $row) {
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' =>$row->Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        'WT' => $row->WT,
                        'Wt_Rate' => $row->Wt_Rate,
                        'Polish' => $row->Polish,
                        'Image' =>$this->getUrl($org_id,$row->Image),
                        "childrow" => []
                    ];
                }
                if ($row->Item_Id) {
                    $menu_set[$row->Id]['childrow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Item_GL' => $row->Purchase_Gl,
                        'Qnty' => $row->Qnty,
                        'Item_Name' => $row->Item_Name,
                        'Item_Sh_Name' => $row->Item_Sh_Name,
                        'Item_Rate' => $row->Item_Rate,
                        'Making_Rate' => $row->Making_Rate,
                        'Item_Total' => $row->Item_Tot
                    ];
                }
            }
            $menu_set = array_values($menu_set);

            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
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
            'is_own' => 'required',
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
                                                                    Qnty_Rate       Numeric(18,2),
                                                                    Wt              Numeric(18,2),
                                                                    Wt_Rate			Numeric(18,2),
                                                                    Tot_Wt			Numeric(18,2),
                                                                    Polish_Rate		Numeric(18,2),
                                                                    Tot_Polish		Numeric(18,2),
                                                                    Item_Id			Int,
                                                                    Item_Gl         Int,
                                                                    Item_Qnty		Numeric(18,2),
                                                                    Item_Rate		Numeric(18,3),
                                                                    Making_Rate     Numeric(18,3),
                                                                    Item_Tot		Numeric(18,3),
                                                                    Item_Grand_Tot  Numeric(18,3)
                                                                );");
                foreach ($order_details as $order_data) {
                   DB::connection('wax')->statement("Insert Into temporddetails (Design_Id,Qnty,Qnty_Rate,Wt_Rate,Tot_Wt,Polish_Rate,Tot_Polish,Item_Id,Item_Qnty,Item_Rate,Item_Tot,Wt,Item_Grand_Tot,Making_Rate,Item_Gl) Values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?);",[$order_data->design_id,$order_data->qnty,$order_data->qnty_rate,$order_data->wt_rate,$order_data->tot_wt,$order_data->polish_rate,$order_data->tot_polish,$order_data->item_id,$order_data->item_qnty,$order_data->item_rate,$order_data->item_tot,$order_data->wt,$order_data->item_grand_tot,$order_data->making_rate,$order_data->Item_Gl]);
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ORDER(?,?,?,?,?,?,?,@error,@message);",[null,$request->ord_date,$request->party_id,$request->year_id,$request->is_own,auth()->user()->Id,1]);

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

    public function get_active_order_list(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$org_id]);
            if (!$sql) {
                throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            $sql = DB::connection('wax')->select("SELECT 
                m.Id,
                m.Order_Date,
                m.Order_No,
                UDF_GET_PARTY_NAME(m.Party_Id) AS Party_Name,
                m.Party_Id,
                m.Tot_Amt,
                d.Design_Id,
                dn.Design_Name,
                dn.Design_No,
                dn.Image,
                d.Order_Qnty,
                d.Deg_Rate,
                UDF_GET_ORDER_STATUS(m.Id) AS Order_Status,
                d.Wt,
                d.Wt_Rate,
                d.Tot_Wt,
                d.Polish_Rate,
                d.Tot_Polish,
                i.Item_Id,
                UDF_GET_ITEM_NAME(i.Item_Id) As Item_Name,
                i.Item_Qnty,
                i.Item_Rate,
                i.Making_Rate,
                i.Item_Tot
            FROM 
                mst_order_master m
            JOIN 
                mst_order_details d ON d.Order_Id = m.Id
            JOIN 
                mst_design_master dn ON dn.Id = d.Design_Id
            JOIN 
                mst_order_item_details i ON i.Order_Id = m.Id AND i.Design_Id = d.Design_Id
            WHERE 
                m.Is_Invoise = 0;
            ");
        
            if (empty($sql)) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            $menu_set = [];
            
            foreach ($sql as $row) {
                // Initialize the main order structure
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' => $row->Id,
                        'Order_Date' => $row->Order_Date,
                        'Order_No' => $row->Order_No,
                        'Party_Name' => $row->Party_Name,
                        'Party_Id' => $row->Party_Id,
                        'Total_Order' => $row->Tot_Amt,
                        'Order_Status' => $row->Order_Status,
                        "DesignRow" => [],
                    ];
                }
        
                // Add design details to the order
                if (!isset($menu_set[$row->Id]['DesignRow'][$row->Design_Id])) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id] = [
                        'Design_Id' => $row->Design_Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        'Order_Qnty' => $row->Order_Qnty,
                        'Design_Rate' => $row->Deg_Rate,
                        'Wt' => $row->Wt,
                        'Wt_Rate' => $row->Wt_Rate,
                        'Tot_Wt' => $row->Tot_Wt,
                        'Polish' => $row->Polish_Rate,
                        'Tot_Polish' => $row->Tot_Polish,
                        'Image' => $this->getUrl($org_id,$row->Image),
                        'ItemRow' => []
                    ];
                }
        
                // Add item details to the corresponding design
                if ($row->Item_Id) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id]['ItemRow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Item_Name' => $row->Item_Name,
                        'Item_Qnty' => $row->Item_Qnty,
                        'Item_Rate' => $row->Item_Rate,
                        'Making_Rate' => $row->Making_Rate,
                        'Item_Tot' => $row->Item_Tot,
                    ];
                }
            }
        
            // Reset keys for DesignRow
            foreach ($menu_set as &$order) {
                $order['DesignRow'] = array_values($order['DesignRow']);
            }
            $menu_set = array_values($menu_set);
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
        
    }

    public function cancle_order(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'order_id' => 'required',
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

                
                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ORDER(?,?,?,?,?,?,?,@error,@message);",[$request->order_id,null,null,null,null,auth()->user()->Id,2]);

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
                        'message' => 'Order Cancled Successfully !!',
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

    public function get_work_status(Int $org_id,Int $order_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select m.Design_Id,m.Work_Details,m.Work_Start,m.Work_End,d.Design_Name,d.Design_No,e.Emp_Name From mst_order_status m Join mst_design_master d On d.Id=m.Design_Id Join mst_employee_master e On e.Id=m.Work_Under Where m.Order_Id=?;",[$order_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            $menu_set = [];
            
            foreach ($sql as $row) {
                if (!isset($menu_set[$row->Design_Id])) {
                    $menu_set[$row->Design_Id] = [
                        'Id' =>$row->Design_Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        "childrow" => []
                    ];
                }
                if ($row->Work_Details) {
                    $menu_set[$row->Design_Id]['childrow'][] = [
                        'Work_Details' => $row->Work_Details,
                        'Work_Start' => $row->Work_Start,
                        'Work_End' => $row->Work_End,
                        'Work_Under' => $row->Emp_Name
                    ];
                }
            }
            $menu_set = array_values($menu_set);

            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function process_work_order(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'order_id' => 'required',
            'work_details' => 'required'
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

                $order_details = $this->convertToObject($request->work_details);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists tempdetails;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table tempdetails
                                                                (
                                                                    Design_Id		Int,
                                                                    Work_Details	Varchar(250),
                                                                    Start_Date		Date,
                                                                    Work_Under		Int
                                                                );");
                foreach ($order_details as $order_data) {
                   DB::connection('wax')->statement("Insert Into tempdetails (Design_Id,Work_Details,Start_Date,Work_Under) Values (?,?,?,?);",[$order_data->design_id,$order_data->work_details,$order_data->start_date,$order_data->work_under]);
                }

                $sql = DB::connection('wax')->statement("Call USP_PROCESS_ORDER(?,?,?,?,@error,@message);",[$request->order_id,auth()->user()->Id,null,1]);

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
                        'message' => 'Order Worked Process Successfully !!',
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

    public function get_complete_order_list(Int $org_id,Int $party_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$org_id]);
            if (!$sql) {
                throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            $sql = DB::connection('wax')->select("SELECT 
                m.Id,
                m.Order_Date,
                m.Order_No,
                UDF_GET_PARTY_NAME(m.Party_Id) AS Party_Name,
                m.Party_Id,
                m.Tot_Amt,
                d.Design_Id,
                dn.Design_Name,
                dn.Design_No,
                dn.Image,
                d.Order_Qnty,
                d.Deg_Rate,
                UDF_GET_ORDER_STATUS(m.Id) AS Order_Status,
                d.Wt_Rate,
                d.Wt,
                d.Tot_Wt,
                d.Polish_Rate,
                d.Tot_Polish,
                i.Item_Id,
                UDF_GET_ITEM_NAME(i.Item_Id) As Item_Name,
                i.Item_Qnty,
                i.Item_Rate,
                i.Making_Rate,
                i.Item_Tot
            FROM 
                mst_order_master m
            JOIN 
                mst_order_details d ON d.Order_Id = m.Id
            JOIN 
                mst_design_master dn ON dn.Id = d.Design_Id
            JOIN 
                mst_order_item_details i ON i.Order_Id = m.Id AND i.Design_Id = d.Design_Id
            WHERE 
                m.Is_Invoise = 0 And m.Party_Id=$party_id And (Select Count(*) From mst_order_status Where Order_Id=m.Id And Is_Complete=1)<>0;
            ");
        
            if (empty($sql)) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            $menu_set = [];
            
            foreach ($sql as $row) {
                // Initialize the main order structure
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' => $row->Id,
                        'Order_Date' => $row->Order_Date,
                        'Order_No' => $row->Order_No,
                        'Party_Name' => $row->Party_Name,
                        'Party_Id' => $row->Party_Id,
                        'Total_Order' => $row->Tot_Amt,
                        'Order_Status' => $row->Order_Status,
                        "DesignRow" => [],
                    ];
                }
        
                // Add design details to the order
                if (!isset($menu_set[$row->Id]['DesignRow'][$row->Design_Id])) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id] = [
                        'Design_Id' => $row->Design_Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        'Image' => $this->getUrl($org_id,$row->Image),
                        'Order_Qnty' => $row->Order_Qnty,
                        'Design_Rate' => $row->Deg_Rate,
                        'Wt' => $row->Wt,
                        'Wt_Rate' => $row->Wt_Rate,
                        'Tot_Wt' => $row->Tot_Wt,
                        'Polish' => $row->Polish_Rate,
                        'Tot_Polish' => $row->Tot_Polish,
                        'ItemRow' => []
                    ];
                }
        
                // Add item details to the corresponding design
                if ($row->Item_Id) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id]['ItemRow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Item_Name' => $row->Item_Name,
                        'Item_Qnty' => $row->Item_Qnty,
                        'Item_Rate' => $row->Item_Rate,
                        'Making_Rate' => $row->Making_Rate,
                        'Item_Tot' => $row->Item_Tot,
                    ];
                }
            }
        
            // Reset keys for DesignRow
            foreach ($menu_set as &$order) {
                $order['DesignRow'] = array_values($order['DesignRow']);
            }
            $menu_set = array_values($menu_set);
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function process_final_process(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'order_id' => 'required',
            'comp_date' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_PROCESS_ORDER(?,?,?,?,@error,@message);",[$request->order_id,auth()->user()->Id,$request->comp_date,2]);

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
                    'message' => 'Order Is Final Processed Successfully !!',
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

    public function process_invoise(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'sales_date' => 'required',
            'party_id' => 'required',
            'tot_amount' => 'required',
            'gst_rate' => 'required',
            'tot_cgst' => 'required',
            'tot_sgst' => 'required',
            'tot_igst' => 'required',
            'tot_round' => 'required',
            'tot_discount' => 'required',
            'year_id' => 'required',
            'invoise_data' => 'required'
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

                $order_details = $this->convertToObject($request->invoise_data);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists temporddetails;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table temporddetails
                                                                (
                                                                    Order_Id		Int,
                                                                    Design_Id		Int,
                                                                    Qnty			Numeric(18,2),
                                                                    Wt              Numeric(18,3),
                                                                    Wt_Rate			Numeric(18,2),
                                                                    Tot_Wt			Numeric(18,2),
                                                                    Polish_Rate		Numeric(18,2),
                                                                    Tot_Polish		Numeric(18,2),
                                                                    Item_Id			Int,
                                                                    Item_Qnty		Numeric(18,2),
                                                                    Item_Rate		Numeric(18,3),
                                                                    Making_Rate     Numeric(18,3),
                                                                    Item_Tot		Numeric(18,3)
                                                                );");
                foreach ($order_details as $order_data) {
                   DB::connection('wax')->statement("Insert Into temporddetails (Order_Id,Design_Id,Qnty,Wt_Rate,Tot_Wt,Polish_Rate,Tot_Polish,Item_Id,Item_Qnty,Item_Rate,Item_Tot,Wt,Making_Rate) Values (?,?,?,?,?,?,?,?,?,?,?,?,?);",[$order_data->order_id,$order_data->design_id,$order_data->qnty,$order_data->wt_rate,$order_data->tot_wt,$order_data->polish_rate,$order_data->tot_polish,$order_data->item_id,$order_data->item_qnty,$order_data->item_rate,$order_data->item_tot,$order_data->wt,$order_data->making_rate]);
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_SALE(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message,@sale_id);",[null,$request->sales_date,$request->party_id,$request->tot_amount,$request->gst_rate,$request->tot_cgst,$request->tot_sgst,$request->tot_igst,$request->tot_round,$request->tot_discount,$request->year_id,$request->bank_id,$request->is_credit,auth()->user()->Id,1]);

                if(!$sql){
                    throw new Exception;
                }
                $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message,@sale_id As Sales_Id;");
                $error_No = $result[0]->Error_No;
                $message = $result[0]->Message;
                $sales_Id = $result[0]->Sales_Id;
    
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
                        'details' => $sales_Id,
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

    public function cancel_invoise(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'sales_id' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_SALE(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message,@sale_id);",[$request->sales_id,null,null,null,null,null,null,null,null,null,null,null,null,auth()->user()->Id,2]);

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
                    'message' => 'Sales Invoise Successfully Cancled !!',
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

    public function process_print_invoise(Int $org_id,Int $sale_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$org_id]);
            if (!$sql) {
                throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            $sql = DB::connection('wax')->select("SELECT 
                                m.Id,
                                m.Sales_Date,
                                m.Sales_No,
                                p.Party_Name,
                                p.Party_Add,
                                p.Party_Mob,
                                p.Party_Gst,
                                m.Party_Id,
                                m.Tot_Amount,
                                m.Gst_Rate,
                                m.Tot_CGST,
                                m.Tot_SGST,
                                m.Tot_IGST,
                                m.Tot_Round,
                                m.Tot_Discount,
                                d.Design_Id,
                                dn.Design_Name,
                                dn.Design_No,
                                dn.Image,
                                d.Deg_Qnty,
                                d.Wt,
                                d.Wt_Rate,
                                d.Tot_Wt,
                                d.Polish_Rate,
                                d.Tot_Polish,
                                i.Item_Id,
                                UDF_GET_ITEM_NAME(i.Item_Id) AS Item_Name,
                                i.Item_Qnty,
                                i.Item_Rate,
                                i.Making_Rate,
                                i.Tot_Item
                            FROM
                                trn_sales_master m
                                    JOIN
                                trn_sales_details d ON d.Sales_Id = m.Id
                                    JOIN
                                mst_design_master dn ON dn.Id = d.Design_Id
                                    JOIN
                                trn_sales_item_details i ON i.Sales_Id = m.Id
                                    AND i.Design_Id = d.Design_Id
                                    JOIN
                                mst_party_master p ON p.Id = m.Party_Id
                            WHERE
                                m.Id = ?;
            ",[$sale_id]);
        
            if (empty($sql)) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            $menu_set = [];
            
            foreach ($sql as $row) {
                // Initialize the main order structure
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' => $row->Id,
                        'Sale_Date' => $row->Sales_Date,
                        'Sale_No' => $row->Sales_No,
                        'Party_Name' => $row->Party_Name,
                        'Party_Id' => $row->Party_Id,
                        'Party_Add' => $row->Party_Add,
                        'Party_Mob' => $row->Party_Mob,
                        'Party_GST' => $row->Party_Gst,
                        'Tot_Amount' => $row->Tot_Amount,
                        'CGST_Rate' => ($row->Gst_Rate/2),
                        'Tot_CGST' => $row->Tot_CGST,
                        'SGST_Rate' => ($row->Gst_Rate/2),
                        'Tot_SGST' => $row->Tot_SGST,
                        'Tot_IGST' => $row->Tot_IGST,
                        'Tot_Round' => $row->Tot_Round,
                        'Tot_Disc' => $row->Tot_Discount,
                        "DesignRow" => [],
                    ];
                }
        
                // Add design details to the order
                if (!isset($menu_set[$row->Id]['DesignRow'][$row->Design_Id])) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id] = [
                        'Design_Id' => $row->Design_Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        'Image' => $this->getUrl($org_id,$row->Image),
                        'Order_Qnty' => $row->Deg_Qnty,
                        'Wt' => $row->Wt,
                        'Wt_Rate' => $row->Wt_Rate,
                        'Tot_Wt' => $row->Tot_Wt,
                        'Polish' => $row->Polish_Rate,
                        'Tot_Polish' => $row->Tot_Polish,
                        'ItemRow' => []
                    ];
                }
        
                // Add item details to the corresponding design
                if ($row->Item_Id) {
                    $menu_set[$row->Id]['DesignRow'][$row->Design_Id]['ItemRow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Item_Name' => $row->Item_Name,
                        'Item_Qnty' => $row->Item_Qnty,
                        'Item_Rate' => $row->Item_Rate,
                        'Making_Rate' => $row->Making_Rate,
                        'Item_Tot' => $row->Tot_Item,
                    ];
                }
            }
        
            // Reset keys for DesignRow
            foreach ($menu_set as &$order) {
                $order['DesignRow'] = array_values($order['DesignRow']);
            }
            $menu_set = array_values($menu_set);
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function get_invoise_list(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select m.Id,m.Sales_Date,m.Sales_No,p.Party_Name,p.Party_Add,p.Party_Gst,p.Party_Mob From trn_sales_master m Join mst_party_master p On p.Id=m.Party_Id;");

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

    public function get_pur_party_list(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Party_Name,Party_Add,Party_Mob,Party_Gst From mst_party_master Where Party_Type=2;");

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

    public function process_purchase(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'pur_date' => 'required',
            'pur_no' => 'required',
            'party_id' => 'required',
            'tot_amount' => 'required',
            'tot_cgst' => 'required',
            'tot_sgst' => 'required',
            'tot_igst' => 'required',
            'tot_round' => 'required',
            'tot_discount' => 'required',
            'year_id' => 'required',
            'invoise_data' => 'required'
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

                $order_details = $this->convertToObject($request->invoise_data);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists tempitemdata;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table tempitemdata
                                                                (
                                                                    Item_Id			Int,
                                                                    Item_Gl			Int,
                                                                    Item_Qnty		Int,
                                                                    Item_Rate		Numeric(18,3),
                                                                    Item_Tot		Numeric(18,2),
                                                                    Item_CGST		Numeric(18,2),
                                                                    Item_SGST		Numeric(18,2),
                                                                    Item_IGST		Numeric(18,2)
                                                                );");
                foreach ($order_details as $order_data) {
                   DB::connection('wax')->statement("Insert Into tempitemdata (Item_Id,Item_Gl,Item_Qnty,Item_Rate,Item_Tot,Item_CGST,Item_SGST,Item_IGST) Values (?,?,?,?,?,?,?,?);",[$order_data->item_id,$order_data->item_gl,$order_data->item_qnty,$order_data->item_rate,$order_data->item_tot,$order_data->item_cgst,$order_data->item_sgst,$order_data->item_igst]);
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PURCHASE(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->pur_date,$request->pur_no,$request->party_id,$request->tot_amount,$request->tot_cgst,$request->tot_sgst,$request->tot_igst,$request->tot_round,$request->tot_discount,$request->year_id,$request->bank_id,$request->is_credit,auth()->user()->Id,1]);

                if(!$sql){
                    throw new Exception;
                }
                $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message,@sale_id As Sales_Id;");
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
                        'message' => 'Purchase Voucher Is Posted Successfully !!',
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

    public function get_pur_list(Int $org_id){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$org_id]);
            if (!$sql) {
                throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            $sql = DB::connection('wax')->select("Select m.Id,m.Pur_Date,m.Pur_No,UDF_GET_PARTY_NAME(m.Party_Id) Party_Name,(m.Tot_Amt+m.Tot_CGST+m.Tot_SGST+m.Tot_IGST+m.Tot_Round-m.Tot_Discount) Total_Amount,d.Item_Id,UDF_GET_ITEM_NAME(d.Item_Id) Item_Name,d.Item_Qnty,d.Item_Rate,d.Item_Cgst,d.Item_Sgst,d.Item_Igst From trn_purchase_master m Join trn_purchase_details d On d.Pur_Id=m.Id;");
        
            if (empty($sql)) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            $menu_set = [];
            
            foreach ($sql as $row) {
                // Initialize the main order structure
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' => $row->Id,
                        'Purchase_Date' => $row->Pur_Date,
                        'Purchase_No' => $row->Pur_No,
                        'Party_Name' => $row->Party_Name,
                        'Total_Amount' => $row->Total_Amount,
                        "ItemRow" => [],
                    ];
                }
        
                // Add design details to the order
                if (!isset($menu_set[$row->Id]['ItemRow'][$row->Item_Id])) {
                    $menu_set[$row->Id]['ItemRow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Item_Name' => $row->Item_Name,
                        'Item_Qnty' => $row->Item_Qnty,
                        'Item_Rate' => $row->Item_Rate,
                        'Item_CGST' => $row->Item_Cgst,
                        'Item_SGST' => $row->Item_Sgst,
                        'Item_IGST' => $row->Item_Igst,
                    ];
                }
            }
        
            $menu_set = array_values($menu_set);
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function cancel_purchase(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'pur_id' => 'required'
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

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PURCHASE(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->pur_id,null,null,null,null,null,null,null,null,null,null,null,null,auth()->user()->Id,2]);

                if(!$sql){
                    throw new Exception;
                }
                $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message,@sale_id As Sales_Id;");
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
                        'message' => 'Purchase Voucher Is Canceled Successfully !!',
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