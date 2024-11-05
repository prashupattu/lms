<?php

namespace App\Http\Controllers\Admin;

use App\Country;
use App\Currency;
use App\Http\Controllers\Controller;
use App\Lib\BaseForm;
use App\Lib\Files;
use App\Lib\HelperTrait;
use App\Setting;
use App\TestGrade;
use App\V2\Form\FieldFilter;
use App\V2\Form\FieldForm;
use App\V2\Form\SettingForm;
use App\V2\Model\RegistrationFieldTable;
use App\V2\Model\RoleTable;
use App\V2\Model\SettingTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laminas\Form\Element\Select;
use Laminas\InputFilter\InputFilter;

class SettingController extends Controller
{
    use HelperTrait;

    public function index(Request $request)
    {
        $output = [];
        $settingTable = new SettingTable();
        $settingForm = new SettingForm(null,$this->getServiceLocator());
      //  $rowset = $settingTable->getRecords();

        if (saas()){
            $rowset = Setting::where('key','!=','general_video_max_size')->get();
        }
        else{
            $rowset = Setting::get();
        }

        //$rowset->buffer();
        if(request()->isMethod('post'))
        {

            $formData = request()->all();

            foreach($rowset as $row){
                if (isset($_POST[$row->key])){
                    $settingTable->saveSetting($row->key,$formData[$row->key]);
                }

            }
            $output['flash_message']=__lang('Changes Saved!');
            $settingForm->setData($formData);

            if(isset($_SESSION['currency'])){
                unset($_SESSION['currency']);
            }

            //update default currency
            $countryId = $formData['country_id'];
            //check if country exists
            $currency = Currency::where('country_id',$countryId)->first();
            if(!$currency){
                $currency = new Currency();
                $currency->country_id= $countryId;
            }
            $currency->exchange_rate = 1;
            $currency->save();

        }
        else{
            $data = [];
            foreach($rowset as $row){
                $data[$row->key] = $row->value;
            }
            $settingForm->setData($data);

            $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
            $output['display_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }

        $logo = $settingTable->getSetting('image_logo');
        if(!empty($logo)){
            $output['logo']= resizeImage($logo, 100, 100,$this->getBaseUrl());
        }
        else{
            $output['logo']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }

        $icon = $settingTable->getSetting('image_icon');
        if(!empty($icon)){
            $output['icon'] =  resizeImage($icon, 100, 100,$this->getBaseUrl());

        }
        else{
            $output['icon']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }
        $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        $output['settings'] = $rowset;
        $output['form']=$settingForm;
        $output['pageTitle'] = __lang('Site Settings');
        $output['siteUrl'] = $this->getBaseUrl();
        return view('admin.setting.index',$output);

    }




    public function fields(Request $request){

        $table = new RegistrationFieldTable();

        $paginator = $table->getPaginatedRecords(true);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Custom Student Fields'),
        ));

    }


