<?php

namespace App\Http\Controllers\Admin;

use App\Course;
use App\Currency;
use App\Http\Controllers\Controller;
use App\Invoice;
use App\Lesson;
use App\Lib\BaseForm;
use App\Lib\Cart;
use App\Lib\HelperTrait;
use App\SmsGateway;
use App\Student;
use App\StudentCourse;
use App\User;
use App\V2\Form\SessionFilter;
use App\V2\Form\SessionForm;
use App\V2\Form\StudentFilter;
use App\V2\Form\StudentForm;
use App\V2\Model\AccountsTable;
use App\V2\Model\AttendanceTable;
use App\V2\Model\LessonGroupTable;
use App\V2\Model\LessonTable;
use App\V2\Model\LessonToLessonGroupTable;
use App\V2\Model\PaymentTable;
use App\V2\Model\RegistrationFieldTable;
use App\V2\Model\SessionCategoryTable;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionLessonAccountTable;
use App\V2\Model\SessionLessonTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SessionToSessionCategoryTable;
use App\V2\Model\StudentFieldTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\File\IsImage;

class StudentController extends Controller
{
    use HelperTrait;


    protected $uploadDir;

    public function __construct(){
        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $this->uploadDir = 'usermedia'.$user.'/student_uploads/'.date('Y_m');

    }

    private function makeUploadDir(){
        $path = $this->uploadDir;
        if(!file_exists($path)){
            mkdir($path,0755,true);
        }
    }

