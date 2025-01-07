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
            
                $sql = DB::statement("Call USP_PUSH_ORG_USER_LOGIN(?,?,@org_id,@user_pass,@org_name,@org_add,@org_mob,@org_gst,@org_pan,@org_fin_id,@org_fin_start,@org_fin_end,@error,@message);",[$request->email,$request->date]);

                if(!$sql){
                    throw new Exception("Operation Could Not Be Complete");
                }
                $result = DB::select("Select @user_pass As Pass,@error As Error_No,@message As Message,@org_id As org_id,@org_name As Org_Name,@org_add As Org_Add,@org_mob As Org_Mob,@org_gst As Org_Gst,@org_pan As Org_Pan,@org_fin_id As Fin_Id,@org_fin_start As Fin_Start,@org_fin_end As Fin_End");
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
                            'Fin_End' => $Fin_End
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
                        "path" => $row->Page_Alies,
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
}