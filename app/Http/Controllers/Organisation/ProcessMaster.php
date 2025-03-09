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

class ProcessMaster extends Controller
{
    use ImageUpload;
    // public function convertToObject($array) {
    //     $object = new stdClass();
    //     foreach ($array as $key => $value) {
    //         if (is_array($value)) {
    //             $value = $this->convertToObject($value);
    //         }
    //         $object->$key = $value;
    //     }
    //     return $object;
    // }

    public function convertToObject($array) {
        if (is_string($array)) {
            $array = json_decode($array, true); // Decode JSON to an associative array
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON format in design_array");
            }
        }
        
        $object = new stdClass();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->convertToObject($value);
            }
            $object->$key = $value;
        }
        return $object;
    }

    public function process_catagory(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'cat_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_CATAGORY(?,?,?,?,@error,@message);",[null,$request->cat_name,auth()->user()->Id,1]);

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
                    'message' => 'Item Catagory Successfully Added !!',
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

    public function get_catagory_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
        
            if (!$sql) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get paginated results
            $perPage = request()->get('per_page', 10); // Default 10 per page
            $keyword = $request->get('keyword', '');
        
            $query = DB::connection('wax')
                ->table('mst_item_catagary')
                ->select('Id', 'Cat_Name')
                ->orderBy('Id');
        
            if (!empty($keyword)) {
                $query->where("Cat_Name", 'LIKE', "%{$keyword}%");
            }
        
            // Store paginated result in a variable
            $paginatedData = $query->paginate($perPage);
        
            // Correct way to check if data exists
            if ($paginatedData->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $paginatedData,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function update_catagory(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'cat_id' => 'required',
            'cat_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_CATAGORY(?,?,?,?,@error,@message);",[$request->cat_id,$request->cat_name,auth()->user()->Id,2]);

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
                    'message' => 'Item Catagory Successfully Updated !!',
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

    public function process_item_model(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'cat_id' => 'required',
            'model_name' => 'required',
            'model_sh_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_MODEL(?,?,?,?,?,?,@error,@message);",[null,$request->cat_id,$request->model_name,$request->model_sh_name,auth()->user()->Id,1]);

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
                    'message' => 'Item Model Successfully Added !!',
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

    public function get_model_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
        
            if (!$sql) {
                throw new Exception("Organization schema not found");
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filtering parameters
            $perPage = request()->get('per_page', 10); // Default 10 per page
        
            // Use query builder instead of DB::select()
            $query = DB::connection('wax')
                ->table('mst_item_model as m')
                ->join('mst_item_catagary as c', 'c.Id', '=', 'm.Cat_Id')
                ->select('m.Id', 'm.Model_Name', 'm.Model_Sh_Name', 'm.Cat_Id', 'c.Cat_Name')
                ->orderBy('m.Id');
        
        
            // Get paginated results
            $paginatedData = $query->paginate($perPage);
        
            // Check if there is data
            if ($paginatedData->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $paginatedData,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function get_catagory_model(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Model_Name,Model_Sh_Name From mst_item_model Where Cat_Id=?;",[$request->cat_id]);

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

    public function update_model(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'model_Id' => 'required',
            'cat_id' => 'required',
            'model_name' => 'required',
            'model_sh_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_MODEL(?,?,?,?,?,?,@error,@message);",[$request->model_Id,$request->cat_id,$request->model_name,$request->model_sh_name,auth()->user()->Id,2]);

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
                    'message' => 'Item Model Successfully Updated !!',
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

    public function process_item_size(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'model_Id' => 'required',
            'cat_id' => 'required',
            'size_name' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_SIZE(?,?,?,?,?,?,@error,@message);",[null,$request->cat_id,$request->model_Id,$request->size_name,auth()->user()->Id,1]);

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
                    'message' => 'Item Size Successfully Added !!',
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

    public function get_size_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception;
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get per_page, page, and filter from request
            $perPage = $request->per_page ?? 10; // Default 10 per page
            $page = $request->page ?? 1;
        
            $query = DB::connection('wax')->table('mst_item_size as m')
                ->join('mst_item_catagary as c', 'c.Id', '=', 'm.Cat_Id')
                ->join('mst_item_model as md', 'md.Id', '=', 'm.Mod_Id')
                ->select('m.Id', 'm.Cat_Id', 'm.Mod_Id', 'm.Size_Name', 'c.Cat_Name', 'md.Model_Sh_Name');
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function get_module_size(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Size_Name From mst_item_size Where Mod_Id=?;",[$request->model_id]);

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

    public function update_item_size(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'size_id' => 'required',
            'model_Id' => 'required',
            'cat_id' => 'required',
            'size_name' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM_SIZE(?,?,?,?,?,?,@error,@message);",[$request->size_id,$request->cat_id,$request->model_Id,$request->size_name,auth()->user()->Id,2]);

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
                    'message' => 'Item Size Successfully Updated !!',
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

    public function process_item_unit(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'unit_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_UNIT(?,?,?,?,@error,@message);",[null,$request->unit_name,auth()->user()->Id,1]);

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
                    'message' => 'Item Unit Successfully Added !!',
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

    public function get_unit_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Unit_Name From mst_item_unit;");

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

    public function update_unit(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'unit_id' => 'required',
            'unit_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_UNIT(?,?,?,?,@error,@message);",[$request->unit_id,$request->unit_name,auth()->user()->Id,2]);

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
                    'message' => 'Item Unit Successfully Updated !!',
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

    public function process_size_color(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'cat_id' => 'required',
            'mod_id' => 'required',
            'size_id' => 'required',
            'color_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_COLOR(?,?,?,?,?,?,?,@error,@message);",[null,$request->cat_id,$request->mod_id,$request->size_id,$request->color_name,auth()->user()->Id,1]);

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
                    'message' => 'Size Colour Successfully Added !!',
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

    public function get_size_color_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select m.Id,m.Cat_Id,m.Mod_Id,m.Size_Id,m.Color_Name,c.Cat_Name,md.Model_Sh_Name,s.Size_Name From mst_size_color m Join mst_item_catagary c On c.Id=m.Cat_Id Join mst_item_model md On md.Id=m.Mod_Id Join mst_item_size s On s.Id=m.Size_Id;");

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

    public function get_size_wise_color(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Color_Name From mst_size_color Where Size_Id=?;",[$request->size_id]);

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

    public function update_size_color(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'col_id' => 'required',
            'cat_id' => 'required',
            'mod_id' => 'required',
            'size_id' => 'required',
            'color_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_COLOR(?,?,?,?,?,?,?,@error,@message);",[$request->col_id,$request->cat_id,$request->mod_id,$request->size_id,$request->color_name,auth()->user()->Id,2]);

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
                    'message' => 'Size Colour Successfully Updated !!',
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

    public function get_account_head(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception;
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get per_page, page, and filter from request
            $perPage = $request->per_page ?? 10; // Default 10 per page
            $page = $request->page ?? 1;
            $headName = $request->keyword ?? null; // Filter value
        
            $query = DB::connection('wax')->table('mst_org_acct_head')
                ->select('Id', 'Head_Name');
        
            // Apply filter if head_name is provided
            if (!empty($headName)) {
                $query->where('Head_Name', 'LIKE', "%$headName%");
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function process_acct_head(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'head_name' => 'required',
            'under_head' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ACCOUNTS_HEAD(?,?,?,?,?,@error,@message);",[null,$request->head_name,$request->under_head,auth()->user()->Id,1]);

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
                    'message' => 'Accounts Head Successfully Added !!',
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

    public function get_acct_head_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception;
            }
        
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get per_page, page, and filter from request
            $perPage = $request->per_page ?? 10; // Default 10 per page
            $page = $request->page ?? 1;
            $subHeadName = $request->keyword ?? null; // Filter value
        
            $query = DB::connection('wax')->table('mst_org_acct_sub_head as m')
                ->join('mst_org_acct_head as h', 'h.Id', '=', 'm.Head_Id')
                ->select('m.Id', 'm.Head_Id as Main_Head', 'm.Sub_Head_Name', 'h.Head_Name');
        
            // Apply filter if sub_head_name is provided
            if (!empty($subHeadName)) {
                $query->where('m.Sub_Head_Name', 'LIKE', "%$subHeadName%");
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function update_acct_head(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'head_id' => 'required',
            'head_name' => 'required',
            'under_head' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ACCOUNTS_HEAD(?,?,?,?,?,@error,@message);",[$request->head_id,$request->head_name,$request->under_head,auth()->user()->Id,2]);

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
                    'message' => 'Accounts Head Successfully Updated !!',
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

    public function process_acct_ledger(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'ledger_name' => 'required',
            'open_balance' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ACCT_LEDGER(?,?,?,?,?,?,?,@error,@message);",[null,$request->ledger_name,$request->head_id,$request->sub_head,$request->open_balance,auth()->user()->Id,1]);

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
                    'message' => 'Accounts Ledger Successfully Added !!',
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

    public function get_acct_ledger_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $ledgerName = $request->keyword ?? null; // Filter value
        
            // Query with optional filtering
            $query = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Head_Id', 'Sub_Head', 'Ledger_Name', 'Open_Balance');
        
            // Apply filter if ledger_name is provided
            if (!empty($ledgerName)) {
                $query->where('Ledger_Name', 'LIKE', "%$ledgerName%");
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function update_acct_ledger(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'ledger_id' => 'required',
            'ledger_name' => 'required',
            'open_balance' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ACCT_LEDGER(?,?,?,?,?,?,?,@error,@message);",[$request->ledger_id,$request->ledger_name,$request->head_id,$request->sub_head,$request->open_balance,auth()->user()->Id,2]);

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
                    'message' => 'Accounts Ledger Successfully Updated !!',
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

    public function get_purchase_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $ledgerName = $request->keyword ?? null; // Filter value
        
            // Query for ledgers where Head_Id = 13
            $query1 = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Ledger_Name')
                ->where('Head_Id', 13);
        
            // Query for ledgers where Sub_Head is in the subquery
            $query2 = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Ledger_Name')
                ->whereIn('Sub_Head', function ($subquery) {
                    $subquery->select('Id')
                        ->from('mst_org_acct_sub_head')
                        ->where('Head_Id', 13);
                });
        
            // Combine the queries using Union
            $query = $query1->unionAll($query2);
        
            // Apply filter if ledger_name is provided
            if (!empty($ledgerName)) {
                $query->where('Ledger_Name', 'LIKE', "%$ledgerName%");
            }
        
            // Apply pagination
            $sql = DB::connection('wax')->table(DB::raw("({$query->toSql()}) as combined_query"))
                ->mergeBindings($query) // Bind parameters
                ->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function get_sales_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $ledgerName = $request->keyword ?? null; // Filter value
        
            // Query for ledgers where Head_Id = 13
            $query1 = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Ledger_Name')
                ->where('Head_Id', 14);
        
            // Query for ledgers where Sub_Head is in the subquery
            $query2 = DB::connection('wax')->table('mst_org_acct_ledger')
                ->select('Id', 'Ledger_Name')
                ->whereIn('Sub_Head', function ($subquery) {
                    $subquery->select('Id')
                        ->from('mst_org_acct_sub_head')
                        ->where('Head_Id', 14);
                });
        
            // Combine the queries using Union
            $query = $query1->unionAll($query2);
        
            // Apply filter if ledger_name is provided
            if (!empty($ledgerName)) {
                $query->where('Ledger_Name', 'LIKE', "%$ledgerName%");
            }
        
            // Apply pagination
            $sql = DB::connection('wax')->table(DB::raw("({$query->toSql()}) as combined_query"))
                ->mergeBindings($query) // Bind parameters
                ->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function process_item(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'open_date' => 'required',
            'cat_id' => 'required',
            'item_name' => 'required',
            'item_sh_name' => 'required',
            'item_unit' => 'required',
            'pur_ledg' => 'required',
            'sales_ledg' => 'required',
            'cgst' => 'required',
            'sgst' => 'required',
            'igst' => 'required',
            'pur_rate' => 'required',
            'sales_rate' => 'required',
            'open_qnty' => 'required',
            'item_rate' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->open_date,$request->cat_id,$request->item_mod,$request->item_size,$request->item_color,$request->item_name,$request->item_sh_name,$request->item_unit,$request->pur_ledg,$request->sales_ledg,$request->cgst,$request->sgst,$request->igst,$request->pur_rate,$request->sales_rate,$request->open_qnty,$request->item_rate,auth()->user()->Id,1]);

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
                    'message' => 'Item Successfully Added !!',
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

    public function get_item_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $searchTerm = $request->keyword ?? null; // Filter value for Item_Name or Item_Sh_Name
        
            // Query builder
            $query = DB::connection('wax')->table('mst_item_master as m')
                ->select(
                    'm.Id', 'm.Cat_Id', 'm.Model_Id', 'm.Size_Id', 'm.Color_Id', 
                    'm.Item_Name', 'm.Item_Sh_Name', 'm.Unit_Id', 'm.Purchase_Gl', 
                    'm.Sales_Gl', 'm.CGST', 'm.SGST', 'm.IGST', 'm.Pur_Rate', 'm.Sale_Rate',
                    'c.Cat_Name',
                    DB::raw("(SELECT Intem_Qnty FROM mst_item_stock WHERE Item_Id = m.Id AND Event_Type = 'OB') AS Open_Qnty"),
                    DB::raw("(SELECT Item_Rate FROM mst_item_stock WHERE Item_Id = m.Id AND Event_Type = 'OB') AS Item_Rate")
                )
                ->join('mst_item_catagary as c', 'c.Id', '=', 'm.Cat_Id');
        
            // Apply search filter if provided
            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('m.Item_Name', 'LIKE', "%$searchTerm%")
                      ->orWhere('m.Item_Sh_Name', 'LIKE', "%$searchTerm%");
                });
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function update_item(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'item_id' => 'required',
            'open_date' => 'required',
            'cat_id' => 'required',
            'item_name' => 'required',
            'item_sh_name' => 'required',
            'item_unit' => 'required',
            'pur_ledg' => 'required',
            'sales_ledg' => 'required',
            'cgst' => 'required',
            'sgst' => 'required',
            'igst' => 'required',
            'pur_rate' => 'required',
            'sales_rate' => 'required',
            'open_qnty' => 'required',
            'item_rate' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_ITEM(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->item_id,$request->open_date,$request->cat_id,$request->item_mod,$request->item_size,$request->item_color,$request->item_name,$request->item_sh_name,$request->item_unit,$request->pur_ledg,$request->sales_ledg,$request->cgst,$request->sgst,$request->igst,$request->pur_rate,$request->sales_rate,$request->open_qnty,$request->item_rate,auth()->user()->Id,2]);

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
                    'message' => 'Item Successfully Updated !!',
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

    public function get_party_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            $head_id=0;
            switch ($request->type) {
                case 1:
                    $head_id = 7;
                    break;
                case 2:
                    $head_id = 10;
                    break;
                default:
                    throw new Exception("Invalid type value");
            }

            $sql = DB::connection('wax')->select("Select Id,Ledger_Name From mst_org_acct_ledger Where Sub_Head=?;",[$head_id]);

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

    public function process_party(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'party_type' => 'required',
            'party_Name' => 'required',
            'party_add' => 'required',
            'party_mob' => 'required',
            'under_ledger' => 'required',
            'open_balance' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PARTY(?,?,?,?,?,?,?,?,?,?,?,@error,@message,@party_id);",[null,$request->party_type,$request->party_Name,$request->party_add,$request->party_mob,$request->party_mail,$request->party_gst,$request->under_ledger,$request->open_balance,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error_No,@message As Message,@party_id As Id;");
            $error_No = $result[0]->Error_No;
            $message = $result[0]->Message;
            $party_Id = $result[0]->Id;

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
                    'message' => 'Party Successfully Added !!',
                    'details' => $party_Id,
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

    public function get_party_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $searchTerm = $request->keyword ?? null; // Filter value for Party_Name
        
            // Query builder with filtering
            $query = DB::connection('wax')->table('mst_party_master')
                ->select(
                    'Id',
                    DB::raw("CASE 
                                WHEN Party_Type = 1 THEN 'Debtor' 
                                WHEN Party_Type = 2 THEN 'Creditor' 
                             END AS Party_Tp"),
                    'Party_Type', 'Party_Name', 'Party_Add', 'Party_Mob', 
                    'Party_Mail', 'Party_Gst', 'Ledger_Id', 'Open_Bal'
                );
        
            // Apply search filter if provided
            if (!empty($searchTerm)) {
                $query->where('Party_Name', 'LIKE', "%$searchTerm%");
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            return response()->json([
                'message' => 'Data Found',
                'details' => $sql,
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function update_party(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'party_id' => 'required', 
            'party_type' => 'required',
            'party_Name' => 'required',
            'party_add' => 'required',
            'party_mob' => 'required',
            'under_ledger' => 'required',
            'open_balance' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_PARTY(?,?,?,?,?,?,?,?,?,?,?,@error,@message,@party_id);",[$request->party_id,$request->party_type,$request->party_Name,$request->party_add,$request->party_mob,$request->party_mail,$request->party_gst,$request->under_ledger,$request->open_balance,auth()->user()->Id,2]);

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
                    'message' => 'Party Successfully Updated !!',
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

    public function process_design(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'design_name' => 'required',
            'design_no' => 'required',
            'wt' => 'required',
            'wt_rate' => 'required',
            'deg_img' => 'required',
            'polish' => 'required',
            'design_array' => 'required',
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

                $design_details = $this->convertToObject($request->design_array);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists tempdetails;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table tempdetails
                                        (
                                            Item_Id				Int,
                                            Qnty                Int,
                                            Making_Rate         Numeric(18,3)
                                        );");
                foreach ($design_details as $design_data) {
                   DB::connection('wax')->statement("Insert Into tempdetails (Item_Id,Qnty,Making_Rate) Values (?,?,?);",[$design_data->item_id,$design_data->qnty,$design_data->making_rate]);
                }
                $img_name = null;
                if ($request->hasFile('deg_img')) {
                    $image = $request->file('deg_img');
                    $extension = strtolower($image->getClientOriginalExtension());
                    $allowedExtensions = ['jpeg', 'jpg', 'png'];
                    if(in_array($extension, $allowedExtensions)){
                        // Define the directory dynamically
                        $directory = 'design/' . $request->org_id;
                            
                        // Upload and compress the image
                        $path = $this->uploadAndCompressImage($image, 'img',$directory);
                        $img_name = $path;
                        // Save the path to the database or perform other actions
                    }
                    else{
                        throw new Exception("Invalid File Format !!");
                    }
        
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_DESIGN(?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->design_name,$request->design_no,$request->wt,$request->wt_rate,$request->polish,$img_name,auth()->user()->Id,1]);

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
                        'message' => 'Design Successfully Added !!',
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

    public function get_design_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception("Organization schema not found.");
            }
        
            $org_schema = $sql[0]->db;
            $db = config('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get pagination and filter parameters
            $perPage = $request->per_page ?? 10; // Default: 10 per page
            $page = $request->page ?? 1;
            $searchTerm = $request->keyword ?? null; // Filter value for Design_Name or Design_No
        
            // Query builder with filtering
            $query = DB::connection('wax')->table('mst_design_master as m')
                ->join('mst_design_details as d', 'd.Design_Id', '=', 'm.Id')
                ->join('mst_item_master as i', 'i.Id', '=', 'd.Item_Id')
                ->select(
                    'm.Id', 'm.Design_Name', 'm.Design_No', 'm.WT', 'm.Wt_Rate', 
                    'm.Polish', 'd.Item_Id', 'd.Qnty', 'd.Making_Rate', 
                    'm.Image', 'i.Item_Name', 'i.Item_Sh_Name'
                );
        
            // Apply search filter if provided
            if (!empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('m.Design_Name', 'LIKE', "%$searchTerm%")
                      ->orWhere('m.Design_No', 'LIKE', "%$searchTerm%");
                });
            }
        
            // Apply pagination
            $sql = $query->paginate($perPage, ['*'], 'page', $page);
        
            if ($sql->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
        
            // Formatting the response to group child rows
            $menu_set = [];
            foreach ($sql->items() as $row) {
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        'Id' => $row->Id,
                        'Design_Name' => $row->Design_Name,
                        'Design_No' => $row->Design_No,
                        'WT' => $row->WT,
                        'Wt_Rate' => $row->Wt_Rate,
                        'image' => $this->getUrl($request->org_id, $row->Image),
                        'File_Name' => $row->Image,
                        'Polish' => $row->Polish,
                        "childrow" => []
                    ];
                }
                if ($row->Item_Id) {
                    $menu_set[$row->Id]['childrow'][] = [
                        'Item_Id' => $row->Item_Id,
                        'Qnty' => $row->Qnty,
                        'Making_Rate' => $row->Making_Rate,
                        'Item_Name' => $row->Item_Name,
                        'Item_Sh_Name' => $row->Item_Sh_Name
                    ];
                }
            }
        
            // Convert associative array to indexed array for JSON response
            $menu_set = array_values($menu_set);
        
            return response()->json([
                'message' => 'Data Found',
                'details' => [
                    'current_page' => $sql->currentPage(),
                    'per_page' => $sql->perPage(),
                    'total' => $sql->total(),
                    'last_page' => $sql->lastPage(),
                    'data' => $menu_set,
                ],
            ], 200);
        
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
    }

    public function get_cat_item_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Item_Name From mst_item_master Where Cat_Id=?;",[$request->cat_id]);

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

    public function update_design(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'design_id' => 'required',
            'design_name' => 'required',
            'design_no' => 'required',
            'wt' => 'required',
            'wt_rate' => 'required',
            'polish' => 'required',
            'design_array' => 'required',
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

                $design_details = $this->convertToObject($request->design_array);
                $drop_table = DB::connection('wax')->statement("Drop Temporary Table If Exists tempdetails;");
                $create_tabl = DB::connection('wax')->statement("Create Temporary Table tempdetails
                                        (
                                            Item_Id				Int,
                                            Qnty                Int,
                                            Making_Rate         Numeric(18,3)
                                        );");
                foreach ($design_details as $design_data) {
                   DB::connection('wax')->statement("Insert Into tempdetails (Item_Id,Qnty,Making_Rate) Values (?,?,?);",[$design_data->item_id,$design_data->qnty,$design_data->making_rate]);
                }

                $img_name = null;
                if ($request->hasFile('deg_img')) {
                    $image = $request->file('deg_img');
                    $extension = strtolower($image->getClientOriginalExtension());
                    $allowedExtensions = ['jpeg', 'jpg', 'png'];
                    if(in_array($extension, $allowedExtensions)){
                        // Define the directory dynamically
                        $directory = 'design/' . $request->org_id;
                            
                        // Upload and compress the image
                        $path = $this->uploadAndCompressImage($image, 'img',$directory);
                        $img_name = $path;
                        // Save the path to the database or perform other actions
                    }
                    else{
                        throw new Exception("Invalid File Format !!");
                    }
        
                }
                else{
                    $img_name=$request->deg_img;
                }

                $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_DESIGN(?,?,?,?,?,?,?,?,?,@error,@message);",[$request->design_id,$request->design_name,$request->design_no,$request->wt,$request->wt_rate,$request->polish,$img_name,auth()->user()->Id,2]);

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
                        'message' => 'Design Successfully Updated !!',
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

    public function process_employee(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'emp_type' => 'required',
            'emp_name' => 'required',
            'emp_add' => 'required',
            'emp_mobile' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_EMPLOYEE(?,?,?,?,?,?,?,?,@error,@message);",[null,$request->emp_type,$request->emp_name,$request->emp_add,$request->emp_mobile,$request->emp_mail,auth()->user()->Id,1]);

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
                    'message' => 'Employee Successfully Added !!',
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

    public function get_emp_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;", [$request->org_id]);
            if (!$sql) {
                throw new Exception('Organization schema not found');
            }
            
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
        
            // Get filters and pagination parameters
            $empName = $request->input('keyword','');
            $perPage = $request->input('per_page', 10); // Default 10 records per page
            
            // Build query with optional filter
            $query = DB::connection('wax')->table('mst_employee_master')
                ->select(
                    'Id',
                    'Emp_Type',
                    DB::raw("CASE 
                                WHEN Emp_Type = 1 THEN 'Permanent' 
                                WHEN Emp_Type = 2 THEN 'Casual' 
                                WHEN Emp_Type = 3 THEN 'Contractual' 
                              END AS Employee_type"),
                    'Emp_Name',
                    'Emp_Address',
                    'Emp_Mobile',
                    'Emp_Mail'
                );
            
            if (!empty($empName)) {
                $query->where('Emp_Name', 'LIKE', "%$empName%");
            }
            
            $employees = $query->paginate($perPage);
            
            if ($employees->isEmpty()) {
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            
            return response()->json([
                'message' => 'Data Found',
                'details' => $employees,
            ], 200);
            
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ], 400);
        
            throw new HttpResponseException($response);
        }
        
    }

    public function update_employee(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'emp_id' => 'required',
            'emp_type' => 'required',
            'emp_name' => 'required',
            'emp_add' => 'required',
            'emp_mobile' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_EMPLOYEE(?,?,?,?,?,?,?,?,@error,@message);",[$request->emp_id,$request->emp_type,$request->emp_name,$request->emp_add,$request->emp_mobile,$request->emp_mail,auth()->user()->Id,2]);

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
                    'message' => 'Employee Successfully Updated !!',
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

    public function get_bank_ledger(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Ledger_Name From mst_org_acct_ledger Where Sub_Head=2;");

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

    public function process_bank_Account(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'bank_name' => 'required',
            'branch_Name' => 'required',
            'bank_ifsc' => 'required',
            'account_no' => 'required',
            'ledger_id' => 'required',
            'opening_date' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_ACCOUNT(?,?,?,?,?,?,?,?,?,?,@error,@message);",[null,$request->bank_name,$request->branch_Name,$request->bank_ifsc,$request->account_no,$request->ledger_id,$request->opening_date,$request->open_banalce,auth()->user()->Id,1]);

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
                    'message' => 'Bank Account Successfully Added !!',
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

    public function get_bank_acct_list(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select Id,Bank_Name,Branch_Name,Bank_IFSC,Account_No,Under_Ledger,Opening_Date,Opening_Balance From mst_bank_account;");

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

    public function update_bank_account(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'bank_id' => 'required',
            'bank_name' => 'required',
            'branch_Name' => 'required',
            'bank_ifsc' => 'required',
            'account_no' => 'required',
            'ledger_id' => 'required',
            'opening_date' => 'required',
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_BANK_ACCOUNT(?,?,?,?,?,?,?,?,?,?,@error,@message);",[$request->bank_id,$request->bank_name,$request->branch_Name,$request->bank_ifsc,$request->account_no,$request->ledger_id,$request->opening_date,$request->open_banalce,auth()->user()->Id,2]);

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
                    'message' => 'Bank Account Successfully Updated !!',
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

    public function get_item_rate(Request $request){
        try {
            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);

            $sql = DB::connection('wax')->select("Select UDF_GET_ITEM_RATE(?) As Rate;",[$request->item_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            return response()->json([
                'message' => 'Data Found',
                'details' => $sql[0]->Rate,
            ],200);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        } 
    }

    public function process_item_rate(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'item_id' => 'required',
            'item_rate' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_ITEM_RATE(?,?,?);",[$request->item_id,$request->item_rate,auth()->user()->Id]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }

                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Item Rate Store Successfully !!',
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

    public function process_work_process(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'process_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_WORK_PROCESS(?,?,?,?,@error,@message);",[null,$request->process_name,auth()->user()->Id,1]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error,@message As Message;");
            $error = $result[0]->Error;
            $message = $result[0]->Message;

            if($error<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Work Process Successfully Saved !!',
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

    public function get_work_list(Request $request){
        try {

            $sql = DB::select("Select UDF_GET_ORG_SCHEMA(?) as db;",[$request->org_id]);
            if(!$sql){
              throw new Exception;
            }
            $org_schema = $sql[0]->db;
            $db = Config::get('database.connections.mysql');
            $db['database'] = $org_schema;
            config()->set('database.connections.wax', $db);
            
            $sql = DB::connection('wax')->select("Select Id,Process_Name From mst_work_status Where Is_Active=1;");

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
           
                return response()->json([
                    'message' => 'Data Found',
                    'details' => $sql,
                ],200);
                 
        } catch (Exception $ex) {
            $response = response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function update_work_process(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'work_id' => 'required',
            'process_name' => 'required'
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
            $sql = DB::connection('wax')->statement("Call USP_ADD_EDIT_WORK_PROCESS(?,?,?,?,@error,@message);",[$request->work_id,$request->process_name,auth()->user()->Id,2]);

            if(!$sql){
                throw new Exception('Operation Error Found !!');
            }
            $result = DB::connection('wax')->select("Select @error As Error,@message As Message;");
            $error = $result[0]->Error;
            $message = $result[0]->Message;

            if($error<0){
                DB::connection('wax')->rollBack();
                return response()->json([
                    'message' => $message,
                    'details' => null,
                ],202);
            }
            else{
                DB::connection('wax')->commit();
                return response()->json([
                    'message' => 'Work Process Successfully Saved !!',
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
}