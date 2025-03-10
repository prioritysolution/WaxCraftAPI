<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Admin Controller Start

use App\Http\Controllers\Admin\AdminLogin;
use App\Http\Controllers\Admin\ProcessOrganisation;

// Admin Controller End

// User Controller Start

use App\Http\Controllers\Organisation\ProcessUserLogin;
use App\Http\Controllers\Organisation\ProcessMaster;
use App\Http\Controllers\Organisation\ProcessInventory;
use App\Http\Controllers\Organisation\ProcessAccounting;
use App\Http\Controllers\Organisation\ProcessInventoryReport;
use App\Http\Controllers\Organisation\ProcessAccountingReport;

// User Controller End

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Admin Route Define Area

Route::post('/Admin/ProcessLogin',[AdminLogin::class,'process_admin_login'])->middleware('api_access');

Route::group([
    'middleware' => ['auth:sanctum',]
],function(){

// Dashboard Route

Route::get('/Admin/GetSideBar',[AdminLogin::class,'get_admin_sidebar']);
Route::get('/Admin/LogOut',[AdminLogin::class,'process_logout']);

// End Dashboard Route

// Process Organisation

Route::post('/Admin/ProcessOrganisation/AddOrg',[ProcessOrganisation::class,'process_org']);
Route::get('/Admin/ProcessOrganisation/OrgList',[ProcessOrganisation::class,'get_org_list']);
Route::post('/Admin/ProcessOrganisation/UpdateOrganisation',[ProcessOrganisation::class,'update_organisation']);
Route::get('/Admin/ProcessOrganisation/GetOrgModule',[ProcessOrganisation::class,'get_org_module']);
Route::get('/Admin/ProcessOrganisation/GetActiveModule/{org_id}',[ProcessOrganisation::class,'get_org_active_module']);
Route::post('/Admin/ProcessOrganisation/MapModule',[ProcessOrganisation::class,'process_org_module']);
Route::get('/Admin/ProcessOrganisation/GetActiveRental/{org_id}',[ProcessOrganisation::class,'get_active_rental']);
Route::post('/Admin/ProcessOrganisation/PostRental',[ProcessOrganisation::class,'process_org_rental']);
Route::get('/Admin/ProcessOrganisation/GetYearDate/{org_id}',[ProcessOrganisation::class,'get_year_start_date']);
Route::post('/Admin/ProcessOrganisation/AddFinYear',[ProcessOrganisation::class,'process_org_fin_year']);
Route::post('/Admin/ProcessOrganisation/AddAdminUser',[ProcessOrganisation::class,'process_org_admin_user']);

// End Process Organisation

});


// End Admin Route Area


// User Route Area

Route::post('/User/ProcessLogin',[ProcessUserLogin::class,'process_user_login'])->middleware('api_access');
Route::get('/User/GetOtp',[ProcessUserLogin::class,'genereate_otp']);
Route::get('/User/VerefyOtp',[ProcessUserLogin::class,'verify_otp']);
Route::put('/User/UpdtePassword',[ProcessUserLogin::class,'update_password']);

