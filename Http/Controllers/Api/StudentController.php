<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Lib\HelperTrait;
use App\Student;
use App\StudentField;
use App\User;
use App\V2\Model\PasswordResetTable;
use App\V2\Model\RegistrationFieldTable;
use App\V2\Model\StudentFieldTable;
use App\V2\Model\StudentTable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Password;

class StudentController extends Controller
{
    use HelperTrait;
    protected $uploadDir;

    // constructor receives container instance
    public function __construct() {

        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $filePath = 'usermedia'.$user;
        $this->uploadDir = $filePath.'/student_uploads/'.date('Y_m');
    }


    public function create(Request $request) {
        if(setting('regis_enable_registration')!= 1){
            return jsonResponse(['status'=>false,'msg'=>__lang('registration-disabled')]);
        }
        $data = $request->all();

        $rules = [
            'first_name'=>'required',
            'last_name'=>'required',
            'mobile_number'=>'required',
            'email'=>'required|valid_email',
            'password'=>'required|max_len,100|min_len,6'
        ];

        foreach(StudentField::where('enabled',1)->orderBy('sort_order')->get() as $row){

            if($row->required==1 && $row->type != 'checkbox'){
                $rules['custom_'.$row->id] = 'required';
            }

        }
        //validate request
        $isValid = $this->validateGump($data,$rules);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        //check if email exists
        if(User::where('email',trim($data['email']))->count()>0){
            return jsonResponse(['status'=>false,'msg'=>__lang('email-exists')]);
        }

        try{


            // your code
            // to access items in the container... $this->container->get('');

            $data['password'] = Hash::make($data['password']);
            do{
//                    $token = md5(uniqid());
                $token = bin2hex(random_bytes(16));
            }while(!Student::where('api_token',$token));

            $data['api_token'] = $token;
            $data['last_seen'] = time();
            $data['registration_complete'] = 1;
            $data['student_created'] = time();
            $data['status'] = 1;
            $data['token_expires'] = Carbon::createFromTimestamp(time() + (86400 * 365))->toDateTimeString();


           $user = User::create([
              'name'=>$data['first_name'],
              'last_name'=>$data['last_name'],
              'email'=>$data['email'],
              'password'=>$data['password'],
              'enabled'=>1,
              'role_id'=>2,
              'last_seen'=>Carbon::now()->toDateTimeString()
           ]);

           $student = $user->student()->create([
               'mobile_number'=>$data['mobile_number'],
               'api_token'=>$data['api_token'],
               'token_expires'=>$data['token_expires']
           ]);

            $studentId = $student->id;
            $studentFieldTable = new StudentFieldTable();

            foreach(StudentField::where('enabled',1)->orderBy('sort_order')->get() as $row){
                if(!isset($data['custom_'.$row->id])){
                    continue;
                }
                $value = $data['custom_'.$row->id];
                if($row->type != 'file'){

                    $studentFieldTable->saveField($studentId,$row->id,$value);
                }
                elseif(!empty($value['name']) && file_exists($value['tmp_name'])){

                    $file = $value['name'];
                    $newPath = $this->uploadDir.'/'.time().$studentId.'_'.sanitize($file);
                    $this->makeUploadDir();
                    rename($value['tmp_name'],$newPath);
                    $studentFieldTable->saveField($studentId,$row->id,$newPath);

                }
                else{
                    $studentFieldTable->saveField($studentId,$row->id,'');
                }
            }


            return jsonResponse(['status'=>true,'token'=>$token,'first_name'=>$user->name,'last_name'=>$user->last_name,'id'=>$student->id,'user_id'=>$student->user_id,'picture'=>$student->user->picture]);
        }
        catch(\Exception $ex){
            return jsonResponse(['status'=>false,'msg'=>$ex->getMessage()]);
        }
    }


    public function getToken(Request $request,$id){
        $token = $id;
        $student = Student::where('api_token',$token)->where('token_expires','>',Carbon::now()->toDateTimeString())->first();
        if($student){
            $status = true;
        }
        else{
            $status = false;
        }
        return jsonResponse(['status'=>$status]);
    }


