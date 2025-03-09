<?php

namespace App\Http\Controllers\Organisation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\OrgUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Hash;
use Exception;
use Session;
use DB;
use \stdClass;

class ProcessUserLogin extends Controller
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

    public function process_user_login(Request $request){
        $validator = Validator::make($request->all(),[
            'email' =>'required|email',
            'password' =>'required',
            'date' => 'required'
        ]);

        if($validator->passes()){
            try {
            
                $sql = DB::statement("Call USP_PUSH_ORG_USER_LOGIN(?,?,@org_id,@user_pass,@org_name,@org_add,@org_mob,@org_gst,@org_pan,@org_fin_id,@org_fin_start,@org_fin_end,@user_name,@error,@message);",[$request->email,$request->date]);

                if(!$sql){
                    throw new Exception("Operation Could Not Be Complete");
                }
                $result = DB::select("Select @user_pass As Pass,@error As Error_No,@message As Message,@org_id As org_id,@org_name As Org_Name,@org_add As Org_Add,@org_mob As Org_Mob,@org_gst As Org_Gst,@org_pan As Org_Pan,@org_fin_id As Fin_Id,@org_fin_start As Fin_Start,@org_fin_end As Fin_End,@user_name As User_Name");
                $db_error = $result[0]->Error_No;
                $db_message = $result[0]->Message;
                $user_pass = $result[0]->Pass;
                $org_id = $result[0]->org_id;
                $Org_Name = $result[0]->Org_Name;
                $Org_Add = $result[0]->Org_Add;
                $Org_Mob = $result[0]->Org_Mob;
                $Org_Gst = $result[0]->Org_Gst;
                $Org_Pan = $result[0]->Org_Pan;
                $Fin_Id = $result[0]->Fin_Id;
                $Fin_Start = $result[0]->Fin_Start;
                $Fin_End = $result[0]->Fin_End;
                $User_Name = $result[0]->User_Name;

                if($db_error<0){
                    $response = response()->json([
                        'message' => $db_message,
                        'details' => null,
                    ],202);
        
                    return $response;
                }
                else{

                    if(Hash::check($request->password, $user_pass)){
                        $user = OrgUser::where("User_Mail", $request->email)->first();
                        $token = $user->CreateToken("UserAuthAPI")->plainTextToken;
                        return response()->json([
                            'message' => 'Login Successful',
                            'token'=>$token,
                            'org_id' =>$org_id,
                            'Org_Name' => $Org_Name,
                            'Org_Add' => $Org_Add,
                            'Org_Mob' => $Org_Mob,
                            'Org_Gst' => $Org_Gst,
                            'Org_Pan' => $Org_Pan,
                            'Fin_Id' => $Fin_Id,
                            'Fin_Start' => $Fin_Start,
                            'Fin_End' => $Fin_End,
                            'User_Name' => $User_Name
                        ],200);
                    }
                    else{
                        $response = response()->json([
                            'message' => 'Invalid Password',
                            'details' => null
                        ],202);
                    
                        return $response;
                    }
                }

            } catch (Exception $ex) {
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

    public function get_user_sidebar(Int $org_id){
        try {
            $result = DB::select("CALL USP_GET_ORG_USER_DASHBOARD(?,?);", [$org_id,auth()->user()->Id]);
            
            if (empty($result)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            $menu_set = [];
            
            foreach ($result as $row) {
                if (!isset($menu_set[$row->Module_Id])) {
                    $menu_set[$row->Module_Id] = [
                        "title" => $row->Sub_Module_Name,
                        "Icon" => $row->Icon,
                        "path" => $row->Main_Page,
                        "childLinks" => []
                    ];
                }
                if ($row->Menue_Name) {
                    $menu_set[$row->Module_Id]['childLinks'][] = [
                        "Menue_Name" => $row->Menue_Name,
                        "Icon" => $row->Menu_Icon,
                        "Page_Allies" => $row->Page_Alies
                    ];
                }
            }
    
            $menu_set = array_values($menu_set);
    
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set
            ], 200);
    
        } catch (Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'details' => null,
            ], 400);
        }
    }

    public function process_logout(){
        auth()->user()->tokens()->delete();
        return response()->json([
            'message' => 'Logout Successfull',
            'details' => null,
        ],200);
    }

    public function get_user_profile(){
        try {
            
            $sql = DB::select("Select m.Id,m.User_Name,m.User_Mob,m.User_Mail,r.Role_Name,m.Role_Id From mst_org_user m Join mst_org_user_role r On r.Id=m.Role_Id Where m.Id=?;",[auth()->user()->Id]);

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

    public function update_user_profile(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'user_id' => 'required',
            'user_name' =>'required',
            'user_mail' => 'required',
            'user_mob' => 'required',
            'user_role' => 'required'
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_USER(?,?,?,?,?,?,?,?,?,@error,@messg);",[$request->user_id,$request->org_id,$request->user_name,$request->user_mail,$request->user_mob,Hash::make($request->user_pass),$request->user_role,auth()->user()->Id,2]);

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
                        'message' => 'User Profile Successfully Updated !!',
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

    public function get_user_role(){
        try {
            
            $sql = DB::select("SELECT Id,Role_Name From mst_org_user_role;");

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

    public function process_user(Request $request){
        $validator = Validator::make($request->all(),[
            'org_id' =>'required',
            'user_name' =>'required',
            'user_mail' => 'required',
            'user_mob' => 'required',
            'user_role' => 'required',
            'user_pass' => 'required'
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_USER(?,?,?,?,?,?,?,?,?,@error,@messg);",[null,$request->org_id,$request->user_name,$request->user_mail,$request->user_mob,Hash::make($request->user_pass),$request->user_role,1,1]);

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
                        'message' => 'User Successfully Added !!',
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

    public function get_user_list(Request $request){
        try {
            
            $sql = DB::select("Select m.Id,m.Org_Id,m.Role_Id,m.User_Name,m.User_Mail,m.User_Mob,r.Role_Name From mst_org_user m Join mst_org_user_role r On r.Id=m.Role_Id Where m.Is_Active=1 And m.Org_Id=?;",[$request->org_id]);

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

    public function update_org_user(Request $request){
        $validator = Validator::make($request->all(),[
            'user_id' => 'required',
            'user_name' =>'required',
            'user_mail' => 'required',
            'user_mob' => 'required',
            'user_role' => 'required',
        ]);

        if($validator->passes()){
            try {

                DB::beginTransaction();

                $sql = DB::statement("Call USP_ADD_ORG_USER(?,?,?,?,?,?,?,?,?,@error,@messg);",[$request->user_id,$request->org_id,$request->user_name,$request->user_mail,$request->user_mob,Hash::make($request->user_pass),$request->user_role,1,2]);

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
                        'message' => 'User Successfully Added !!',
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

    public function get_role_menue(Request $request){
        try {
            
            $sql = DB::select("Select m.Id As Sub_Module_Id,m.Sub_Module_Name,mn.Id As Menue_Id,mn.Menue_Name,mn.Sub_Module_Id From mst_org_sub_module m Left Join mst_org_module_menue mn on mn.Sub_Module_Id=m.Id Where m.Module_Id In (Select Module_Id From map_org_module Where Org_Id=? And Is_Active=1) And m.Id Not In (1,10) Order By m.SL,mn.SL;",[$request->org_id]);

            if (empty($sql)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }
            $menue_set = [];
            foreach ($sql as $menue) {
                if(!isset($menu_set[$menue->Sub_Module_Id])){
                    $menu_set[$menue->Sub_Module_Id]=[
                        "Module_Id" => $menue->Sub_Module_Id,
                        "Module_Name" => $menue->Sub_Module_Name,
                        "ChildRow" => [],
                    ];
                }
                if($menue->Menue_Id){
                    if(!isset($menu_set[$menue->Sub_Module_Id]["ChildRow"][$menue->Menue_Id])){
                        $menu_set[$menue->Sub_Module_Id]["ChildRow"][]=[
                            "Menue_Id" => $menue->Menue_Id,
                            "menue_Name" => $menue->Menue_Name
                        ];
                    }
                }
                
            }
            $menu_set = array_values($menu_set);
            return response()->json([
                'message' => 'Data Found',
                'details' => $menu_set
            ]);

        } catch (Exception $ex) {
            $response = response()->json([
                'message' => 'Error Found',
                'details' => $ex->getMessage(),
            ],400);

            throw new HttpResponseException($response);
        }
    }

    public function map_user_role(Request $request){
        $validator = Validator::make($request->all(),[
           'user_id' => 'required',
           'Module_Array' => 'required'
        ]);
        if($validator->passes()){
            try {

                DB::beginTransaction();

                $module_details = $this->convertToObject($request->Module_Array);
                $drop_table = DB::statement("Drop Temporary Table If Exists tempmodule;");
                $create_tabl = DB::statement("Create Temporary Table tempmodule
                                            (
                                                Module_Id		Int,
                                                Menue_Id		Int
                                            );");
                foreach ($module_details as $module_data) {
                   DB::statement("Insert Into tempmodule (Module_Id,Menue_Id) Values (?,?);",[$module_data->module_id,$module_data->menue_id]);
                }

                $sql = DB::statement("Call USP_MAP_USER_ROLE(?,@error,@message);",[$request->user_id]);

                if(!$sql){
                    throw new Exception;
                }
                $result = DB::select("Select @error As Error_No,@message As Message;");
                $error_No = $result[0]->Error_No;
                $message = $result[0]->Message;
    
                if($error_No<0){
                    DB::rollBack();
                    return response()->json([
                        'message' => $message,
                        'details' => null,
                    ],202);
                }
                else{
                    DB::commit();
                    return response()->json([
                        'message' => 'User Module Successfully Mapped !!',
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