Route::group([
    'middleware' => ['auth:sanctum',]
],function(){

    // Dashboard Route

    Route::get('/Org/GetSidebar/{org_id}',[ProcessUserLogin::class,'get_user_sidebar']);
    Route::get('/Org/GetUserProfile',[ProcessUserLogin::class,'get_user_profile']);
    Route::put('/Org/UpdateUserProfile',[ProcessUserLogin::class,'update_user_profile']);
    Route::get('/Org/GetUserRele',[ProcessUserLogin::class,'get_user_role']);
    Route::post('/Org/AddUser',[ProcessUserLogin::class,'process_user']);
    Route::get('/Org/GetUserList',[ProcessUserLogin::class,'get_user_list']);
    Route::put('/Org/UpdateUser',[ProcessUserLogin::class,'update_org_user']);
    Route::get('/Org/GetRoleMenue',[ProcessUserLogin::class,'get_role_menue']);
    Route::post('/Org/MapUserRole',[ProcessUserLogin::class,'map_user_role']);
    Route::get('/Org/LogOut',[ProcessUserLogin::class,'process_logout']);
    Route::get('/Org/GetAccessUserList',[ProcessUserLogin::class,'get_active_user']);
    Route::put('/Org/UpdateUserAccess',[ProcessUserLogin::class,'process_user_access']);

    // End Dashboard Route

    // Master Route Start

    Route::post('/Org/Master/AddCatagory',[ProcessMaster::class,'process_catagory']);
    Route::get('/Org/Master/GetCatagory',[ProcessMaster::class,'get_catagory_list']);
    Route::put('/Org/Master/UpdateCatagory',[ProcessMaster::class,'update_catagory']);
    Route::post('/Org/Master/AddModel',[ProcessMaster::class,'process_item_model']);
    Route::get('/Org/Master/GetModel',[ProcessMaster::class,'get_model_list']);
    Route::put('/Org/Master/UpdateModel',[ProcessMaster::class,'update_model']);
    Route::get('/Org/Master/GetCatagoryModel',[ProcessMaster::class,'get_catagory_model']);
    Route::post('/Org/Master/AddSize',[ProcessMaster::class,'process_item_size']);
    Route::get('/Org/Master/GetSize',[ProcessMaster::class,'get_size_list']);
    Route::get('/Org/Master/GetModuleSize',[ProcessMaster::class,'get_module_size']);
    Route::put('/Org/Master/UpdateSize',[ProcessMaster::class,'update_item_size']);
    Route::post('/Org/Master/AddUnit',[ProcessMaster::class,'process_item_unit']);
    Route::get('/Org/Master/GetUnitList',[ProcessMaster::class,'get_unit_list']);
    Route::put('/Org/Master/UpdateUnit',[ProcessMaster::class,'update_unit']);
    Route::post('/Org/Master/AddSizeColor',[ProcessMaster::class,'process_size_color']);
    Route::get('/Org/Master/GetSizeColor',[ProcessMaster::class,'get_size_color_list']);
    Route::put('/Org/Master/UpdateSizeColor',[ProcessMaster::class,'update_size_color']);
    Route::get('/Org/Master/GetSizeWiseColor',[ProcessMaster::class,'get_size_wise_color']);
    Route::get('/Org/Master/GetAcctMainHead',[ProcessMaster::class,'get_account_head']);
    Route::post('/Org/Master/AddAccountHead',[ProcessMaster::class,'process_acct_head']);
    Route::get('/Org/Master/GetAccountHead',[ProcessMaster::class,'get_acct_head_list']);
    Route::put('/Org/Master/UpdateAccountHead',[ProcessMaster::class,'update_acct_head']);
    Route::post('/Org/Master/AddAccountLedger',[ProcessMaster::class,'process_acct_ledger']);
    Route::get('/Org/Master/GetAccountLedger',[ProcessMaster::class,'get_acct_ledger_list']);
    Route::put('/Org/Master/UpdateAccountLedger',[ProcessMaster::class,'update_acct_ledger']);
    Route::get('/Org/Master/GetPurchaseLedger',[ProcessMaster::class,'get_purchase_ledger']);
    Route::get('/Org/Master/GetSalesLedger',[ProcessMaster::class,'get_sales_ledger']);
    Route::post('/Org/Master/AddItem',[ProcessMaster::class,'process_item']);
    Route::get('/Org/Master/GetItem',[ProcessMaster::class,'get_item_list']);
    Route::put('/Org/Master/UpdateItem',[ProcessMaster::class,'update_item']);
    Route::get('/Org/Master/GetPartyLedger',[ProcessMaster::class,'get_party_ledger']);
    Route::post('/Org/Master/AddParty',[ProcessMaster::class,'process_party']);
    Route::get('/Org/Master/GetPartyList',[ProcessMaster::class,'get_party_list']);
    Route::put('/Org/Master/UpdateParty',[ProcessMaster::class,'update_party']);
    Route::post('/Org/Master/AddDesign',[ProcessMaster::class,'process_design']);
    Route::get('/Org/Master/GetDesign',[ProcessMaster::class,'get_design_list']);
    Route::get('/Org/Master/GetCatItem',[ProcessMaster::class,'get_cat_item_list']);
    Route::post('/Org/Master/UpdateDesign',[ProcessMaster::class,'update_design']);
    Route::post('/Org/Master/AddEmployee',[ProcessMaster::class,'process_employee']);
    Route::get('/Org/Master/GetEmployeeList',[ProcessMaster::class,'get_emp_list']);
    Route::put('/Org/Master/UpdateEmployee',[ProcessMaster::class,'update_employee']);
    Route::get('/Org/Master/GetBankLedger',[ProcessMaster::class,'get_bank_ledger']);
    Route::post('/Org/Master/AddBankAccount',[ProcessMaster::class,'process_bank_Account']);
    Route::get('/Org/Master/GetBankAccount',[ProcessMaster::class,'get_bank_acct_list']);
    Route::put('/Org/Master/UpdateBankAccount',[ProcessMaster::class,'update_bank_account']);
    Route::get('/Org/Master/GetItemRate',[ProcessMaster::class,'get_item_rate']);
    Route::post('/Org/Master/PostItemRate',[ProcessMaster::class,'process_item_rate']);
    Route::post('/Org/Master/AddWorkProcess',[ProcessMaster::class,'process_work_process']);
    Route::get('/Org/Master/GetWorkProcess',[ProcessMaster::class,'get_work_list']);
    Route::put('/Org/Master/UpdateWorkProcess',[ProcessMaster::class,'update_work_process']);

    // End Master Route

    // Inventory Voucher Route Start

    Route::get('/Org/ProcessInventory/GetOrderParty',[ProcessInventory::class,'get_debtor_list']);
    Route::get('/Org/ProcessInventory/GetOrderDesign',[ProcessInventory::class,'get_order_design']);
    Route::get('/Org/ProcessInventory/GetDesignDetails',[ProcessInventory::class,'get_design_details']);
    Route::post('/Org/ProcessInventory/PostOrder',[ProcessInventory::class,'process_order']);
    Route::get('/Org/ProcessInventory/GetActiveOrder',[ProcessInventory::class,'get_active_order_list']);
    Route::put('/Org/ProcessInventory/CancelOrder',[ProcessInventory::class,'cancle_order']);
    Route::get('/Org/ProcessInventory/GetWorkStatus',[ProcessInventory::class,'get_work_status']);
    Route::post('/Org/ProcessInventory/ProcessOrder',[ProcessInventory::class,'process_work_order']);
    Route::get('/Org/ProcessInventory/GetInvoiseOrder',[ProcessInventory::class,'get_complete_order_list']);
    Route::put('/Org/ProcessInventory/FinalOrderProcess',[ProcessInventory::class,'process_final_process']);
    Route::post('/Org/ProcessInventory/PostInvoise',[ProcessInventory::class,'process_invoise']);
    Route::put('/Org/ProcessInventory/CalcelInvoise',[ProcessInventory::class,'cancel_invoise']);
    Route::get('/Org/ProcessInventory/GetInvoisePrint',[ProcessInventory::class,'process_print_invoise']);
    Route::get('/Org/ProcessInventory/InvoiseList',[ProcessInventory::class,'get_invoise_list']);
    Route::get('/Org/ProcessInventory/GetPurchaseParty',[ProcessInventory::class,'get_pur_party_list']);
    Route::post('/Org/ProcessInventory/PostPurchase',[ProcessInventory::class,'process_purchase']);
    Route::get('/Org/ProcessInventory/GetPurchaseList',[ProcessInventory::class,'get_pur_list']);
    Route::put('/Org/ProcessInventory/CancelPurchase',[ProcessInventory::class,'cancel_purchase']);
    Route::post('/Org/ProcessInventory/AddGstBill',[ProcessInventory::class,'process_gst_bill']);
    // Inventory Voucher Route End

    // Accounting Voucher Route Start

    Route::get('/Org/ProcessAccounting/GetLedgerList',[ProcessAccounting::class,'get_ledger_list']);
    Route::get('/Org/ProcessAccounting/CheckParty',[ProcessAccounting::class,'get_ledger_party']);
    Route::post('/Org/ProcessAccounting/PostReceiptsVoucher',[ProcessAccounting::class,'process_receipts_voucher']);
    Route::get('/Org/ProcessAccounting/GetReceiptVoucher',[ProcessAccounting::class,'get_recpt_list']);
    Route::put('/Org/ProcessAccounting/CancelReceiptVoucher',[ProcessAccounting::class,'cancel_recpt_voucher']);
    Route::post('/Org/ProcessAccounting/PostPaymentVoucher',[ProcessAccounting::class,'process_payment_voucher']);
    Route::get('/Org/ProcessAccounting/GetPaymentVoucher',[ProcessAccounting::class,'get_payment_list']);
    Route::put('/Org/ProcessAccounting/CancelPaymentVoucher',[ProcessAccounting::class,'cancel_payment_voucher']);
    Route::get('/Org/ProcessAccounting/GetBankBalance',[ProcessAccounting::class,'get_bank_balance']);
    Route::post('/Org/ProcessAccounting/PostBankDeposit',[ProcessAccounting::class,'process_bank_deposit']);
    Route::get('/Org/ProcessAccounting/GetBankDeposit',[ProcessAccounting::class,'get_bank_dep_list']);
    Route::put('/Org/ProcessAccounting/CancelBankDeposit',[ProcessAccounting::class,'cancel_bank_deposit']);
    Route::post('/Org/ProcessAccounting/PostBankWithdrwan',[ProcessAccounting::class,'process_bank_withdrwan']);
    Route::get('/Org/ProcessAccounting/GetBankWithdrwan',[ProcessAccounting::class,'get_bank_with_list']);
    Route::put('/Org/ProcessAccounting/CancelBankWithdrwan',[ProcessAccounting::class,'cancel_bank_withdrwan']);
    Route::post('/Org/ProcessAccounting/PostBankTransfer',[ProcessAccounting::class,'process_bank_transfer']);
    Route::get('/Org/ProcessAccounting/GetBankTransfer',[ProcessAccounting::class,'list_bank_transfer']);
    Route::put('/Org/ProcessAccounting/CancelBankTransfer',[ProcessAccounting::class,'cancel_bank_transfer']);
    Route::get('/Org/ProcessAccounting/GetTrailorUser',[ProcessAccounting::class,'get_trailor_list']);
    Route::get('/Org/ProcessAccounting/GetTrailorBalance',[ProcessAccounting::class,'get_trailor_balance']);
    Route::post('/Org/ProcessAccounting/AddTlrTrans',[ProcessAccounting::class,'process_tlr_trans']);

    // Accounting Voucher Route End

    // Inventory Report

    Route::get('/Org/ProcessInventoryReport/OrderBook',[ProcessInventoryReport::class,'process_order_book']);
    Route::get('/Org/ProcessInventoryReport/SalesRegister',[ProcessInventoryReport::class,'process_sales_register']);
    Route::get('/Org/ProcessInventoryReport/PurchaseRegister',[ProcessInventoryReport::class,'process_purchase_register']);
    Route::get('/Org/ProcessInventoryReport/GetPartyLedger',[ProcessInventoryReport::class,'process_party_ledger']);
    Route::get('/Org/ProcessInventoryReport/GetPartyItemLedger',[ProcessInventoryReport::class,'process_party_item_ledger']);

    // End Inventory Report

    // Accounting Report

    Route::get('/Org/ProcessAccountingReport/Daybook',[ProcessAccountingReport::class,'process_daybook']);
    Route::get('/Org/ProcessAccountingReport/BankLedger',[ProcessAccountingReport::class,'process_bank_ledger']);
    Route::get('/Org/ProcessAccountingReport/GetLedger',[ProcessAccountingReport::class,'process_ledger']);
    Route::get('/Org/ProcessAccountingReport/GetAccountLedger',[ProcessAccountingReport::class,'process_acct_ledger']);
    Route::get('/Org/ProcessAccountingReport/CashBook',[ProcessAccountingReport::class,'process_cashbook']);
    Route::get('/Org/ProcessAccountingReport/TlrBook',[ProcessAccountingReport::class,'process_tlrbook']);
    // End Accounting Report
});

// User Route Area End Here