    public function getProfile(Request $request,$id){

        $student = $this->getApiStudent();
        if($student->id != $id){
            return jsonResponse([
                'status'=>false,
                'msg'=>'You do not have access to the profile'
            ]);
        }

        $student= $this->getApiStudent();


        $data = $student->toArray();

        $data += $student->user->toArray();
        $data['first_name'] = $data['name'];

        $studentFieldTable = new StudentFieldTable();
        $records= $studentFieldTable->getStudentRecords($this->getApiStudentId());

        foreach($records as $record){
            $data['custom_'.$record->student_field_id] = $record->value;
        }

        return jsonResponse([
            'status'=>true,
            'data'=>$data
        ]);


    }

    public function updateProfile(Request $request,$id){

        $params = $request->all();

        $rules = [
            'first_name'=>'required',
            'last_name'=>'required',
            'mobile_number'=>'required',
            'email'=>'required|valid_email',
        ];

        foreach(StudentField::where('enabled',1)->orderBy('sort_order')->get() as $row){

            if($row->required==1 && $row->type != 'checkbox'){
                $rules['custom_'.$row->id] = 'required';
            }

        }

        $this->validateParams($params,$rules);

        $row = $this->getApiStudent();
        $studentsTable = new StudentTable();
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldTable = new StudentFieldTable();

        $data = removeTags($params);



        $student = Student::find($id);
        $user = $student->user;
        if(!empty($data['picture']['name'])){
            @unlink($row->picture);

            $file = $data['picture']['name'];
            $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($file);
            $this->makeUploadDir();
            rename($data['picture']['tmp_name'],$newPath);

            $user->picture = $newPath;
            $user->save();
        }


        $user->fill([
           'name'=>$data['first_name'],
            'last_name'=>$data['last_name'],
            'email'=>$data['email'],
           // 'enabled'=>$data['status'],
        ]);
        $user->save();

        $student->fill([
            'mobile_number'=>$data['mobile_number']
        ]);
        $student->save();

    //    $array[$studentsTable->getPrimary()]=$id;
    //    $studentsTable->saveRecord($array);

        $fields= $registrationFieldsTable->getActiveFields();
        foreach($fields as $row){


            $fieldRow = $studentFieldTable->getStudentFieldRecord($id,$row->id);
            $value = $data['custom_'.$row->id];
            if($row->type != 'file'){

                $studentFieldTable->saveField($id,$row->id,$value);
            }
            elseif(!empty($value['name'])){

                @unlink($fieldRow->value);

                $file = $value['name'];
                $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($file);
                $this->makeUploadDir();
                rename($value['tmp_name'],$newPath);
                $studentFieldTable->saveField($id,$row->id,$newPath);

            }
        }

        $student= $this->getApiStudent();


        $data = $student->toArray();
        $data['first_name'] = $student->user->name;
        $data['last_name'] = $student->user->last_name;
        $data['token'] = $student->api_token;
        $data['picture'] = $student->user->picture;

    //    $data = ['status'=>true,'token'=>$token,'first_name'=>$user->name,'last_name'=>$user->last_name,'id'=>$student->id];

        $studentFieldTable = new StudentFieldTable();
        $records= $studentFieldTable->getStudentRecords($this->getApiStudentId());

        foreach($records as $record){
            $data['custom_'.$record->student_field_id] = $record->value;
        }

        return jsonResponse([
            'status'=>true,
            'data'=>$data
        ]);


    }


