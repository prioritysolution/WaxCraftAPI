<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\ImageUpload;
use Hash;
use Exception;
use Session;
use DB;
use \stdClass;

Class ProcessOrganisation extends Controller
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

    public function process_org(Request $request){
        $validator = Validator::make($request->all(),[
            'org_name' =>'required',
            'org_add' =>'required',
            'org_mobile' => 'required',
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();
                
                $img_name = null;
                if ($request->hasFile('org_logo')) {
                    $image = $request->file('org_logo');
                    $extension = strtolower($image->getClientOriginalExtension());
                    $allowedExtensions = ['jpeg', 'jpg', 'png'];
                    if(in_array($extension, $allowedExtensions)){
                        // Define the directory dynamically
                        $directory = 'logo';
                            
                        // Upload and compress the image
                        $path = $this->uploadAndCompressImage($image, 'img',$directory);
                        $img_name = $path;
                        // Save the path to the database or perform other actions
                    }
                    else{
                        throw new Exception("Invalid File Format !!");
                    }
                }

                $sql = DB::statement("Call USP_ADD_ORG(?,?,?,?,?,?,?,?,?,?,@error,@messg);",[null,$request->org_name,$request->org_add,$request->org_mobile,$request->org_mail,$request->org_gst,$request->org_pan,$img_name,auth()->user()->Id,1]);
                
                if(!$sql){
                    throw new Exception;
                }

                $result = DB::select("Select @error As Error_No,@messg As Message");
                $error_No = $result[0]->Error_No;
                $message = $result[0]->Message;

                if($error_No<0){
                    DB::rollBack();
                    return response()->json([
                        'message' => $message,
                        'details' => null,
                    ],400);
                }
                else{
                    DB::commit();
                    return response()->json([
                        'message' => 'Organisation Successfully Added !!',
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
          ],400);
      
          throw new HttpResponseException($response);
        }
    }

    public function get_org_list(){
        try {
            
            $sql = DB::select("SELECT Id, Org_Name, Org_Address, Org_Mob, Org_Mail, Org_Gst, Org_Pan,Oeg_Logo FROM mst_org_register WHERE Is_Active = ?", [1]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $org_data =[];
            foreach ($sql as $org) {
               if(!isset($org_data[$org->Id])){
                $org_data[$org->Id]=[
                    'Id' => $org->Id,
                    'Org_Name' => $org->Org_Name,
                    'Org_Address' => $org->Org_Address,
                    'Org_Mob' => $org->Org_Mob,
                    'Org_Mail' => $org->Org_Mail,
                    'Org_Gst' => $org->Org_Gst,
                    'Org_Pan' => $org->Org_Pan,
                    'Org_Logo' => $this->getLogo($org->Oeg_Logo),
                ];
               }
            }
            $org_data = array_values($org_data);
            return response()->json([
                'message' => 'Data Found',
                'details' => $org_data
            ]);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function update_organisation(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' => 'required',
            'org_name' =>'required',
            'org_add' =>'required',
            'org_mobile' => 'required',
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();
                
                $img_name = null;
                if ($request->hasFile('org_logo')) {
                    $image = $request->file('org_logo');
                    $extension = strtolower($image->getClientOriginalExtension());
                    $allowedExtensions = ['jpeg', 'jpg', 'png'];
                    if(in_array($extension, $allowedExtensions)){
                        // Define the directory dynamically
                        $directory = 'logo';
                            
                        // Upload and compress the image
                        $path = $this->uploadAndCompressImage($image, 'img',$directory);
                        $img_name = $path;
                        // Save the path to the database or perform other actions
                    }
                    else{
                        throw new Exception("Invalid File Format !!");
                    }
                }

                $sql = DB::statement("Call USP_ADD_ORG(?,?,?,?,?,?,?,?,?,?,@error,@messg);",[$request->org_id,$request->org_name,$request->org_add,$request->org_mobile,$request->org_mail,$request->org_gst,$request->org_pan,$img_name,auth()->user()->Id,2]);
                
                if(!$sql){
                    throw new Exception;
                }

                $result = DB::select("Select @error As Error_No,@messg As Message");
                $error_No = $result[0]->Error_No;
                $message = $result[0]->Message;

                if($error_No<0){
                    DB::rollBack();
                    return response()->json([
                        'message' => $message,
                        'details' => null,
                    ],400);
                }
                else{
                    DB::commit();
                    return response()->json([
                        'message' => 'Organisation Successfully Added !!',
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
          ],400);
      
          throw new HttpResponseException($response);
        }
    }

    public function get_org_module(){
        try {
            
            $sql = DB::select("Select Id,Module_Name From mst_org_module Where Is_Active=? Order By Sl",[1]);

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

    public function get_org_active_module(Int $org_id){
        try {
            
            $sql = DB::select("Select Module_Id From map_org_module Where Is_Active=1 And Org_Id=?",[$org_id]);

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

    public function process_org_module(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'module_array' => 'required'
        ]);
        if($validator->passes()){
            try {

                DB::beginTransaction();

                $module_list = $this->convertToObject($request->module_array);
                $drop_table = DB::statement("Drop Temporary Table If Exists tempmodule;");
                $create_tabl = DB::statement("Create Temporary Table tempmodule
                                        (
                                            Module_Id				Int
                                        );");
                foreach ($module_list as $module) {
                   DB::statement("Insert Into tempmodule (Module_Id) Values (?);",[$module->module_id]);
                }

                $sql = DB::statement("Call USP_ADD_ORG_MODULE(?,?);",[$request->org_id,auth()->user()->Id]);

                if(!$sql){
                    throw new Exception;
                }

                    DB::commit();
                    return response()->json([
                        'message' => "Org Module Mapped Successfully !!",
                        'details' => null,
                    ],200);

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

    public function get_active_rental(Int $org_id){
        try {
            
            $sql = DB::select("Select DATE_ADD(Valid_Till, INTERVAL 1 DAY) As Date From mst_org_rental Where Is_Active=1 And Org_Id=?",[$org_id]);

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

    public function process_org_rental(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'frm_date' =>'required',
            'to_date' => 'required',
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_RENTAL(?,?,?,?);",[$request->org_id,$request->frm_date,$request->to_date,auth()->user()->Id]);

                if(!$sql){
                    throw new Exception;
                }

                    DB::commit();
                    return response()->json([
                        'message' => 'Rental Data Added Successfully !!',
                        'details' => null,
                    ],200);

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
          ],400);
      
          throw new HttpResponseException($response);
        }
    }

    public function get_year_start_date(Int $org_id){
        try {
            
            $sql = DB::select("Select DATE_ADD(End_Date, INTERVAL 1 DAY) As Date From mst_org_financial_year Where Is_Active=1 And Org_Id=?",[$org_id]);

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

    public function process_org_fin_year(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'frm_date' =>'required',
            'to_date' => 'required',
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_FIN_YEAR(?,?,?,?);",[$request->org_id,$request->frm_date,$request->to_date,auth()->user()->Id]);

                if(!$sql){
                    throw new Exception;
                }

                    DB::commit();
                    return response()->json([
                        'message' => 'Financial Year Added Successfully !!',
                        'details' => null,
                    ],200);

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
          ],400);
      
          throw new HttpResponseException($response);
        }
    }

    public function process_org_admin_user(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'user_name' =>'required',
            'user_mail' => 'required',
            'user_mob' => 'required',
            'user_pass' => 'required'
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_USER(?,?,?,?,?,?,?,?,?,@error,@messg);",[null,$request->org_id,$request->user_name,$request->user_mail,$request->user_mob,Hash::make($request->user_pass),1,auth()->user()->Id,1]);

                if(!$sql){
                    throw new Exception;
                }

                $result = DB::select("Select @error As Error_No,@messg As Message");
                $error_No = $result[0]->Error_No;
                $message = $result[0]->Message;

                if($error_No<0){
                    DB::rollBack();
                    return response()->json([
                        'message' => $message,
                        'details' => null,
                    ],400);
                }
                else{
                    DB::commit();
                    return response()->json([
                        'message' => 'Admin User Successfully Added !!',
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
          ],400);
      
          throw new HttpResponseException($response);
        }
    }
}