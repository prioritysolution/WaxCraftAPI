<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Hash;
use Exception;
use Session;
use DB;
use \stdClass;

class AdminLogin extends Controller
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

    public function process_admin_login(Request $request){
        $validator = Validator::make($request->all(),[
            'email' =>'required|email',
            'password' =>'required'
        ]);

        if($validator->passes()){
            try {
            
                $sql = DB::statement("Call USP_PUSH_ADMIN_LOGIN(?,@user_pass,@error,@message);",[$request->email]);

                if(!$sql){
                    throw new Exception("Operation Could Not Be Complete");
                }
                $result = DB::select("Select @user_pass As Pass,@error As Error_No,@message As Message");
                $db_error = $result[0]->Error_No;
                $db_message = $result[0]->Message;
                $user_pass = $result[0]->Pass;

                if($db_error<0){
                    $response = response()->json([
                        'message' => $db_message,
                        'details' => null,
                    ],202);
        
                    return $response;
                }
                else{

                    if(Hash::check($request->password, $user_pass)){
                        $user = User::where("User_Mail", $request->email)->first();
                        $token = $user->CreateToken("AdminAuthAPI")->plainTextToken;
                        return response()->json([
                            'message' => 'Login Successful',
                            'token'=>$token
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

    public function get_admin_sidebar(){
        try {
            $result = DB::select("CALL USP_GET_ADMIN_DASHBOARD(?);", [auth()->user()->Id]);
            
            if (empty($result)) {
                // Custom validation for no data found
                return response()->json([
                    'message' => 'No Data Found',
                    'details' => null,
                ], 202);
            }

            $menu_set = [];
            
            foreach ($result as $row) {
                if (!isset($menu_set[$row->Id])) {
                    $menu_set[$row->Id] = [
                        "title" => $row->Module_Name,
                        "Icon" => $row->Icon,
                        "path" => $row->Page_Alies,
                        "childLinks" => []
                    ];
                }
                if ($row->Menue_Name) {
                    $menu_set[$row->Id]['childLinks'][] = [
                        "Menue_Name" => $row->Menue_Name,
                        "Icon" => $row->Icon,
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