    public function saveProfilePhotoOld(Request $request){

        $params = $request->all();

        $this->validateParams($params,[
            'picture'=>'required'
        ]);

        $data = $params['picture'];
        try{

            $data = base64_decode($data);

            $im = imagecreatefromstring($data);

            if ($im == false) {
                return jsonResponse(['status'=>false,'msg'=>'Invalid image supplied']);
            }


            $row = $this->getApiStudent();

            //delete current pic
            @unlink('public/'.$row->picture);
            $id = $row->student_id;

            $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($row->first_name).'.jpg';

            $this->makeUploadDir();
            //  touch($newPath);
            imagejpeg($im,'public/'.$newPath);
            crop_img('public/'.$newPath);
            /*
                        // Create new imagick object
                        $file ='public/'.$newPath;
                        $im = new \Imagick($file);

            // Optimize the image layers
                        $im->optimizeImageLayers();

            // Compression and quality
                        $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        $im->setImageCompressionQuality(25);

            // Write the image back
                        $im->writeImages($file, true);*/

            chmod('public/'.$newPath,0777);


            $row->picture = $newPath;
            $row->save();
            return jsonResponse(['status'=>true]);

        }catch(\Exception $ex){
            return jsonResponse(['status'=>false,'msg'=>$ex->getMessage().'<br/>'.$ex->getTraceAsString()]);
        }


    }


    public function saveProfilePhoto(Request $request){

        $params['picture'] = $_FILES['picture']['tmp_name'];

        $this->validateParams($params,[
            'picture'=>'required'
        ]);

        $data = $params['picture'];
        try{

            //   $data = base64_decode($data);
            $im=false;
            //$im = imagecreatefromstring($data);
            $file= $_FILES['picture']['tmp_name'];
            $ext =getExtensionForMime($file);

            if($ext=='jpg'){
                $im = imagecreatefromjpeg($file);
            }
            elseif($ext=='png'){
                $im = imagecreatefrompng($file);
            }

            if ($im == false) {
                return jsonResponse(['status'=>false,'msg'=>__lang('invalid-image')]);
            }


            $row = $this->getApiStudent()->user;

            //delete current pic
            @unlink($row->picture);
            $id = $this->getApiStudentId();

            $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($row->first_name).'.jpg';

            $this->makeUploadDir();
            //  touch($newPath);
            imagejpeg($im,$newPath);
            crop_img($newPath);


            chmod($newPath,0777);


            $row->picture = $newPath;
            $row->save();
            return jsonResponse(['status'=>true]);

        }catch(\Exception $ex){
            return jsonResponse(['status'=>false,'msg'=>$ex->getMessage().'<br/>'.$ex->getTraceAsString()]);
        }


    }




    public function removeProfilePhoto(){

        $row = $this->getApiStudent()->user;

        //delete current pic
        @unlink($row->picture);
        $row->picture = '';
        $row->save();
        return jsonResponse(['status'=>true]);

    }

    public function changePassword(Request $request){

        $data = $request->all();

        $this->validateParams($data,[
            'password'=>'required|max_len,100|min_len,6'
        ]);

        $data['password'] = Hash::make($data['password']);

        $student = $this->getApiStudent()->user;
        $student->password = $data['password'];
        $student->save();

        return jsonResponse([
            'status'=>true,
            'msg'=>__lang('password-changed!')
        ]);

    }

    public function resetPassword(Request $request){
        $data = $request->all();

        $this->validateParams($data,[
            'email'=>'required'
        ]);

        $email = $data['email'];
        $user = User::where('email',$email)->first();

        if(!$user){
            return jsonResponse([
                'status'=>false,
                'msg'=>__lang('no-email-assoc',['email'=>$email])
            ]);
        }

        $credentials = ['email' => $email];
        $response = __(Password::sendResetLink($credentials));

        return jsonResponse([
            'status'=> true,
            'msg'=>$response
        ]);

    }

    private function makeUploadDir(){
        $path = $this->uploadDir;
        if(!file_exists($path)){
            mkdir($path,0755,true);
            chmod($path,0755);
        }
    }

    public function deleteAccount(){
        $student = $this->getApiStudent();
        try{
            $picture = $student->user->picture;
            if(!empty($picture) && file_exists($picture)){
                @unlink($student->user->picture);
            }

            $student->user->delete();
            //    $table->deleteRecord($id);
            return jsonResponse([
                'status'=> true,
                'msg'=>__lang('Record deleted')
            ]);
        }
        catch(\Exception $ex){
            return jsonResponse([
                'status'=> false,
                'msg'=>__lang('locked-message')
            ]);

        }
    }

}