    public function addfield(Request $request){

        $output = array();
        $table = new RegistrationFieldTable();
        $form = new FieldForm();
        $filter = new FieldFilter();

        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();
                $array[$table->getPrimary()]=0;
                $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                $form = new FieldForm(null);
                session()->flash('flash_message',__lang('Field added'));
                return adminRedirect(['controller'=>'setting','action'=>'fields']);
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');

            }

        }

        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Field');
        $output['action']='addfield';
        $output['id']=null;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);


    }

    public function deletefield(Request $request,$id){
        $table = new RegistrationFieldTable();

        $table->deleteRecord($id);
        flashMessage(__lang('Record deleted'));
        return adminRedirect(array('controller'=>'setting','action'=>'fields'));

    }

    public function editfield(Request $request,$id){


        $output = array();
        $table = new RegistrationFieldTable();
        $form = new FieldForm(null);
        $filter = new FieldFilter();

        $row = $table->getRecord($id);
        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {



                $array = $form->getData();
                $array[$table->getPrimary()]=$id;
                $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                flashMessage(__lang('Changes Saved!'));

                return redirect()->route('admin.setting.fields');
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
            }

        }
        else {

            $data = getObjectProperties($row);
            $form->setData($data);


        }



        $output['form'] = $form;
        $output['id'] = $id;
        $output['pageTitle']= __lang('Edit Field');
        $output['row']= $row;
        $output['action']='editfield';

        $viewModel = viewModel('admin',__CLASS__,'addfield',$output);

        return $viewModel ;

    }

    public function roles(Request $request){
        $table = new RoleTable();

        $paginator = $table->getPaginatedRecords(true);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Roles'),
        ));
    }

    public function addrole(Request $request){
        $roleTable = new RoleTable();
        $permissionTable = new PermissionTable();
        $rolePermissionTable = new RolePermissionTable();
        $groups = new PermissionGroupTable();
        $form = new RoleForm(null,$this->getServiceLocator());
        $filter = new RoleFilter();
        $form->setInputFilter($filter);
        $output =[];
        if(request()->isMethod('post'))
        {
            $formData = request()->all();
            $form->setData($formData);

            if($form->isValid()){
                $data = $form->getData();

                $id = $roleTable->addRecord(['role'=>$data['role']]);

                $permissions = $permissionTable->getRecords();
                foreach($permissions as $row){
                    $key = 'permission_'.strtolower(str_replace(' ','_',$row->group)).'_'.$row->permission_id;
                    if(!empty($data[$key]))
                    {
                        $rolePermissionTable->addRecord([
                            'role_id'=>$id,
                            'permission_id'=>$data[$key]
                        ]);

                    }
                }

                session()->flash('flash_message',__lang('Role created'));
                return adminRedirect(['controller'=>'Setting','action'=>'roles']);
            }
            else{
                $output['flash_message'] = 'Save failed. Please enter a role name';
            }


        }

        $output['form'] = $form;
        $output['groups'] = $groups->getRecords();
        $output['action']='add';
        $output['id']=null;
        $output['pageTitle']=__lang('Add Role');
        return $output;
    }


    public function editrole(Request $request){

        $roleTable = new RoleTable();
        $permissionTable = new PermissionTable();
        $rolePermissionTable = new RolePermissionTable();
        $groups = new PermissionGroupTable();
        $form = new RoleForm(null,$this->getServiceLocator());
        $filter = new RoleFilter();
        $form->setInputFilter($filter);
        $output =[];
        $id = request()->get('id');
        if($id==1){
            session()->flash('flash_message',__lang('no-role-edit'));
            back();
        }
        if(request()->isMethod('post'))
        {

            $formData = request()->all();
            $form->setData($formData);

            if($form->isValid()){
                $data = $form->getData();

                $roleTable->update(['role'=>$data['role']],$id);
                $rolePermissionTable->deletePermissionsForRole($id);
                $permissions = $permissionTable->getRecords();
                foreach($permissions as $row){
                    $key = 'permission_'.strtolower(str_replace(' ','_',$row->group)).'_'.$row->permission_id;
                    if(!empty($data[$key]))
                    {
                        $rolePermissionTable->addRecord([
                            'role_id'=>$id,
                            'permission_id'=>$data[$key]
                        ]);

                    }
                }

                session()->flash('flash_message',__lang('Role edited'));
                return adminRedirect(['controller'=>'setting','action'=>'roles']);
            }
            else{
                $output['flash_message'] = __lang('role-save-error');
            }


        }
        else{

            $role = $roleTable->getRecord($id);
            $data = ['role'=>$role->role];
            $rowset = $rolePermissionTable->getPermissionsForRole($id);
            foreach($rowset as $row){

                $data['permission_'.strtolower(str_replace(' ','_',$row->group)).'_'.$row->permission_id]=$row->permission_id;

            }


            $form->setData($data);
        }

        $output['form'] = $form;
        $output['groups'] = $groups->getRecords();
        $output['action']='edit';
        $output['id']= $id;
        $output['pageTitle']=__lang('Edit Role');
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
        $viewModel->setTemplate('admin/setting/addrole.phtml');
        return $viewModel;
    }

    public function deleteaccount(Request $request){
        try{

            $id = request()->get('id');
            $table = new AccountsTable();
            $admin = $this->getAdmin();
            $row = $table->getRecord($id);
            if($row->email==$admin->email){
                session()->flash('flash_message',__lang('account-delete-error'));
                  return adminRedirect(['controller'=>'setting','action'=>'admins']);
            }

            $table->deleteRecord($id);

        }
        catch(\Exception $ex){
            $this->deleteError();
        }
        return adminRedirect(['controller'=>'setting','action'=>'admins']);
    }



    public function deleterole(Request $request){
        try{

            $id = request()->get('id');
            if($id < 4){
                session()->flash('flash_message',__lang('no-role-delete'));
                return back();
            }
            $roleTable = new RoleTable();
            $roleTable->deleteRecord($id);

        }
        catch(\Exception $ex){
            $this->deleteError();
        }
        return adminRedirect(['controller'=>'setting','action'=>'roles']);
    }

    public function admins(Request $request)
    {
        $table = new AccountsTable();

        $paginator = $table->getPaginatedRecords(true);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Administrators'),
        ));
    }

    public function addadmin(Request $request){

        if(!$this->canAddAdmin()){
            return adminRedirect(['controller'=>'setting','action'=>'admins']);
        }

        $accountsTable = new AccountsTable();
        $form = new AccountForm(null,$this->getServiceLocator());
        $filter = new AccountFilter();
        $form->setInputFilter($filter);
        $output = [];
        if(request()->isMethod('post'))
        {
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid())
            {
                $data = $form->getData();
                $password = $data['password'];
                $data['password'] = md5($data['password']);
                unset($data['confirm_password']);
                $email = $data['email'];
                $url = $this->getBaseUrl().'/admin';
                $message = __("new-admin-mail",['email'=>$email,'password',$password,'url'=>$url]);

                try{
                    $accountsTable->addRecord($data);
                    if(!empty($formData['senddetails'])){
                        $this->sendEmail($email,__lang('New Account'),$message);
                    }

                    session()->flash('flash_message',__lang('account-created'));
                    return adminRedirect(['controller'=>'setting','action'=>'admins']);
                }
                catch(\Exception $ex){
                    $output['flash_message'] = __lang('account-creation-error');
                }

            }
            else{

                $formMessages = $form->getMessages();

                $output['flash_message']=__lang('save-failed-msg');
                if ($formData['picture']) {
                    $output['display_image']= resizeImage($formData['picture'], 100, 100,$this->getBaseUrl());
                }
            }

        }
        else{
            $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
            $output['display_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());

        }

        $output['pageTitle'] = __lang('Add Administrator');
        $output['form'] = $form;
        $output['action'] = 'add';
        $output['id']=null;
        return $output;
    }

    public function getAuthService()
    {

        return $this->getServiceLocator()->get('AdminAuthService');
    }


    public function editadmin(Request $request){
        $accountsTable = new AccountsTable();
        $form = new AccountForm(null,$this->getServiceLocator());
        $filter = new AccountFilter();

        $filter->remove('password');
        $filter->remove('confirm_password');

        $form->setInputFilter($filter);
        $output = [];
        $id = request()->get('id');
        $row = $accountsTable->getRecord($id);

        $form->get('password')->setAttribute('required','');
        $form->get('confirm_password')->setAttribute('required','');

        $form->get('password')->setAttribute('placeholder',__lang('Optional'));
        $form->get('confirm_password')->setAttribute('placeholder',__lang('Optional'));

        if(request()->isMethod('post'))
        {
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid())
            {
                //get current admin
                $admin = $this->getAdmin();

                $data = $form->getData();

                if($id==$admin->account_id && $admin->email != $data['email']){
                    $this->getAuthService()->getStorage()->write(array(
                        'email'=>$data['email'],
                        'role'=>'admin'
                    ));
                }

                $password = $data['password'];
                if(!empty($password)){
                    $data['password'] = md5($data['password']);
                    unset($data['confirm_password']);
                    $email = $data['email'];

                    $message = __lang('admin-password-reset-msg',['password'=>$password]);
                }
                else{
                    unset($data['confirm_password'],$data['password']);
                }
                $accountsTable->update($data,$id);
                try{

                    $siteName = $this->getSetting('general_site_name',$this->getServiceLocator());
                    if(!empty($formData['senddetails']) && $row->email != $email){
                        $this->sendEmail($email,__lang('Account Modified'),$message);
                    }
                }
                catch(\Exception $ex){

                    $output['flash_message'] =  __lang('account-creation-error');
                }

                session()->flash('flash_message',__lang('Changes Saved!'));
                return adminRedirect(['controller'=>'setting','action'=>'admins']);


            }
            else{

                $formMessages = $form->getMessages();

                $output['flash_message']=__lang('save-failed-msg');
                //   print_r($form->e)
            }

        }
        else{
            $formData = getObjectProperties($row);
            unset($formData['password']);
            $form->setData($formData);
        }


        if ($row->picture && file_exists(DIR_MER_IMAGE . $row->picture) && is_file(DIR_MER_IMAGE . $row->picture)) {
            $output['display_image'] = resizeImage($row->picture, 100, 100,$this->getBaseUrl());
        } else {
            $output['display_image'] = resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }


        $output['no_image']= $this->getBaseUrl().'/'.resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());


        $output['pageTitle'] = __lang('Edit Administrator');
        $output['form'] = $form;
        $output['action'] = 'edit';
        $output['id']=$id;
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
        $viewModel->setTemplate('admin/setting/addadmin.phtml');
        return $viewModel;
    }



    public function migrate(Request $request){

        $output = [];
        $output['pageTitle'] = __lang('Update');
        if(request()->isMethod('post')){
            $file = '../update/app.zip';
            if(!file_exists($file)){

                session()->flash('flash_message',__lang('no-update-file'));
                return back();
            }

            $files= new Files();


            //create temp directory
            $tmpDir = '../update_tmp';
            if(!file_exists($tmpDir)){
                mkdir($tmpDir);
            }

            $userPath = $tmpDir.'/usermedia';
            $userPath2 = $tmpDir.'/uservideo';
            $userPath3 = $tmpDir.'/uploads';
            $configFile = $tmpDir.'/.env';
            $files->delete($userPath);
            $files->delete($userPath2);
            $files->delete($userPath3);
            $files->delete($configFile);


            //copy user media and config file to this directory

            copy('../.env',$configFile);


            $this->recurseCopy('usermedia',$userPath);
            $this->recurseCopy('uservideo',$userPath2);
            $this->recurseCopy('uploads',$userPath3);
//            $files->copyOrMove('public/usermedia',$userPath,'','move');
            //now move app.zip to root
            copy('../update/app.zip','../app.zip');

            //now unzip file
            $zip = new \ZipArchive();
            $res = $zip->open('../app.zip');
            if ($res === TRUE) {
                $path = base_path();
                $zip->extractTo($path);
                $zip->close();
            } else {
                $files->delete($userPath);
                $files->delete($userPath2);
                $files->delete($userPath3);
                $files->delete($configFile);

                @unlink('app.zip');
                session()->flash('flash_message',__lang('zip-error'));
                return back();
            }

            //copy usermedia and config file back
            $files->delete('../.env');
            copy($configFile,'../.env');

            $files->delete('usermedia');
            $files->delete('uservideo');
            $files->delete('uploads');
            $this->recurseCopy($userPath,'usermedia');
            $this->recurseCopy($userPath2,'uservideo');
            $this->recurseCopy($userPath3,'uploads');
            //   $files->copyOrMove($userPath,'public/usermedia','','move');

            //delete temp files
            //  $files->delete($userPath);
            $files->delete($configFile);
            $files->delete($file);
            $files->delete($tmpDir);
            $files->delete('../app.zip');


            //run migrations
            Artisan::call('migrate', array('--path' => 'database/migrations', '--force' => true));

            session()->flash('flash_message',__lang('update-complete'));
            return adminRedirect(['controller'=>'setting','action'=>'migrate']);

        }

        //check for file
        if(file_exists('../update/app.zip')){
            $output['file'] = true;
        }
        else{
            $output['file']=false;
        }




        return view('admin.setting.migrate',$output);
    }

    private function recurseCopy($src, $dst)
    {
        $dir = opendir($src);
        mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    public function testgrades(Request $request){

        $this->data['grades'] = TestGrade::orderBy('grade','asc')->get();
        $this->data['pageTitle'] = __lang('Grades');
        return view('admin.setting.testgrades',$this->data);
    }

    public function addtestgrade(Request $request){
        $form = $this->getTestGradeForm();

        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){
                TestGrade::create($form->getData());
                session()->flash('flash_message',__lang('Grade created'));
                return adminRedirect(['controller'=>'setting','action'=>'testgrades']);
            }
            else{
                $this->data['message'] = $this->getFormErrors($form);
            }
        }
        $this->data['form'] = $form;
        $this->data['pageTitle'] = __lang('Add Test Grade');

        return view('admin.setting.gradeform',$this->data);
    }

    public function edittestgrade(Request $request,$id){
        $form = $this->getTestGradeForm();
        $testGrade = TestGrade::find($id);
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){
                $testGrade->fill($form->getData());
                $testGrade->save();
                session()->flash('flash_message',__lang('Grade saved'));
                return adminRedirect(['controller'=>'setting','action'=>'testgrades']);
            }
            else{
                $this->data['message'] = $this->getFormErrors($form);
            }
        }
        else{
            $form->setData($testGrade->toArray());

        }

        $this->data['form'] = $form;
        $this->data['pageTitle'] = __lang('Edit Test Grade').': '.$testGrade->grade;


        return view('admin.setting.gradeform',$this->data);
    }

    public function deletetestgrade(Request $request,$id){

        $testGrade = TestGrade::find($id);
        $testGrade->delete();
        session()->flash('flash_message',__lang('Grade deleted'));
        return back();
    }


    public function currencies(Request $request){

        $currentCountry  = $this->getSetting('country_id');
        $currencies = Currency::orderBy('id','desc')->paginate(30);
        $pageTitle = __lang('Currencies');
        $countries = Country::orderBy('currency_name')->groupBy('currency_code')->get();

        return view('admin.setting.currencies',compact('currentCountry','currencies','pageTitle','countries'));
    }

    public function addcurrency(Request $request){
        if(request()->isMethod('post')){
            $country = $request->post('country');

            $countryModel= Country::find($country);
            if($countryModel && !Currency::where('country_id',$country)->first()){
                $currency = new Currency();
                $currency->country_id= $country;
                $currency->exchange_rate = $request->post('exchange_rate');
                $currency->save();
                session()->flash('flash_message',__lang('currency-added'));
            }
        }
        return back();
    }

    public function deletecurrency(Request $request,$id){

        $currency= Currency::find($id);
        if($currency){
            $currency->delete();
            session()->flash('flash_message',__lang('Currency removed'));
        }
        return back();
    }

    public function updatecurrency(Request $request,$id){

        if(request()->isMethod('post')){
            $rate = $request->post('rate');
            $currency = Currency::find($id);
            $currency->exchange_rate = $rate;
            $currency->save();
        }
        exit('done');
    }


    public function language(Request $request){
        $output=[];
        $select = new Select('language');
        $settingsTable = new SettingTable();
        $select->setAttribute('class','form-control');
        $options = include '../resources/lang/list.php';
        $select->setValueOptions($options);
        $select->setLabel(__lang('language'));

        if(request()->isMethod('post')){
            $language = $request->post('language');
            if(!empty($language)){
                $settingsTable->saveSetting('config_language',$language);
                flashMessage(__lang('changes-saved'));
                return redirect(selfURL());
            }

        }

        $lang = getSetting('config_language');
        if(empty($lang)){
            $lang = 'en';
        }

        $select->setValue($lang);
        $output['select'] = $select;
        $output['pageTitle']= __lang('language');
        return view('admin.setting.language',$output);
    }


    private function getTestGradeForm(){
        $form = new BaseForm();
        $form->createText('grade','Grade',true,null,null,'e.g. A');
        $form->createText('min','Minimum',true,'form-control number');
        $form->createText('max','Maximum',true,'form-control number');
        $form->setInputFilter($this->getTestGradeFilter());
        return $form;
    }

    private function getTestGradeFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'grade',
            'required'=>'true',
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'min',
            'required'=>'true',
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ],
                [
                    'name'=>'Digits'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'max',
            'required'=>'true',
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ],
                [
                    'name'=>'Digits'
                ]
            ]
        ]);
        return $filter;


    }



    public function frontend(){
        $status = setting('frontend_status');
        $options = [
            '1'=>__('default.enabled'),
            '0'=>  __('default.disabled')
        ];

        return view('admin.setting.frontend',compact('status','options'));
    }

    public function saveFrontend(Request $request){
        $this->validate($request,[
            'status'=>'required'
        ]);
        $status = $request->status;
        $setting= Setting::where('key','frontend_status')->first();
        if(!$setting){
            $setting = new Setting();
            $setting->key = 'frontend_status';
            $setting->type = 'text';
        }

        $setting->value = $status;
        $setting->save();
        flashMessage(__('default.changes-saved'));
        return back();

    }

    public function dashboard(){
        $color = setting('dashboard_color');

        return view('admin.setting.dashboard',compact('color'));
    }

    public function saveDashboard(Request $request){

        $color = $request->color;
        $setting= Setting::where('key','dashboard_color')->first();
        if(!$setting){
            $setting = new Setting();
            $setting->key = 'dashboard_color';
            $setting->type = 'text';
        }

        $setting->value = $color;
        $setting->save();
        flashMessage(__('default.changes-saved'));
        return back();

    }


}