    /**
     * The default action - show the home page
     */
    public function index(Request $request)
    {
        // TODO Auto-generated ParentsController::index(Request $request) default action

        $table = new StudentTable();
        $filter = $request->get('filter');


        if (empty($filter)) {
            $filter=null;
        }


        $text = new Text('filter');
        $text->setAttribute('class','form-control');
        $text->setAttribute('placeholder',__lang('filter-name-email'));
        $text->setValue($filter);

        $attendanceTable = new AttendanceTable();

        $paginator = $table->getStudents(true,$filter);
        $total = User::where('role_id',2)->whereHas('student')->count();

        $paginator->setCurrentPageNumber((int)$request->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Students').': '.$total,
            'filter'=>$filter,
            'text'=>$text,
            'attendanceTable'=>$attendanceTable,
            'studentSessionTable'=> new StudentSessionTable()

        ));



    }


    public function active(Request $request)
    {
        // TODO Auto-generated ParentsController::index(Request $request) default action

        $table = new StudentSessionTable();

        $attendanceTable = new AttendanceTable();

        $paginator = $table->getActiveStudents();

        $paginator->setCurrentPageNumber((int)$request->get('page', 1));
        $paginator->setItemCountPerPage(30);
        $pageTitle = __lang('Active Students').': '.$table->getTotalActiveStudents();
        if (saas() && defined('STUDENT_LIMIT')){
            $pageTitle.= '/'.STUDENT_LIMIT;
        }
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>$pageTitle,
            'attendanceTable'=>$attendanceTable,
            'studentSessionTable'=>$table
        ));



    }


    public function add(Request $request)
    {

        $output = array();
        $studentsTable = new StudentTable();
        $form = new StudentForm(null,$this->getServiceLocator());
        $filter = new StudentFilter($this->getServiceLocator());
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldTable = new StudentFieldTable();
        $output['fields'] = $registrationFieldsTable->getAllFields();



        if ($request->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = $request->all();


            //	$form->setData($data);
            $form->setData(array_merge_recursive(
                $request->all(),
                $_FILES
            ));
            if ($form->isValid()  && !$studentsTable->emailExists($data['email']) ) {

                $data = $form->getData();



                $array = [
                    'first_name'=>$data['name'],
                    'last_name'=>$data['last_name'],
                    'mobile_number'=>$data['mobile_number'],
                    'email'=>$data['email'],
                    'status'=>$data['status'],
                ];




                $array[$studentsTable->getPrimary()]=0;
                //	$array['password'] = md5('password');
                $studentPassword = substr(md5($data['email'].time().rand(0,1000000)),0,6);
                $array['password']= md5($studentPassword);


                //Create user
                $user = User::create([
                    'name'=>$data['name'],
                    'last_name'=>$data['last_name'],
                    'password'=>Hash::make($studentPassword),
                    'role_id'=>2,
                    'enabled'=>$data['status'],
                    'email'=>$data['email']
                ]);
                $user->student()->create([
                    'mobile_number'=>$data['mobile_number']
                ]);

                $studentId = $user->student->id;


                //store dp
                if(!empty($data['picture']['name'])){


                    $file = $data['picture']['name'];
                    $newPath = $this->uploadDir.'/'.time().$studentId.'_'.sanitize($file);
                    $this->makeUploadDir();
                    rename($data['picture']['tmp_name'],$newPath);

                    //$parentsTable->update(['picture'=>$newPath],$studentId);
                    $user->picture = $newPath;
                    $user->save();
                }


                $fields= $registrationFieldsTable->getAllFields();
                foreach($fields as $row){
                    $value = $data['custom_'.$row->id];
                    if($row->type != 'file'){

                        $studentFieldTable->saveField($studentId,$row->id,$value);
                    }
                    elseif(!empty($value['name'])){

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


                //    flashMessage(__lang('Changes Saved!'));
                flashMessage(__lang('Record Added!'));
                $form = new StudentForm(null,$this->getServiceLocator());

                //send email

                $title = __lang('New Account Details');
                $senderName = setting('general_site_name');

                $firstName = $array['first_name'];
                $recipientEmail = $array['email'];
                $siteUrl = $this->getBaseUrl();
                $message = __lang('new-account-mail',['firstName'=>$firstName,'studentPassword'=>$studentPassword,'senderName'=>$senderName,'siteUrl'=>$siteUrl,'recipientEmail'=>$recipientEmail]);

                $this->sendEmail($recipientEmail,$title,$message);

                return redirect()->route('admin.student.index');


            }
            elseif($studentsTable->emailExists($data['email'])){
                $output['flash_message'] = __lang('save-fail-email-is-assoc',['email'=>$data['email']]);
            }
            else{
                $output['flash_message'] = $this->getFormErrors($form);


            }

        }

        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Student');
        $output['action'] = 'add';
        $output['id']=0;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);

    }


    public function view(Request $request,$id)
    {
        $studentTable = new StudentTable();
        $attendanceTable = new AttendanceTable();
        $registrationFields = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();
        $row = $studentTable->getStudent($id);

        $attendance = $attendanceTable->getStudentRecords(false,$id);



        $customRecords = $studentFieldsTable->getStudentRecordsAll($id);

        $validator = new IsImage();

        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'row'=>$row,
            'attendance'=>$attendance,
            'custom'=>$customRecords,
            'pageTitle'=>__lang('Student Details').': '.$row->last_name.' '.$row->name,
            'validator'=>$validator,
            'attendanceTable'=>$attendanceTable,
            'id'=>$id
        ));
    }

    public function edit(Request $request,$id)
    {
        $student = Student::findOrFail($id);
        $output = array();
        $studentsTable = new StudentTable();
        $form = new StudentForm(null,$this->getServiceLocator());
        $form->setServiceLocator($this->getServiceLocator());
        $filter = new StudentFilter($this->getServiceLocator());
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldTable = new StudentFieldTable();
        $output['fields'] = $registrationFieldsTable->getAllFields();


        $registrationFieldsTable = new RegistrationFieldTable();
        $output['fields'] = $registrationFieldsTable->getAllFields();

        $row = $studentsTable->getRecord($id);
        if ($request->isMethod('post')) {

            $form->setInputFilter($filter);

            $form->setData(array_merge_recursive(
                $request->all(),
                $_FILES
            ));

            //check if email is valid
            $validEmail = true;
            $postEmail = $request->post('email');
            if($row->email != $postEmail && $studentsTable->emailExists($postEmail)){
                $validEmail = false;
            }


            if ($form->isValid() && $validEmail) {

                $data = $form->getData();

                $array = [
                    'name'=>$data['name'],
                    'last_name'=>$data['last_name'],
                    'mobile_number'=>$data['mobile_number'],
                    'email'=>$data['email'],
                    'enabled'=>$data['status'],
                ];


                //store dp
                if(!empty($data['picture']['name'])){
                    @unlink($row->picture);

                    $file = $data['picture']['name'];
                    $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($file);
                    $this->makeUploadDir();
                    rename($data['picture']['tmp_name'],$newPath);
                    $array['picture'] = $newPath;

                }

                $student->user->fill($array);
                $student->user->save();
                $student->fill($array);
                $student->save();



                $fields= $registrationFieldsTable->getAllFields();
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
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Changes Saved!');

            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
                if(!$validEmail){
                    $output['flash_message'] = __lang('save-failed').': '.__lang('email-exists');
                }
            }

        }
        else {
            $user  = User::find($row->user_id);
            $data = get_object_vars($row);
            $userData = $user->toArray();
            $userData['mobile_number'] = $user->student->mobile_number;
           // $userData['first_name'] = $userData['name'];
            $data = array_merge($data,$userData);
            $customData = [];
            $customField = $studentFieldTable->getStudentRecords($id);
            foreach($customField as $row){
                $customData['custom_'.$row->student_field_id]=$row->value;
            }

            $newData = array_merge($data,$customData);

            $form->setData($newData);

        }

        $output['form'] = $form;
        $output['id'] = $id;
        $output['pageTitle']= __lang('Edit Student');
        $output['row']=$student;
        $output['action'] = 'edit';
        $output['id']=$id;

        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
        //   $viewModel->setTemplate('admin/student/add.phtml');
        return $viewModel;
    }

    public function removeimage(Request $request,$id){
        $student = Student::findOrFail($id);
        if (file_exists($student->user->picture)){
            @unlink($student->user->picture);
        }
        $student->user->picture = null;
        $student->user->save();
        flashMessage(__lang('display-picture-removed'));
        return back();
    }


    public function changepassword(Request $request,$id){
        $student = Student::find($id);
        $form = $this->getPasswordResetForm();
        if($request->isMethod('post')){
            $formData = $request->all();
            $form->setData($formData);
            if($form->isValid())
            {
                $data = $form->getData();

/*                $studentTable = new StudentTable();
                $studentTable->update(['password'=>md5($data['password'])],$id);*/

                $student->user->password = Hash::make($data['password']);
                $student->user->save();

                flashMessage(__lang('Password changed'));
                if(!empty($data['notify'])){
                    $subject = __lang('password-changed-subj');
                    $message = __lang('password-changed-msg').$data['password'];
                    $this->notifyStudent($id,$subject,$message);
                }

            }
            else{
                flashMessage($this->getFormErrors($form));
            }


        }

        return back();
    }

    private function getPasswordResetForm(){
        $form = new BaseForm();
        $form->createPassword('password','Password',true);
        $form->createPassword('confirm_password','Confirm Password',true);
        $form->createCheckbox('notify','Send new password to student?',1);
        $form->setInputFilter($this->getPasswordResetFilter());
        return $form;
    }

    private function getPasswordResetFilter(){
        $filter = new InputFilter();
        $filter->add(array(
            'name'=>'password',
            'required'=>true,
            'validators'=>array(
                array(
                    'name'=>'NotEmpty'
                )
            )
        ));


        $filter->add(array(
            'name'=>'confirm_password',
            'required'=>true,
            'validators'=>array(
                array(
                    'name'=>'NotEmpty'
                ),
                array(
                    'name'    => 'Identical',
                    'options' => array(
                        'token' => 'password',
                    )
                )
            )
        ));
        $filter->add(array(
            'name'=>'notify',
            'required'=>false,
        ));
        return $filter;

    }

    public function delete(Request $request,$id)
    {
        $student = Student::findOrFail($id);

     //   $table = new StudentTable();
        try{
            $picture = $student->user->picture;
            if(!empty($picture) && file_exists($picture)){
                @unlink($student->user->picture);
            }

            $student->user->delete();
        //    $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }



        return adminRedirect(array('controller'=>'student','action'=>'index'));
    }

    public function sessions(Request $request){
        $table = new SessionTable();
        $attendanceTable = new AttendanceTable();
        $studentSessionTable = new StudentSessionTable();

        $filter = $request->get('filter');

        if (empty($filter)) {
            $filter=null;
        }

        $group = $request->get('group', null);
        if (empty($group)) {
            $group=null;
        }

        $sort = $request->get('sort', null);
        if (empty($sort)) {
            $sort=null;
        }

        $type = $request->get('type', null);
        if (empty($type)) {
            $type=null;
        }


        $payment = $request->get('payment', null);
        if (!is_numeric($payment)) {
            $payment=null;
        }

        $text = new Text('filter');
        $text->setAttribute('class','form-control');
        $text->setAttribute('placeholder',__lang('Search'));
        $text->setValue($filter);

        $select = new Select('group');
        $select->setAttribute('class','form-control select2');
        $select->setEmptyOption('--'.__lang('Select Category').'--');

        $sortSelect = new Select('sort');
        $sortSelect->setAttribute('class','form-control');
        $sortSelect->setValueOptions([
            'recent'=>__lang('Recently Added'),
            'asc'=>__lang('Alphabetical (Ascending)'),
            'desc'=>__lang('Alphabetical (Descending)'),
            'date'=>__lang('Start Date'),
            'priceAsc' =>__lang('Price (Lowest to Highest)'),
            'priceDesc' => __lang('Price (Highest to Lowest)')
        ]);

        $sortSelect->setEmptyOption('--'.__lang('Sort').'--');
        $sortSelect->setValue($sort);

        $typeSelect = new Select('type');
        $typeSelect->setAttribute('class','form-control');
        $typeSelect->setEmptyOption(__lang('course-type'));
        $typeSelect->setValueOptions([
            'c'=>__lang('Online Course'),
            's'=>__lang('Training Session'),
            'b'=>__lang('training-online'),
        ]);
        $typeSelect->setEmptyOption('--'.__lang('Type').'--');
        $typeSelect->setValue($type);


        $paymentSelect = new Select('payment');
        $paymentSelect->setAttribute('class','form-control');
        $paymentSelect->setValueOptions([
            '1'=>__lang('Yes'),
            '0'=>__lang('No'),
        ]);
        $paymentSelect->setEmptyOption('--'.__lang('Payment Required').'--');
        $paymentSelect->setValue($payment);

        $groupTable = new SessionCategoryTable();
        $groupRowset = $groupTable->getLimitedRecords(1000);
        $options =[];

        foreach($groupRowset as $row){
            $options[$row->id] = $row->name;
        }
        $select->setValueOptions($options);
        $select->setValue($group);

        $paginator = $table->getPaginatedRecords(true,null,null,$filter,$group,$sort,$type,false,$payment);
        $totalRecords = $table->getTotalRecords(true,null,null,$filter,$group,$sort,$type,false,$payment);

        $paginator->setCurrentPageNumber((int)$request->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return view('admin.student.sessions',array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('courses-and-sessions').'  ('.$totalRecords.')',
            'attendanceTable'=>$attendanceTable,
            'studentSessionTable'=>$studentSessionTable,
            'filter'=>$filter,
            'group'=>$group,
            'text'=>$text,
            'select'=>$select,
            'sortSelect'=>$sortSelect,
            'sort'=>$sort,
            'typeSelect'=>$typeSelect,
            'type'=>$type,
            'paymentSelect'=>$paymentSelect,
            'payment'=>$payment
        ));

    }

    public function addsession(Request $request,$type){
        $table = new SessionTable();
        $output = array();
        $output['id']=0;

        $filter = new SessionFilter();
        $sessionLessonTable = new SessionLessonTable();
        $lessonTable = new LessonTable();
        $lessonGroupTable = new LessonGroupTable();
        $sessionInstructorTable = new SessionInstructorTable();
        $dbType = $type;
        if(empty($dbType)){
            $dbType = 's';
        }
        elseif($dbType=='b'){
            $dbType=null;
        }
        $form = new SessionForm(null,$this->getServiceLocator(),$dbType);



        if($request->isMethod('post')){
            $formData = $request->all();
            $form->setInputFilter($filter);
            $form->setData($formData);
            if($form->isValid()){
                $data = $form->getData();



                if(empty($data['session_date'])){
                    $date = Carbon::now()->toDateString();
                }
                else{
                    $date = Carbon::parse($data['session_date'])->toDateString();
                }

                if(empty($data['session_end_date'])){
                    $endDate = Carbon::now()->toDateString();
                }
                else{
                    $endDate = Carbon::parse($data['session_end_date'])->toDateString();
                }

                if(empty($data['enrollment_closes'])){
                    $closesOn = $endDate;
                }
                else{
                    $closesOn = Carbon::parse($data['enrollment_closes'])->toDateString();
                }


                $dbData = array(
                    'name'=>$data['session_name'],
                    'start_date'=>$date,
                    'end_date'=>$endDate,
                    'enabled'=>$data['session_status'],
                    'payment_required'=>$data['payment_required'],
                    'fee'=>$data['amount'],
                    'enrollment_closes'=>$closesOn,
                    'venue'=>$data['venue'],
                    'description'=>$data['description'],
                    'type'=>$type,
                    'picture'=>$data['picture'],
                    'short_description'=>$data['short_description'],
                    'enable_forum'=>$data['enable_forum'],
                    'enable_discussion'=>$data['enable_discussion'],
                    'capacity'=>$data['capacity'],
                    'enforce_capacity'=>$data['enforce_capacity'],
                    'admin_id'=>$this->getAdmin()->admin->id
                );



                $courseRow = Course::create($dbData);
                $sessionId= $courseRow->id;
                if(isset($formData['session_instructor_id'])){

                    $courseRow->admins()->attach($formData['session_instructor_id']);

                }



                /*     session()->flash('flash_message','Session added');
                     return adminRedirect(['controller'=>'student','action'=>'sessions']);*/

                session()->flash('flash_message',__lang('session-added-post-classes'));
                return adminRedirect(['controller'=>'session','action'=>'sessionclasses','id'=>$sessionId]);

            }
            else{
                $output['flash_message'] = $this->getFormErrors($form);
                if(isset($formData['session_instructor_id'])){
                    foreach($formData['session_instructor_id'] as $value){
                        $groupId = $value[0];
                        $formData['session_instructor_id[]'][] = $groupId;
                    }
                }


                $form->setData($formData);

                if ($formData['picture']) {
                    $output['display_image']= resizeImage($formData['picture'], 100, 100,$this->getBaseUrl());
                }
                else{
                    $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
                    $output['display_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
                }
            }
        }
        else{
            $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
            $output['display_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }
        $output['form'] = $form;
        $output['action'] = 'add';
        $output['type'] = $type;
        $output['pageTitle'] = ($type=='s')? __lang('add-session-title'):__lang('add-session-online-title');
        $output['lessonGroupTable'] = new LessonToLessonGroupTable();
        return view('admin.student.addsession',$output);
    }

    public function editsession(Request $request,$id){
        $table = new SessionTable();
        $output = array();
        $output['pageTitle'] = __lang('Edit Session');
        $output['lessonGroupTable'] = new LessonToLessonGroupTable();
        $sessionRow = $table->getRecord($id);
        $type = $sessionRow->type;
        $dbType = 's';
        if($sessionRow->type=='b'){
            $dbType=null;
        }

        $form = new SessionForm(null,$this->getServiceLocator(),$dbType);



        $filter = new SessionFilter();
        $sessionLessonTable = new SessionLessonTable();
        $sessionInstructorTable = new SessionInstructorTable();

        if($request->isMethod('post')){
            $formData = $request->all();
            $form->setInputFilter($filter);
            $form->setData($formData);

            if($form->isValid()){

                $data = $form->getData();
                if(empty($data['session_date'])){
                    $date = Carbon::now()->toDateString();
                }
                else{
                    $date = Carbon::parse($data['session_date'])->toDateString();
                }

                if(empty($data['session_end_date'])){
                    $endDate = Carbon::now()->toDateString();
                }
                else{
                    $endDate = Carbon::parse($data['session_end_date'])->toDateString();
                }

                if(empty($data['enrollment_closes'])){
                    $closesOn = $endDate;
                }
                else{
                    $closesOn = Carbon::parse($data['enrollment_closes'])->toDateString();
                }


                $dbData = array(
                    'name'=>$data['session_name'],
                    'start_date'=>$date,
                    'end_date'=>$endDate,
                    'enabled'=>$data['session_status'],
                    'payment_required'=>$data['payment_required'],
                    'fee'=>$data['amount'],
                    'enrollment_closes'=>$closesOn,
                    'venue'=>$data['venue'],
                    'description'=>$data['description'],
                    'type'=>$type,
                    'picture'=>$data['picture'],
                    'short_description'=>$data['short_description'],
                    'enable_forum'=>$data['enable_forum'],
                    'enable_discussion'=>$data['enable_discussion'],
                    'capacity'=>$data['capacity'],
                    'enforce_capacity'=>$data['enforce_capacity'],
                );

                $course = Course::find($id);
                $course->fill($dbData);
                $course->save();
               // $table->update($dbData,$id);

                if(isset($formData['session_instructor_id'])){

                    $course->admins()->sync($formData['session_instructor_id']);

                }




                session()->flash('flash_message',__lang('Changes Saved!'));
                return adminRedirect(array('controller'=>'student','action'=>'sessions'));
            }
            else{

                $output['flash_message'] = $this->getFormErrors($form);
                foreach($formData['session_instructor_id'] as $value){
                    $groupId = $value[0];
                    $formData['session_instructor_id[]'][] = $groupId;
                }
                $form->setData($formData);


            }
        }
        else{
            $row = $table->getRecord($id);
            $data = getObjectProperties($row);
            $data['session_date'] = Carbon::parse($row->start_date)->toDateString() ;
            $data['session_end_date'] = Carbon::parse($row->end_date)->toDateString();
            $data['enrollment_closes'] = Carbon::parse($row->enrollment_closes)->toDateString();
            $data['session_name'] = $row->name;
            $data['amount']=$row->fee;
            $data['session_status']=$row->enabled;

            $rowset = $sessionInstructorTable->getSessionRecords($id);
            foreach($rowset as $groupRow){
                $data['session_instructor_id[]'][] = $groupRow->admin_id;
            }


            //get session lessons
/*            $rowset = $sessionLessonTable->getSessionRecords($id);
            foreach($rowset as $row){
                $data['lesson_'.$row->lesson_id]=$row->lesson_id;
                if(!empty($row->lesson_date)){
                    $data['lesson_date_'.$row->lesson_id]= date('Y-m-d',$row->lesson_date);
                }

                $data['lesson_venue_'.$row->lesson_id] = $row->lesson_venue;
                $data['lesson_start_'.$row->lesson_id] = $row->lesson_start;
                $data['lesson_end_'.$row->lesson_id] = $row->lesson_end;
                if(!empty($row->sort_order)){
                    $data['sort_order_'.$row->lesson_id]= $row->sort_order;
                }
            }*/

            $form->setData($data);
        }

        $row = $table->getRecord($id);

        if ($row->picture && file_exists($row->picture) && is_file($row->picture)) {
            $output['display_image'] = resizeImage($row->picture, 100, 100,$this->getBaseUrl());
        } else {

            $output['display_image'] = resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }


        $output['no_image']= $this->getBaseUrl().'/'.resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());

        $output['form'] = $form;
        $output['action'] = 'edit';
        $output['id'] = $id;
        $output['type'] = $type;
        return view('admin.student.addsession',$output);
    }


    public function duplicatesession(Request $request,$id){


        //get tables
        $sessionTable = new SessionTable();
        $sessionLessonTable = new SessionLessonTable();
        $sessionLessonAccountTable = new SessionLessonAccountTable();
        $sessionInstructorTable = new SessionInstructorTable();
        $sessionToSessionCategoryTable = new SessionToSessionCategoryTable();

        //now get session records
        $sessionRow = $sessionTable->getRecord($id);
        $sessionLessonRowset = $sessionLessonTable->getSessionRecords($id);
        $sessionLessonAccountRowset = $sessionLessonAccountTable->getSessionRecords($id);
        $sessionInstructorRowset = $sessionInstructorTable->getSessionRecords($id);
        $sessionToSessionCategoryRowset = $sessionToSessionCategoryTable->getSessionRecords($id);

        //create row
        $sessionArray= getObjectProperties($sessionRow);
        unset($sessionArray['id']);
        $newId = $sessionTable->addRecord($sessionArray);

        //now get lessons
        foreach($sessionLessonRowset as $row){
            $data = getObjectProperties($row);
            unset($data['name'],$data['id'],$data['type'],$data['picture'],$data['description'],$data['test_required'],$data['test_id'],$data['introduction'],$data['enforce_lecture_order']);
            $data['course_id']=$newId;
            $sessionLessonTable->addRecord($data);
        }

        //get instructors
        foreach($sessionLessonAccountRowset as $row){
            $data = getObjectProperties($row);
            unset($data['id']);
            $data['course_id']= $newId;
            $sessionLessonAccountTable->addRecord($data);
        }

        foreach($sessionInstructorRowset as $row){
            $data = getObjectProperties($row);
            unset($data['id']);
            $data['course_id']= $newId;
            $newData = [
                'course_id'=>$data['course_id'],
                'admin_id'=>$data['admin_id']
            ];
            if(!empty($data['admin_id'])){
                $sessionInstructorTable->addRecord($newData);
            }


        }

        foreach($sessionToSessionCategoryRowset as $row){
            $data = getObjectProperties($row);
            unset($data['id'],$data['name']);
            $data['course_id']= $newId;
            $sessionToSessionCategoryTable->addRecord($data);
        }

        session()->flash('flash_message',__lang('record-duplicated'));
        return adminRedirect(array('controller'=>'student','action'=>'sessions'));


    }


    private function lessonSelected($data){
        $lessonTable = new LessonTable();
        $rowset = $lessonTable->getRecords();
        $valid=false;
        foreach($rowset as $row){
            if(!empty($data['lesson_'.$row->lesson_id])){
                $valid = true;
            }
        }

        return $valid;
    }

    public function createclass(Request $request)
    {
        $output = array();
        $lessonTable = new LessonTable();

        $form = new LessonForm(null,$this->getServiceLocator());
        $filter = new LessonFilter();
        $output['type'] = $request->get('type');

        if ($request->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = $request->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();
                $array[$lessonTable->getPrimary()]=0;
                unset($array['lesson_group_id[]']);
                $id = $lessonTable->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                $form = new LessonForm(null,$this->getServiceLocator());
                $output['lesson_id'] = $id;

                $output['row']= $lessonTable->getRecord($id);

                $sessionForm = new SessionForm(null,$this->getServiceLocator());
                $output['form'] = $sessionForm;
                $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
                $viewModel->setTerminal(true);
                return $viewModel;
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
                $messages=$form->getMessages();
                print_r($messages);
                print_r($output);
                exit();

            }

        }
        else{
            exit('Invalid request');
        }



    }


    public function deletesession(Request $request,$id){
        $table = new SessionTable();
        try{
            $table->deleteRecord($id);
            session()->flash('flash_message',__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }


        return adminRedirect(array('controller'=>'student','action'=>'sessions'));

    }

    public function attendance(Request $request)
    {
        $output = array();
        $sessionTable = new SessionTable();
        $lessonTable = new LessonTable();

        $lessonId = new Select('lesson_id');
        $lessonId->setAttribute('class','form-control select2');
        $lessonId->setEmptyOption('--'.__lang('Select a Class').'--');
        $lessonId->setAttribute('required','required');
        $lessonId->setAttribute('data-ng-model','lesson_id');
        $lessonId->setAttribute('data-ng-options','o.id as o.name for o in lessonList');


        $sessionId = new Select('course_id');
        $sessionId->setAttribute('class','form-control select2');
        $sessionId->setEmptyOption('--'.__lang('Select a Session/Course').'--');
        $sessionId->setAttribute('required','required');
        $sessionId->setAttribute('data-ng-model','course_id');
        $sessionId->setAttribute('data-ng-change','loadLessons()');



        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(500);

        $options = array();
        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }

        $sessionInstructorTable = new SessionInstructorTable();
        $rowset = $sessionInstructorTable->getAccountRecords(ADMIN_ID);
        foreach($rowset as $row){
            $options[$row->course_id] = $row->name;
        }

        $sessionId->setValueOptions($options);

        $rowset = $lessonTable->getRecords();
        $options = array();
        foreach($rowset as $row){
            $options[$row->lesson_id]=$row->name;
        }

        //  $lessonId->setValueOptions($options);



        $output['lesson_id'] = $lessonId;
        $output['course_id'] = $sessionId;
        $output['pageTitle']=__lang('Attendance');
        return view('admin.student.attendance',$output);

    }

    public function attendancebulk(Request $request){

        $output = array();
        $sessionTable = new SessionTable();
        $lessonTable = new LessonTable();

        $lessonId = new Select('lesson_id');
        $lessonId->setAttribute('class','form-control select2');
        $lessonId->setEmptyOption('--'.__lang('Select a Class').'--');
        $lessonId->setAttribute('required','required');
        $lessonId->setAttribute('data-ng-model','lesson_id');
        $lessonId->setAttribute('data-ng-options','o.id as o.name for o in lessonList');

        $sessionId = new Select('course_id');
        $sessionId->setAttribute('class','form-control select2');
        $sessionId->setEmptyOption('--'.__lang('Select a Session/Course').'--');
        $sessionId->setAttribute('required','required');
        $sessionId->setAttribute('data-ng-model','course_id');


        $sessionId->setAttribute('data-ng-change',"loadBulkStudents()");



        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(500);

        $options = array();
        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }
        $sessionInstructorTable = new SessionInstructorTable();
        $rowset = $sessionInstructorTable->getAccountRecords(ADMIN_ID);
        foreach($rowset as $row){
            $options[$row->course_id] = $row->name;
        }
        $sessionId->setValueOptions($options);

        $rowset = $lessonTable->getRecords();
        $options = array();
        foreach($rowset as $row){
            $options[$row->lesson_id]=$row->name;
        }

        // $lessonId->setValueOptions($options);



        $output['lesson_id'] = $lessonId;
        $output['course_id'] = $sessionId;
        $output['pageTitle']=__lang('attendance-bulk');
        return view('admin.student.attendancebulk',$output);

    }

    public function getstudents(Request $request)
    {
        $studentTable = new StudentTable();
        $filter = $request->get('filter', null);
        $data = array();

        if(!empty($filter)){
            $rowset = $studentTable->getStudents(true,$filter);
            $rowset->setCurrentPageNumber(1);
            $rowset->setItemCountPerPage(100);


            foreach($rowset as $row){
                $data[]=array(
                    'student_id'=>$row->id,
                    'first_name'=>$row->name,
                    'last_name'=>$row->last_name,
                    'email'=>$row->email
                );
            }
        }

        exit(json_encode($data));

    }

    public function getsessionstudents(Request $request,$id)
    {
        $studentSessionTable = new StudentSessionTable();
        $session = $id;
        $data = array();
        if(!empty($session))
        {
            $rowset = $studentSessionTable->getSessionRecords(false,$session,true);
            foreach($rowset as $row){
                $data[]=array(
                    'student_id'=>$row->student_id,
                    'first_name'=>$row->name,
                    'last_name'=>$row->last_name,
                    'email'=>$row->email
                );
            }
        }

        exit(json_encode($data));
    }

    public function processattendance(Request $request){
        $attendanceTable = new AttendanceTable();
        $sessionLessonTable= new SessionLessonTable();
        $data = $request->all();
        $data = $_POST;
        $data = file_get_contents("php://input");
        /*
        print_r($data);
        $json = '';
        foreach($data as $key=>$value){
            $json = json_decode($key);
        }
        */
        $json = json_decode($data);
        $json = (array) $json;
        $json['students'] = (array) $json['students'];


        $lessonId = $json['lesson_id'];
        $sessionId = $json['course_id'];
        foreach($json['students'] as $value){
            $data = array(
                'lesson_id'=>$lessonId,
                'course_id'=>$sessionId,
                'student_id'=>$value->student_id,
                'attendance_date'=>$sessionLessonTable->getLessonDate($sessionId,$lessonId)
            );
            //    print_r($data);
            $attendanceTable->setAttendance($data);
        }

        echo json_encode(array('status'=>true));

        exit();
    }

    public function sessionattendees(Request $request,$id){
        $sessionTable = new SessionTable();
        $attendanceTable = new AttendanceTable();
        $rowset = $attendanceTable->getGroupedSessionRecords(false,$id);
        $output = array(
            'rowset'=>$rowset,
            'attendanceTable'=>$attendanceTable
        );
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
        $viewModel->setTerminal(true);
        return $viewModel;
    }

    public function sessionenrollees(Request $request,$id){
        $sessionTable = new SessionTable();
        $studentSessionTable = new StudentSessionTable();
        $attendanceTable = new AttendanceTable();
        $rowset = $studentSessionTable->getSessionRecords(false,$id,true);
        $output = array(
            'rowset'=>$rowset,
            'attendanceTable'=>$attendanceTable
        );
        $viewModel = viewModel('admin',__CLASS__,'sessionattendees',$output);

        return $viewModel;
    }


    public function enroll(Request $request,$id)
    {
        $sessionTable = new SessionTable();
        $studentSessionTable = new StudentSessionTable();
        $studentTable = new StudentTable();
        $studentRow = $studentTable->getRecord($id);

        $select = new Select('course_id');
        $select->setAttribute('class','form-control select2');
        $select->setAttribute('required','required');


        $options = array();
        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(300);

        foreach($rowset as $row){
            $options[$row->id]=$row->name.' ('.courseType($row->type).')';
        }
        $select->setValueOptions($options);
        $select->setEmptyOption('--'.__lang('Select a Session/Course').'--');
        if($request->isMethod('post'))
        {
            $post = $request->all();
            $sessionId = $post['course_id'];

            if(!$this->canEnrollToSession($sessionId)){
                return adminRedirect(array('controller'=>'student','action'=>'index'));
            }

            // $studentSessionTable->addRecord(array('student_id'=>$id,'course_id'=>$sessionId));
            $this->enrollStudent($id,$sessionId);

            session()->flash('flash_message',__lang('you-have-enrolled').' '.$studentRow->name.' '.$studentRow->last_name);
            return adminRedirect(array('controller'=>'student','action'=>'index'));
        }


        $output = array(
            'select' =>$select,
            'id'=>$id,
            'student'=>$studentRow
        );
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);

        return $viewModel;
    }


    public function export(Request $request,$id){

        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $registrationFieldTable = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();

        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die("Unable to open file!");
        $sessionRow = $sessionTable->getRecord($id);
        $totalRecords = $studentSessionTable->getTotalForSession($id);
        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        $columns = array(__lang('ID'),__lang('Name'),__lang('Telephone'),__lang('Email'));

        $fields = $registrationFieldTable->getAllFields();
        foreach($fields as $row){
            $columns[] = $row->name;
        }

        fputcsv($myfile,$columns );
        for($i=1;$i<=$totalPages;$i++){
            $paginator = $studentSessionTable->getSessionRecords(true,$id,true);
            $paginator->setCurrentPageNumber($i);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row){
                $csvData = array($row->id,$row->name,$row->mobile_number,$row->email);

                $fields = $registrationFieldTable->getAllFields();
                foreach($fields as $field){
                    $fieldRow = $studentFieldsTable->getStudentFieldRecord($row->student_id,$field->id);
                    if(empty($fieldRow)){
                        $csvData[] ='';
                    }
                    elseif($fieldRow->type=='checkbox'){
                        $csvData[] = boolToString($fieldRow->value);
                    }
                    else{
                        $csvData[] = $fieldRow->value ;
                    }


                }



                fputcsv($myfile,$csvData );

            }



        }
        $paginator = array();
        fclose($myfile);
        header('Content-type: text/csv');
        $sessionName = $sessionRow->name;
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.$sessionName.'_student_export_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();
    }


    public function exportbulkattendance(Request $request,$id){

        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $registrationFieldTable = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();
        $sessionLessonTable = new SessionLessonTable();

        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die("Unable to open file!");
        $sessionRow = $sessionTable->getRecord($id);
        $totalRecords = $studentSessionTable->getTotalForSession($id);
        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        $columns = array(__lang('ID'),__lang('Name'),__lang('Telephone'),__lang('Email'));

        //get lessons
        $lessons = $sessionLessonTable->getSessionRecords($id);
        $emptyArray= [];
        foreach($lessons as $row){
            $columns[] = $row->lesson_id.'_'.limitLength($row->name,50);
            $emptyArray[] = '';
        }



        fputcsv($myfile,$columns );
        for($i=1;$i<=$totalPages;$i++){
            $paginator = $studentSessionTable->getSessionRecords(true,$id,true);
            $paginator->setCurrentPageNumber($i);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row){
                $csvData = array($row->student_id,$row->name,$row->mobile_number,$row->email);
                $csvData = array_merge($csvData,$emptyArray);


                fputcsv($myfile,$csvData );

            }



        }
        $paginator = array();
        fclose($myfile);
        header('Content-type: text/csv');
        $sessionName = $sessionRow->name;
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.$sessionName.'_attendance_student_export_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();
    }

    public function attendanceimport(Request $request){
        $attendanceTable = new AttendanceTable();
        $sessionLessonTable= new SessionLessonTable();

        $output = array();
        $sessionTable = new SessionTable();

        $sessionId = new Select('course_id');
        $sessionId->setAttribute('class','form-control select2');
        $sessionId->setEmptyOption('--'.__lang('Select a Session/Course').'--');
        $sessionId->setAttribute('required','required');
        $sessionId->setAttribute('data-ng-model','course_id');




        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(500);

        $options = array();
        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }
        $sessionInstructorTable = new SessionInstructorTable();
        $rowset = $sessionInstructorTable->getAccountRecords(ADMIN_ID);
        foreach($rowset as $row){
            $options[$row->course_id] = $row->name;
        }
        $sessionId->setValueOptions($options);

        if($request->isMethod('post'))
        {
            $post = $request->all();
            $data = $_FILES['file'];
            $sId = $post['course_id'];
            $file = $data['tmp_name'];
            $file = fopen($file,"r");

            $all_rows = array();
            $header = null;
            while ($row = fgetcsv($file)) {
                if ($header === null) {
                    $header = $row;
                    continue;
                }
                $all_rows[] = array_combine($header, $row);
            }
            $total = 0;
            $failure = 0;

            $lessons = $sessionLessonTable->getSessionRecords($sId);
            $columns = array();
            foreach($lessons as $row){
                $columns[$row->lesson_id]= $row->lesson_id.'_'.limitLength($row->name,50);
            }

            //loop rows
            foreach($all_rows as $value){
                $dbData = array();
                $studentId=$value['ID'];

                foreach($columns as $key=>$value2)
                {

                    $lesson = trim(strtolower($value[$value2]));
                    if($lesson=='p'){
                        $data = array(
                            'student_id'=>$studentId,
                            'course_id'=>$sId,
                            'lesson_id'=>$key,
                            'attendance_date'=>$sessionLessonTable->getLessonDate($sId,$key)
                        );
                        $attendanceTable->setAttendance($data);
                    }
                }


                $total++;
            }
            $output['flash_message'] = __lang("attendance-set-msg",['total'=>$total]);
            if(!empty($failure)){
                $output['flash_message'] .= " $failure ".__lang("records failed");
            }

        }



        $output['course_id'] = $sessionId;
        $output['pageTitle']=__lang('attendance-import');
        return view('admin.student.attendanceimport',$output);
    }

    public function exporttel(Request $request,$id){

        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die("Unable to open file!");
        $sessionRow = $sessionTable->getRecord($id);
        $totalRecords = $studentSessionTable->getTotalForSession($id);
        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        //  fputcsv($myfile, array('Last Name','First Name','Telephone','Email','Class 1 - Start ','Class 1 - End','Class 2 - Start ','Class 2 - End','Class 3 - Start ','Class 3 - End','Class 4 - Start ','Class 4 - End','Class 5 - Start ','Class 5 - End','Class 6 - Start ','Class 6 - End'));
        for($i=1;$i<=$totalPages;$i++){
            $paginator = $studentSessionTable->getSessionRecords(true,$id);
            $paginator->setCurrentPageNumber($i);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row){

                fputcsv($myfile, array($row->mobile_number));

            }



        }
        $paginator = array();
        fclose($myfile);
        header('Content-type: text/csv');
        $sessionName = $sessionRow->name;
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.safeUrl($sessionName).'_student_tel_export_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();
    }

    public function massenroll(Request $request){
        set_time_limit(86400);
        $sessionTable = new SessionTable();
        $studentTable = new StudentTable();
        $studentSessionTable = new StudentSessionTable();
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();

        $select = new Select('course_id');
        $select->setAttribute('class','form-control select2');
        $select->setAttribute('required','required');


        $options = array();
        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(300);

        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }
        $select->setValueOptions($options);
        $select->setEmptyOption('--'.__lang('Select Session/Course').'--');
        $output = array();
        try {
            if($request->isMethod('post'))
            {
                $this->validate($request,[
                    'file'=>'file|mimes:csv,txt'
                ]);

                $post = $request->all();
                $data = $_FILES['file'];
                $sessionId = $post['course_id'];
                $file = $data['tmp_name'];
                $file = fopen($file,"r");

                $all_rows = array();
                $header = null;
                while ($row = fgetcsv($file)) {
                    if ($header === null) {
                        $header = $row;
                        continue;
                    }
                    $all_rows[] = array_combine($header, $row);
                }
                $total = 0;
                $failure = 0;
                //loop rows
                foreach($all_rows as $value){
                    $dbData = array();
                    $dbData['last_name']=$value['last_name'];
                    $dbData['name'] = $value['first_name'];

                    $dbData['mobile_number']=$value['mobile_number'];
                    $dbData['email']=$value['email'];

                    if(empty($dbData['last_name']) || empty($dbData['email'])){
                        continue;
                    }

                    $dbData['role_id'] = 2;
                    $dbData['enabled']=1;
                    $studentPassword = substr(md5($dbData['email'].time().rand(0,1000000)),0,6);
                    $dbData['password']= Hash::make($studentPassword);

                    try{
                        if(!$studentTable->emailExists($dbData['email'])){

                            if(!$this->canEnrollToSession($sessionId)){
                                return adminRedirect(array('controller'=>'student','action'=>'massenroll'));

                            }


                            $total++;
                            $user = User::create($dbData);
                            $user->student()->create($dbData);
                            $studentId = $user->student->id;

                            $fields = $registrationFieldsTable->getAllFields();
                            foreach($fields as $row){
                                $entry = $value[$row->id.'_'.$row->name];
                                if($row->type=='checkbox'){
                                    $entry = strtolower(trim($entry));
                                    switch($entry){
                                        case 'yes':
                                            $entry = 1;
                                            break;
                                        case 'no':
                                            $entry = 0;
                                            break;
                                        default:
                                            $entry=0;
                                            break;
                                    }
                                }
                                $studentFieldsTable->saveField($studentId,$row->id,$entry);
                            }

                            $enrollData = array('student_id'=>$studentId,'course_id'=>$sessionId);
                            $studentSessionTable->addRecord($enrollData);

                            //send email

                            $title = __lang('New Account Details');
                            $senderName = $this->getSetting('general_site_name',$this->getServiceLocator());

                            $firstName = $value['first_name'];
                            $recipientEmail = $value['email'];
                            $siteUrl = $this->getBaseUrl();
                            $message = __lang('new-account-mail',['firstName'=>$firstName,'senderName'=>$senderName,'recipientEmail'=>$recipientEmail,'studentPassword'=>$studentPassword,'siteUrl'=>$siteUrl]);

                            $this->sendEmail($recipientEmail,$title,$message);



                        }else{
                            $total++;
                            $row = $studentTable->getStudentWithEmail($dbData['email']);
                            $enrollData = array('student_id'=>$row->id,'course_id'=>$sessionId);
                            $studentSessionTable->addRecord($enrollData);
                        }

                    }
                    catch(\Exception $ex){
                        Log::critical($ex->getMessage().$ex->getTraceAsString());

                        $failure++;
                    }

                }
                $output['flash_message'] = __lang("you-enrolled-total",['total'=>$total]);
                if(!empty($failure)){
                    $output['flash_message'] .= " $failure ".__lang('records-failed');
                }

            }
        }
        catch(\Exception $ex){
            flashMessage($ex->getMessage());
            return back()->withInput();
        }
        $output['select'] = $select;
        $output['pageTitle']=__lang('Bulk Enroll');
        return view('admin.student.massenroll',$output);
    }

    public function certificatelist(Request $request){
        set_time_limit(86400);
        $sessionTable = new SessionTable();
        $studentTable = new StudentTable();
        $studentSessionTable = new StudentSessionTable();
        $attendanceTable = new AttendanceTable();
        $sessionLessonTable = new SessionLessonTable();

        $select = new Select('course_id');
        $select->setAttribute('class','form-control');
        $select->setAttribute('required','required');


        $options = array();
        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(300);

        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }
        $select->setValueOptions($options);
        $select->setEmptyOption('--'.__lang('Select Session/Course').'--');
        $output = array();

        if($request->isMethod('post')){
            $post = $request->all();
            $id = $post['course_id'];
            $file = "export.txt";
            if (file_exists($file)) {
                unlink($file);
            }

            $myfile = fopen($file, "w") or die("Unable to open file!");

            $sessionRow = $sessionTable->getRecord($id);
            $totalRecords = $studentSessionTable->getTotalForSession($id);
            $rowsPerPage = 3000;
            $totalPages = ceil($totalRecords/$rowsPerPage);

            $columns = array('Last Name','First Name','Telephone','Email');
            $lessons = $sessionLessonTable->getSessionRecords($id);
            foreach($lessons as $lesson){
                $columns[] = $lesson->id.'_'.limitLength($lesson->name,30);
            }

            fputcsv($myfile,$columns);
            for($i=1;$i<=$totalPages;$i++){
                $paginator = $studentSessionTable->getSessionRecords(true,$id,true);
                $paginator->setCurrentPageNumber($i);
                $paginator->setItemCountPerPage($rowsPerPage);

                foreach ($paginator as $row){


                    $type = $post['type'];
                    $search = $post['search'];

                    if($type=='minimum')
                    {
                        if($search=='present'){
                            $totalLessons = $attendanceTable->getTotalDistinctForStudentInSession($row->student_id,$id);
                        }
                        else{
                            $totalLessons = $attendanceTable->getTotalDistinctForStudent($row->student_id);

                        }


                        if($post['quantity'] >= 0 && $totalLessons >= $post['quantity']){

                            $values = array(ucfirst(strtolower($row->last_name)),ucfirst(strtolower($row->name)),cleanTel($row->mobile_number),strtolower($row->email));

                            foreach($lessons as $lesson)
                            {
                                $values[] = $attendanceTable->getStudentLessonDate($row->student_id,$lesson->id);
                            }

                            fputcsv($myfile,$values);

                        }
                        elseif($post['quantity'] < 0 && $totalLessons < abs($post['quantity'])){
                            $values = array(ucfirst(strtolower($row->last_name)),ucfirst(strtolower($row->name)),cleanTel($row->mobile_number),strtolower($row->email));

                            foreach($lessons as $lesson)
                            {
                                $values[] = $attendanceTable->getStudentLessonDate($row->student_id,$lesson->id);
                            }

                            fputcsv($myfile,$values);
                        }

                    }
                    else{
                        $mClasses = [];
                        foreach($post as $key=>$value){


                            if(preg_match('#lesson_#',$key)){

                                if(!empty($key)){
                                    $mClasses[] = $value;
                                }

                            }

                        }

                        if($search=='present'){

                            $status = $attendanceTable->hasClassesInSession($row->student_id,$id,$mClasses);
                        }
                        else{
                            $status = $attendanceTable->hasClasses($row->student_id,$mClasses);

                        }


                        if($status){

                            $values = array(ucfirst(strtolower($row->last_name)),ucfirst(strtolower($row->name)),cleanTel($row->mobile_number),strtolower($row->email));

                            foreach($lessons as $lesson)
                            {
                                $values[] = $attendanceTable->getStudentLessonDate($row->student_id,$lesson->lesson_id);
                            }

                            fputcsv($myfile,$values);

                        }



                    }




                }



            }
            $paginator = array();
            fclose($myfile);
            header('Content-type: text/csv');
            $sessionName = $sessionRow->name;
            // It will be called downloaded.pdf
            header('Content-Disposition: attachment; filename="'.$sessionName.'_certificate_list_'.date('d/M/Y').'.csv"');

            // The PDF source is in original.pdf
            readfile($file);
            unlink($file);
            exit();



        }

        $output['select'] = $select;
        $output['pageTitle']=__lang('Certificate List');
        return view('admin.student.certificatelist',$output);
    }



    /*****************************BEGIN IMPORT CODE********************/

    public function importsession(Request $request)
    {
        $sesionTable = new SessionTable();
        $oldSessionTable = new OldSessionTable();

        $rowset = $oldSessionTable->getRecords();
        foreach($rowset as $row){
            echo $row->name.'<br/>';
            $data = array(
                'course_id'=>$row->id,
                'session_name'=>$row->name,
                'session_date'=> mktime(null,null,null,null,null,$row->year),
                'session_status'=>0
            );
            $sesionTable->addRecord($data);
        }

        exit('done');
    }


    /*****************************END IMPORT CODE********************/
    function getBoolean($val){
        $val = strtolower(trim($val));
        if($val='yes'){
            return 1;
        }
        elseif($val='no'){
            return 0;
        }
        else{
            return 1;
        }
    }

    function getGender($string){
        $string = strtolower($string);
        $gender = substr($string,0,1);
        return $gender;
    }

    function getAgeRange($age){
        $age = trim($age);
        $age = substr($age,0,2);
        switch($age){
            case '20':
                $id = 1;
                break;
            case '31':
                $id = 2;
                break;
            case '41':
                $id = 3;
                break;
            case '51':
                $id = 4;
                break;
            case '60':
                $id = 5;
                break;
            default:
                $id=1;
                break;
        }
        return $id;

    }

    public function getMaritalStatus($status){
        $status = trim($status);
        $table = new MaritalStatusTable();
        $rowset = $table->tableGateway->select(array('marital_status'=>$status));
        $total = $rowset->count();
        if(!empty($total)){
            $row = $rowset->current();
            $id = $row->marital_status_id;
        }
        else{
            $id = 1;
        }
        return $id;
    }

    public function deleteattendance(Request $request){
        try{


            $attendanceTable = new AttendanceTable();
            $id = $request->get('id');
            $total =  $attendanceTable->deleteRecord($id);
            if(!empty($total)){
                exit(__lang('Record deleted'));
            }
            else{
                exit(__lang('Record not deleted'));
            }

        }
        catch(\Exception $ex)
        {

        }
    }

    public function exportattendance(Request $request){
        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $attendanceTable = new AttendanceTable();
        $sessionLessonTable = new SessionLessonTable();
        $id = $request->get('id');
        $row = $sessionTable->getRecord($id);

        $output = array();
        $students = $studentSessionTable->getSessionRecords(false,$id,true);
        //get lessons for session
        $output['lessons'] = $sessionLessonTable->getSessionRecords($id);
        $output['students'] = $students;
        $output['attendanceTable'] = $attendanceTable;
        $output['row'] = $row;
        $output['pageTitle'] = $row->name;
        $output['sid']=$id;

        return viewModel('admin',__CLASS__,__FUNCTION__,$output);

    }

    public function attendancedate(Request $request){

        $sessionTable = new SessionTable();
        $attendanceTable = new AttendanceTable();
        $lessonTable = new LessonTable();
        $sessionLessonTable = new SessionLessonTable();

        $select = new Select('course_id');
        $select->setAttribute('class','form-control select2');
        $select->setAttribute('required','required');


        $options = array();
        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(300);

        foreach($rowset as $row){
            $options[$row->id]=$row->name;
        }
        $select->setValueOptions($options);
        $select->setEmptyOption('--'.__lang('Select Session/Course').'--');

        $lessons = $lessonTable->getRecords();

        if($request->isMethod('post')){
            $post = $request->all();
            $sessionId = $post['course_id'];
            $count = 0;
            $rowset = $sessionLessonTable->getSessionRecords($sessionId);
            foreach($rowset as $row){
                if(!empty($post['lesson_'.$row->lesson_id])){
                    $count++;
                    $lessonDate = getDateString($post['lesson_'.$row->lesson_id]);
                    $attendanceTable->setDate($sessionId,$row->lesson_id,$lessonDate);

                }
            }
            session()->flash('flash_message',__lang('date-set-msg',['count'=>$count]));

            return adminRedirect(array('controller'=>'student','action'=>'attendancedate'));

        }

        return view('admin.student.attendancedate',['select'=>$select,'lessons'=>$lessons,'pageTitle'=>__lang('Attendance Dates')]);
    }

    public function csvsample(Request $request){

        $registrationTable = new RegistrationFieldTable();
        $file = "sample.csv";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die("Unable to open file!");
        $fields= array('last_name','first_name','mobile_number','email');

        //get custom fields
        $rowset = $registrationTable->getAllFields();
        foreach($rowset as $row){
            $fields[] = $row->id.'_'.$row->name;
        }

        fputcsv($myfile, $fields);


        fclose($myfile);
        header('Content-type: text/csv');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="sample.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();

    }

    public function getsessionlessons(Request $request,$id)
    {
        $sessionLessonsTable = new SessionLessonTable();
        $rowset = $sessionLessonsTable->getSessionRecords($id);

        $output = array();

        $lessonId = new Select('lesson_id');
        $lessonId->setAttribute('class','form-control');
        $lessonId->setEmptyOption('--'.__lang('Select a Class').'--');
        $lessonId->setAttribute('required','required');
        $lessonId->setAttribute('data-ng-model','lesson_id');

        $options = array();
        foreach($rowset as $row){
            $options[]= ['id'=>$row->lesson_id,'name'=>$row->name];
        }

        exit(json_encode($options));

        $lessonId->setValueOptions($options);

        $output['lesson_id'] = $lessonId;
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
        $viewModel->setTerminal(true);
        return $viewModel;

    }


    public function sessionlessons(Request $request,$id)
    {
        $sessionLessonTable = new SessionLessonTable();
        $rowset = $sessionLessonTable->getSessionRecords($id);

        $output= ['lessons'=>$rowset];
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);

        return $viewModel;
    }




    public function certificatelessons(Request $request,$id)
    {
        $sessionLessonTable = new SessionLessonTable();
        $rowset = $sessionLessonTable->getSessionRecords($id);

        $output= ['lessons'=>$rowset];
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);

        return $viewModel;
    }

    public function sessionstudents(Request $request,$id)
    {
        // TODO Auto-generated ParentsController::index(Request $request) default action

        $table = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $sessionLessonTable = new SessionLessonTable();
        $sessionRow = $sessionTable->getRecord($id);

        $totalLessons = $sessionLessonTable->getSessionRecords($id)->count();


        $attendanceTable = new AttendanceTable();

        $paginator = $table->getSessionRecords(true,$id,true);

        $paginator->setCurrentPageNumber((int)$request->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>$sessionRow->name.' '.__lang('Students').': '.$table->getTotalForSession($id),
            'attendanceTable'=>$attendanceTable,
            'id'=>$id,
            'totalLessons'=>$totalLessons
        ));



    }

    public function  unenroll(Request $request,$id){
        $studentSessionTable = new StudentSessionTable();

        $session = $_GET['session'];
        $studentSessionTable->unenroll($id,$session);
        session()->flash('flash_message',__lang('unenroll-msg'));
        return adminRedirect(['controller'=>'student','action'=>'sessionstudents','id'=>$session]);


    }

    public function mailsession(Request $request,$id=null){
        $sessionTable = new SessionTable();
        $studentSessionTable = new StudentSessionTable();
        $output = [];
        $count = 0;

        if(!empty($id)){
            $session = $sessionTable->getRecord($id);
            $output['subTitle'] = __lang('send-mail-st1',['total'=>$studentSessionTable->getTotalForSession($id),'session'=>$session->name]);
            $output['pageTitle']= __lang('Send Message').' : '.$session->name;
            $output['totalStudents'] = $studentSessionTable->getTotalForSession($id);
            $output['id'] = $id;
            $output['smsTitle'] = __lang('send-mail-sms1',['total'=>$studentSessionTable->getTotalForSession($id),'session'=>$session->name]);

        }
        else{

            $output['id'] = 0;
            $output['subTitle'] = __lang('send-mail-st2',['total'=>Student::count()]);

            $output['pageTitle']= __lang('Send Message').' : '.__lang('All Students');
            $output['totalStudents'] = Student::count();
            $output['smsTitle'] = __lang('send-mail-sms2',['total'=>Student::count()]);

        }



        if($request->isMethod('post'))
        {

            $message = $request->post('message');
            $senderName = $request->post('name');
            $senderEmail = $request->post('senderEmail');
            $subject = $request->post('subject');

            $studentTable = new StudentTable();

            if(!empty($id)){
                $totalRecords = $studentSessionTable->getTotalForSession($id);
            }
            else{
                $totalRecords = Student::count();
            }



            $rowsPerPage = 3000;
            $totalPages = ceil($totalRecords/$rowsPerPage);


            for($i=1;$i<=$totalPages;$i++){
                if(!empty($id)){
                    $paginator = $studentSessionTable->getSessionRecords(true,$id,true);
                }
                else{
                    $paginator = $studentTable->getStudents(true);
                }

                $paginator->setCurrentPageNumber($i);
                $paginator->setItemCountPerPage($rowsPerPage);

                foreach ($paginator as $row){

                    $this->sendEmail($row->email,$subject,$message,null,$senderName,$senderEmail);

                    $count++;


                }



            }



            session()->flash('flash_message',__lang('message-sent-total',['total'=>$count]));
            return redirect(selfURL());
            // return adminRedirect(['controller'=>'student','action'=>'sessions']);
        }

        $output['senderName'] = $this->getSetting('general_site_name',$this->getServiceLocator());
        $output['senderEmail'] = $this->getSetting('general_admin_email',$this->getServiceLocator());
        $output['gateways'] = SmsGateway::where('enabled',1)->orderBy('gateway_name')->get();

        return view('admin.student.mailsession',$output);
    }



    public function invoices(Request $request) {
        // TODO Auto-generated TransactionController::index(Request $request) default action


        $this->data['pageTitle'] = __lang('Invoices');
        $this->data['paginator']= Invoice::orderBy('id','desc')->paginate(20);
        $this->data['courses'] = Course::orderBy('name')->limit(5000)->get();
        $this->data['currencies'] = Currency::get();


        return viewModel('admin',__CLASS__,__FUNCTION__,$this->data);
    }

    public function createInvoice(Request $request){
        $this->validate($request,[
           'user_id'=>'required',
            'courses'=>'required'
        ]);

        //create cart
        $cart = new Cart(false,'s');
        $cart->setUser($request->user_id);
        $data = $request->all();
        if (isset($request->courses) && is_array($request->courses)){
            foreach($request->courses as $course){
                $cart->addSession($course);
            }
        }

        if (empty($request->amount)){
            $data['amount'] = $cart->getRawTotal();
            $data['currency_id'] = currentCurrency()->id;
        }

        if (empty($data['currency_id'])){
            $data['currency_id'] = currentCurrency()->id;
        }

        $data['cart'] = serialize($cart);
        $invoice = Invoice::create($data);
        if ($invoice->paid==1){
            $cart->approve($invoice->user_id);
            //notify student
            try{
                $this->sendEmail($invoice->user->email,__lang('invoice-approved'),__lang('invoice-approved-notification-msg',['id'=>$invoice->id]));
            }
            catch(\Exception $ex){

            }
        }
        else{
            $subject = __lang('new-invoice');
            $url= route('student.student.invoices');
            $message = __lang('new-invoice-msg',['id'=>'#'.$invoice->id]).'<br/>'."<a href=\"{$url}\">{$url}</a>";

            try{
                $this->sendEmail($invoice->user->email,$subject,$message);
            }
            catch(\Exception $ex){

            }
        }
        return back()->with('flash_message',__lang('changes-saved'));
    }

    public function approvetransaction(Request $request,$id){

        $invoice = Invoice::find($id);
        $invoice->paid = 1;
        $invoice->save();

        //add payment

        $cart = unserialize($invoice->cart);
        $cart->approve($invoice->user_id);

        //notify student
        try{
            $this->sendEmail($invoice->user->email,__lang('invoice-approved'),__lang('invoice-approved-notification-msg',['id'=>$id]));
        }
        catch(\Exception $ex){

        }

        session()->flash('flash_message',__lang('invoice-approved-msg'));
        return back();

    }

    private  function  enrollStudent($studentId,$sessionId){
        $studentSessionTable = new StudentSessionTable();
        $studentTable = new StudentTable();
        $sessionTable = new SessionTable();
        $sessionRow = $sessionTable->getRecord($sessionId);
        $code = generateRandomString(5);
        $studentSessionTable->addRecord(array(
            'student_id'=>$studentId,
            'course_id'=>$sessionId,
            'reg_code'=>$code,
            'created_at'=>Carbon::now()->toDateString(),
            'updated_at'=>Carbon::now()->toDateString(),
        ));

        $student = $studentTable->getRecord($studentId);
        $message = __lang('enrollment-mail',['course'=>$sessionRow->name,'code'=>$code]);
        $emailMessage = $message.setting('regis_enroll_mail');
        $this->sendEmail($student->email,__lang('Enrollment Complete'),$emailMessage);
        $this->sendEnrollMessage($student,$sessionRow->name);
    }

    public function payments(Request $request)
    {
        $table = new PaymentTable();

        $startDate=null;
        $endDate=null;
        $start=null;
        $end=null;


        if(isset($_GET['start'])){
            $start =$_GET['start'];
        }


        if(isset($_GET['end'])){
            $end =$_GET['end'];
        }


        if(isset($start)){

            $start = str_replace('-', '/', $start);
            $startDate = getDateString($start);
        }

        if(isset($end)){
            $end = str_replace('-', '/', $end);
            $endDate = getDateString($end);
        }



        $paginator = $table->getPaymentPaginatedRecords(true,$startDate,$endDate);

        $paginator->setCurrentPageNumber((int)$request->get('page', 1));
        $paginator->setItemCountPerPage(30);
        $sum = $table->getSum($startDate,$endDate);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Payments'),
            'startDate'=>str_replace('/','-',$start),
            'endDate'=>str_replace('/','-',$end),
            'sum'=>$sum
        ));

    }

    public function instructors(Request $request,$id){
        $sessionLessonTable = new SessionLessonTable();
        $sessionLessonAccountTable = new SessionLessonAccountTable();
        $row = Course::find($id);

        $rowset = $sessionLessonTable->getSessionRecords($id);
        return view('admin.student.instructors',[
            'pageTitle'=>__lang('Instructors for').' '.$row->name,
            'table'=>$sessionLessonAccountTable,
            'rowset'=>$rowset,
            'id'=>$id
        ]);

    }

    public function manageinstructors(Request $request,Course $course,Lesson $lesson){

        $sessionLessonTable = new SessionLessonTable();
        $sessionLessonAccountTable = new SessionLessonAccountTable();
        $row = $course->lessons()->find($lesson->id);
        //get accountlist
        $accountsTable = new AccountsTable();
        $rowset = $accountsTable->getRecordsSorted();

        if($request->isMethod('post'))
        {
            $sessionLessonAccountTable->clearSessionLessons($course->id,$lesson->id);
            $data = $request->all();
            unset($data['_token']);
            foreach($data as $key=>$value){
                if(!empty($value)){
                    $sessionLessonAccountTable->addRecord([
                        'course_id'=>$course->id,
                        'lesson_id'=>$lesson->id,
                        'admin_id'=>$value
                    ]);
                }
            }



            session()->flash('flash_message',__lang('Changes Saved!'));
            return adminRedirect(['controller'=>'student','action'=>'instructors','id'=>$course->id]);

        }

        return viewModel('admin',__CLASS__,__FUNCTION__,[
            'rowset'=>$rowset,
            'table'=>$sessionLessonAccountTable,
            'slrow'=>$row,
            'course'=>$course,
            'lesson'=>$lesson
        ]);
    }


    public function import(Request $request){
        set_time_limit(86400);
        $sessionTable = new SessionTable();
        $studentTable = new StudentTable();
        $studentSessionTable = new StudentSessionTable();
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();

        $rowset = $sessionTable->getPaginatedRecords(true);
        $rowset->setCurrentPageNumber(1);
        $rowset->setItemCountPerPage(300);

        $output = array();
        if($request->isMethod('post'))
        {
            $post = $request->all();
            $data = $_FILES['file'];
            $file = $data['tmp_name'];
            $file = fopen($file,"r");

            $all_rows = array();
            $header = null;
            while ($row = fgetcsv($file)) {
                if ($header === null) {
                    $header = $row;
                    continue;
                }
                $all_rows[] = array_combine($header, $row);
            }
            $total = 0;
            $failure = 0;
            //loop rows
            foreach($all_rows as $value){
                $dbData = array();
                $dbData['last_name']=$value['last_name'];



                $dbData['name'] = $value['first_name'];

                $dbData['mobile_number']=$value['mobile_number'];
                $dbData['email']=$value['email'];

                if(empty($dbData['last_name']) || empty($dbData['email'])){
                    continue;
                }

                $dbData['enabled']=1;
                $dbData['role_id']= 2;
                $studentPassword = substr(md5($dbData['email'].time().rand(0,1000000)),0,6);
                $dbData['password']=Hash::make($studentPassword);



                //$dbData['student_created']=time();

                try{
                    if(!$studentTable->emailExists($dbData['email'])){
                        $total++;

                        $user = new User();
                        $user->fill($dbData);
                        $user->save();
                        $student =  $user->student()->create([
                            'mobile_number'=>$dbData['mobile_number']
                        ]);

                        $studentId = $student->id;
                      //  $studentId = $studentTable->addRecord($dbData);

                        $fields = $registrationFieldsTable->getAllFields();
                        foreach($fields as $row){
                            $entry = $value[$row->id.'_'.$row->name];
                            if($row->type=='checkbox'){
                                $entry = strtolower(trim($entry));
                                switch($entry){
                                    case 'yes':
                                        $entry = 1;
                                        break;
                                    case 'no':
                                        $entry = 0;
                                        break;
                                    default:
                                        $entry=0;
                                        break;
                                }
                            }
                            $studentFieldsTable->saveField($studentId,$row->id,$entry);
                        }

                        //send email

                        $title = __lang('New Account Details');
                        $senderName = $this->getSetting('general_site_name',$this->getServiceLocator());

                        $firstName = $value['first_name'];
                        $recipientEmail = $value['email'];

                        $siteUrl = $this->getBaseUrl();
                        $message = __lang('new-account-mail',['firstName'=>$firstName,'senderName'=>$senderName,'recipientEmail'=>$recipientEmail,'studentPassword'=>$studentPassword,'siteUrl'=>$siteUrl]);

                        $this->sendEmail($recipientEmail,$title,$message);



                    }

                }
                catch(\Exception $ex){

                    $failure++;
                }

            }
            $output['flash_message'] = __lang("import-success",['total'=>$total]);
            if(!empty($failure)){
                $output['flash_message'] .= " $failure ".__lang("records failed");
            }

        }

        $output['pageTitle']=__lang('Import/Export Students');

        return view('admin.student.import',$output);
    }



    public function exportstudents(Request $request){

        $studentTable = new StudentTable();
        $registrationFieldTable = new RegistrationFieldTable();
        $studentFieldsTable = new StudentFieldTable();

        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die("Unable to open file!");
        $totalRecords = $studentTable->getTotal();
        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        $columns = array(__lang('ID'),__lang('Last Name'),__lang('First Name'),__lang('Telephone'),__lang('Email'),__lang('Created On'));

        $fields = $registrationFieldTable->getAllFields();
        foreach($fields as $row){
            $columns[] = $row->name;
        }



        fputcsv($myfile,$columns );
        for($i=1;$i<=$totalPages;$i++){
            $paginator = $studentTable->getStudents(true);
            $paginator->setCurrentPageNumber($i);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row){
                $csvData = array($row->id,$row->last_name,$row->name,$row->mobile_number,$row->email,showDate('d/M/Y',$row->created_at));

                $fields = $registrationFieldTable->getAllFields();
                foreach($fields as $field){
                    $fieldRow = $studentFieldsTable->getStudentFieldRecord($row->id,$field->id);
                    if(empty($fieldRow)){
                        $csvData[] ='';
                    }
                    elseif($fieldRow->type=='checkbox'){
                        $csvData[] = boolToString($fieldRow->value);
                    }
                    else{
                        $csvData[] = $fieldRow->value ;
                    }


                }



                fputcsv($myfile,$csvData );

            }



        }
        $paginator = array();
        fclose($myfile);
        header('Content-type: text/csv');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="student_export_'.date('r').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();
    }

    public function deleteinvoice(Request $request,$id){

        $invoice = Invoice::find($id);
        $invoice->delete();
        flashMessage(__lang('Record deleted'));
        return back();

    }

    public function search(Request $request){
        $keyword = $request->get('term');

        if(empty($keyword)){
            return response()->json([]);
        }

        $users = User::where(function ($query)  use ($keyword){
            $query->where(function($query) use($keyword){
                $query->where('name','LIKE','%'.$keyword.'%')->orWhere('last_name','LIKE','%'.$keyword.'%')->orWhere('email','LIKE','%'.$keyword.'%');
            });
        })->limit(500)->get();

        $formattedUsers = [];

        foreach($users as $user){
            $formattedUsers[] = ['id'=>$user->id,'text'=>"{$user->name} {$user->last_name} <{$user->email}>"];
        }

        return response()->json($formattedUsers);
    }

    public function code(Request $request){
        $students = false;
        if (!empty($request->code)){
            $code = $request->code;
            $students = StudentCourse::where('reg_code','LIKE','%'.$code.'%')->paginate(30);
        }
        $pageTitle = __lang('verify-code');

        return view('admin.student.code',compact('students','pageTitle'));
    }

}
