<?php

namespace App\Http\Controllers\Student;
use App\Admin;
use App\Certificate;
use App\Country;
use App\Discussion;
use App\Http\Controllers\Controller;
use App\Lib\Cart;
use App\Lib\HelperTrait;
use App\RevisionNote;
use App\StudentCourse;
use App\StudentField;
use App\User;
use App\V2\Form\DiscussionForm;
use Dompdf\Options;
use Illuminate\Http\Request;

use App\Assignment;
use App\AssignmentSubmission;
use App\Invoice;
use App\Student;
use App\StudentCertificate;
use App\Test;
use App\V2\Form\DiscussionFilter;
use App\V2\Form\StudentFilter;
use App\V2\Form\StudentForm;
use App\V2\Model\AccountsTable;
use App\V2\Model\AssignmentSubmissionTable;
use App\V2\Model\AttendanceTable;
use App\V2\Model\CertificateLessonTable;
use App\V2\Model\CertificateTable;
use App\V2\Model\CertificateTestTable;
use App\V2\Model\DiscussionAccountTable;
use App\V2\Model\DiscussionReplyTable;
use App\V2\Model\DiscussionTable;
use App\V2\Model\DownloadFileTable;
use App\V2\Model\DownloadSessionTable;
use App\V2\Model\DownloadTable;
use App\V2\Model\RegistrationFieldTable;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionLessonAccountTable;
use App\V2\Model\SessionLessonTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SessionTestTable;
use App\V2\Model\StudentCertificateTable;
use App\V2\Model\StudentFieldTable;
use App\V2\Model\StudentLectureTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\SurveyQuestionTable;
use App\V2\Model\SurveyResponseTable;
use App\V2\Model\SurveyTable;
use App\V2\Model\TestGradeTable;
use App\V2\Model\TestQuestionTable;
use Dompdf\Dompdf;
use App\Lib\BaseForm;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laminas\EventManager\EventManagerInterface;
use Laminas\View\Model\ViewModel;
use App\V2\Model\StudentTable;
use App\V2\Model\HomeworkTable;


/**
 * ParentsController
 *
 * @author
 *
 * @version
 *
 */
class StudentController extends Controller {

    use HelperTrait;

    protected $uploadDir;

    public function __construct(){
        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $this->uploadDir = 'usermedia'.$user.'/student_uploads/'.date('Y_m');
    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            $controller->layout('layout/student');
        }, 100);
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
	public function index(Request $request) {
		// TODO Auto-generated ParentsController::index(Request $request) default action

        $output = [];
        $viewModel = $this->forward()->dispatch('Application\Controller\Catalog',['action'=>'sessions']);
        $output['sessions'] = $viewModel->getVariables();
        $output['sessions']['paginator']->setItemCountPerPage(5);



        $viewModel = $this->forward()->dispatch('Application\Controller\Catalog',['action'=>'courses']);
        $output['courses'] = $viewModel->getVariables();
        $output['courses']['paginator']->setItemCountPerPage(5);

        $studentId = $this->getId();

        $viewModel = $this->forward()->dispatch('Application\Controller\Student',['action'=>'mysessions']);
        $output['mysessions'] = $viewModel->getVariables();
        $output['mysessions']['paginator']->setItemCountPerPage(3);


        $viewModel = $this->forward()->dispatch('Application\Controller\Student',['action'=>'notes']);
        $output['notes'] = $viewModel->getVariables();
        $output['notes']['paginator']->setItemCountPerPage(5);


        $viewModel = $this->forward()->dispatch('Application\Controller\Download',['action'=>'index']);
        $output['downloads'] = $viewModel->getVariables();
        $output['downloads']['paginator']->setItemCountPerPage(5);

        $viewModel = $this->forward()->dispatch('Application\Controller\Student',['action'=>'discussion']);
        $output['discussions'] = $viewModel->getVariables();
        $output['discussions']['paginator']->setItemCountPerPage(5);

        $viewModel = $this->forward()->dispatch('Application\Controller\Assignment',['action'=>'index']);
        $output['homework'] = $viewModel->getVariables();
        $output['homework']['paginator']->setItemCountPerPage(100);

        $totalHomework= $output['homework']['total'];
        $submissionTable = new AssignmentSubmissionTable();
        $output['homeworkPresent'] = false;
        if(!empty($totalHomework)){
            foreach ($output['homework']['paginator'] as $row){
                if(!$submissionTable->hasSubmission($this->getId(),$row->assignment_id)){
                    $output['homeworkPresent'] = true;
                }
            }

        }
        $output['controller'] = $this;
        $output['student'] = Student::find($studentId);
        $output['gradeTable'] = new TestGradeTable();

        $viewModel = $this->forward()->dispatch('Application\Controller\Student',['action'=>'certificates']);
        $output['certificate'] = $viewModel->getVariables();
        $output['certificate']['paginator']->setItemCountPerPage(7);

        //create forum topics
        $studentSessionTable = new StudentSessionTable();
        $forumTopics = $studentSessionTable->getForumTopics(true,$this->getId());
        $forumTopics->setItemCountPerPage(10);
        $output['forumTopics'] = $forumTopics;


        $this->layout('layout/student');
        $output['pageTitle'] = __lang('Dashboard');
        return new ViewModel ($output);
	}

	public function getStudentProgress($sessionId){
	    $attendanceTable = new AttendanceTable();

	    $session = \Application\Entity\Course::find($sessionId);
	    $totalLessons = $session->sessionLessons()->count();


	    $totalAttended  = $attendanceTable->getTotalDistinctForStudentInSession($this->getId(),$sessionId);

        if($totalLessons==0){
            $totalLessons = 1;
        }
	    //calculate percentage
        $percentage = ($totalAttended/$totalLessons) * 100;
        $percentage = round($percentage);
        return $percentage;

    }

    public function mysessions(Request $request){

        if(isMobileApp()){
            return redirect()->route('mobile-close');
        }
        $studentId = $this->getId();
        $studentSessionTable = new StudentSessionTable();
        $attendanceTable = new AttendanceTable();
        $paginator = $studentSessionTable->getStudentRecords(true,$studentId);
        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(24);

        $total = $studentSessionTable->getTotalForStudent($studentId);

        $output = [
            'pageTitle'=>__lang('Enrolled Courses'),
            'paginator'=>$paginator,
            'id'=>$studentId,
            'studentSessionTable'=>$studentSessionTable,
            'attendanceTable'=>$attendanceTable,
            'total'=>$total
        ];
        return viewModel('student',__CLASS__,__FUNCTION__,$output);
    }

    /**
     * This displays information for auto enroll
     */
    public function welcome(Request $request)
    {

    }

    /**
     * @return ViewModel
     * @throws \Exception
     * The student can change their account information here
     */
	public function profile(Request $request)
	{
        $output = array();
        $studentsTable = new StudentTable();
        $form = new StudentForm(null,$this->getServiceLocator(),true);
        $registrationFieldsTable = new RegistrationFieldTable();
        $studentFieldTable = new StudentFieldTable();
        $output['fields'] = $registrationFieldsTable->getActiveFields();

        $filter = new StudentFilter($this->getServiceLocator(),true);
        $id = $this->getId();

        $row = $studentsTable->getRecord($id);
        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $data['status']= ($row->enabled=='') ? 1:$row->enabled;

            $form->setData(array_merge_recursive(
                    $data,
                $_FILES
            ));


            if ($form->isValid()) {



                $data = $form->getData();

                //check for formatted phone number
                $formattedNo  = $request->post('fmobilenumber');
                if(!empty($formattedNo)){
                    $data['mobile_number'] = $formattedNo;
                }


                $data = removeTags($data);



                $array = [
                    'name'=>$data['name'],
                    'last_name'=>$data['last_name'],
                    'mobile_number'=>$data['mobile_number'],
                    'email'=>$data['email'],
                    'enabled'=>$data['status'],
                ];


                //store dp
                if(!empty($_FILES['picture']['name'])){
                    @unlink($row->picture);

                    $file = $data['picture']['name'];
                    $newPath = $this->uploadDir.'/'.time().$id.'_'.sanitize($file);
                    $this->makeUploadDir();
                    rename($data['picture']['tmp_name'],$newPath);
                    chmod($newPath,0644);
                    $array['picture'] = $newPath;

                }





               // $array[$studentsTable->getPrimary()]=$id;
                //$studentsTable->saveRecord($array);
                $student = $this->getStudent();
                $student->fill($array);
                $student->save();
                $student->user->fill($array);
                $student->user->save();



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

                flashMessage(__lang('Changes saved!'));
                redirect(selfURL());
                $output['message'] = __lang('Changes saved!');

            }
            else{
                $errors = $form->getMessages();

                $fields= '';
                foreach($errors as $key=>$value){
                    $key= $form->get($key)->getLabel();

                    $fields .= '<br/><strong>'.ucfirst($key).'</strong>: ';
                    foreach($value as $msg){
                        $fields .= $msg.'. ';
                    }
                }
                $output['message'] = __lang('save-failed-msg').$fields;
                flashMessage($output['message']);
            }

        }
        else {

            $data = getObjectProperties($row);
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
        $output['pageTitle']= __lang('Your Profile');
        $output['row']= $studentsTable->getRecord($id);
        $output['action'] = 'edit';
        $output['table'] = new StudentFieldTable();



        $viewModel = viewModel('student',__CLASS__,__FUNCTION__,$output);
        return $viewModel;
	}

    public function removeimage(Request $request){

	    $user = Auth::user();
	    $user->picture = null;
	    $user->save();

        flashMessage(__lang('Display picture removed'));
        return back();
    }

    /**
     * @return ViewModel
     * This displays the list of sessions for the student to select from
     */
    public function enroll(Request $request){



        $table = new SessionTable();
        $studentSessionTable = new StudentSessionTable();
        $paginator = $table->getValidSessions(true,['b','s']);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);

        $authService=$this->getAuthService();
        $role = getRole();


        if($authService->hasIdentity() && $role=='student'){
            $id = $this->getId();
        }
        else{
            $id = 0;
        }

        return new ViewModel (array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Enroll For A Session'),
            'studentSessionTable'=>$studentSessionTable,
            'id'=>$id,
            'terminal'=>$this->params('terminal')
        ));
    }



    public function cart(Request $request){

        $this->data['pageTitle'] = __lang('Your Orders');

        $this->data['cart'] = getCart();

        return $this->data;
    }

      public function setsession(Request $request){
          $id = $this->params('id');

        if(!$this->canEnrollToSession($id)){
            return back();
        }

        $sessionTable= new SessionTable();
        $studentSessionTable = new StudentSessionTable();
        $session = new Container('enroll');

        $session->id = $id;
        $row = $sessionTable->getRecord($id);
        $authService = $this->getAuthService();
        $role = getRole();
        $type = ($row->type=='c')? __lang('Course'):__lang('Session');
        if(!$authService->hasIdentity() || $role != 'student')
        {
            return redirect()->route('application/signin');
        }
        elseif( (empty($row->payment_required) || $row->amount==0 ) && (empty($row->enrollment_closes) || $row->enrollment_closes > time())  && !empty($row->session_status)){


             $code = generateRandomString(5);
             $studentSessionTable->addRecord(array(
                 'student_id'=>$this->getId(),
                 'course_id'=>$id,
                 'reg_code'=>$code,
                 'enrolled_on'=>time()
             ));

             $student = $this->getStudent();

            $sessionName =$row->session_name;
             $message = __lang('you-suc-enroll')." $sessionName $type! <br/>"."<h4>".__lang('Your enrollment code is')." $code</h4>";
            // $emailMessage = $message.'<br/>'.$this->getSetting('regis_email_message',$this->getServiceLocator());
            $emailMessage = $message.setting('regis_email_message');
             $this->sendEmail($student->email,__lang('Enrollment Complete'),$emailMessage);

             $this->sendEnrollMessage($student,$row->session_name);
            $message = __lang('you-suc-enroll')." $sessionName $type!";
             flashMessage($message);
             //return redirect()->route('application/enroll');
            if($row->type!='c'){
                return redirect()->route('session-details',array('id'=>$row->course_id));
            }
            else{
                //redirect to the course introduction page
                return redirect()->route('application/default',['controller'=>'course','action'=>'intro','id'=>$row->course_id]);
            }

         }
        elseif(!(empty($row->payment_required) || $row->amount==0 ) && (empty($row->enrollment_closes) || $row->enrollment_closes > time()) && !empty($row->session_status))
        {
            $cart = getCart();
            $cart->addSession($id);
            return redirect()->route('cart');
        }
        else{
            flashMessage(__lang('enroll-not-avail').' '.$type);
            return back();
        }


    }

    public function removesession(Request $request){
        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();

        $id = $this->params('id');
        $session = $sessionTable->getRecord($id);
        if(empty($session->payment_required))
        {
            $studentSessionTable->tableGateway->delete(array(
                'student_id'=>$this->getId(),
                'course_id'=>$id
            ));
            flashMessage(__lang('suc-unenroll'));
        }
        else{
            flashMessage(__lang('unenroll-fail'));
        }

        return back();
        //return redirect()->route('application/enroll');
    }

    public function classes(Request $request)
    {
        $attendanceTable = new AttendanceTable();
        $id = $this->getId();

        $attendance = $attendanceTable->getStudentRecords(true,$id);

        $attendance->setCurrentPageNumber((int)request()->get('page', 1));
        $attendance->setItemCountPerPage(30);

        $viewModel = viewModel('student',__CLASS__,__FUNCTION__,array(
            'attendance'=>$attendance,
            'pageTitle'=>__lang('Classes Attended')
        ));
        return $viewModel;
    }

	public function password(Request $request)
	{
		$output = array();

		$accountsTable =new StudentTable();
		if (request()->isMethod('post')) {
		    $this->validate($request,[
		        'password'=>['required', 'string', 'min:8', 'confirmed']
            ]);

			    $user = Auth::user();
			    $user->password = Hash::make($request->password);
			    $user->save();
				flashMessage(__lang('Password changed!'));
                return redirect(selfURL());

		}
		$output['pageTitle']=__lang('Change Your Password');
		return view('student.student.password',$output);
	}

    public function notes(Request $request){

        $table = new StudentSessionTable();
        $homeworkTable = new HomeworkTable();
        $paginator = $table->getStudentRecords(true,$this->getId());

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return view('student.student.notes',array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('select-a-session/course'),
            'homeworkTable'=>$homeworkTable,
            'id'=>$this->getId()
        ));
    }

    private function enrolledInSession($id){
      $studentSessionTable = new StudentSessionTable();
        return $studentSessionTable->enrolled($this->getId(),$id);
    }

	public function sessionnotes(Request $request,$id) {
		// TODO Auto-generated ArticlesController::index(Request $request) default action
		$table = new HomeworkTable();
		$sessionTable = new SessionTable();


        if(!$this->enrolledInSession($id)){
            return redirect()->route('student.dashboard');
        }

		$paginator = $table->getPaginatedRecords(true,$id);
		$session = $sessionTable->getRecord($id);

		$paginator->setCurrentPageNumber((int)request()->get('page', 1));
		$paginator->setItemCountPerPage(30);
		return view('student.student.sessionnotes',array(
				'paginator'=>$paginator,
				'pageTitle'=>__lang('Revision Notes').': '.$session->name,
				'session'=>$session->name,
                'id'=>$id
		));


	}



	public function viewnote(Request $request,$id)
	{
		$homeworktable= new HomeworkTable();
		$row= RevisionNote::findOrFail($id);
        if(!$this->enrolledInSession($row->course_id)){
            return redirect()->route('student.dashboard');
        }
		return view('student.student.viewnote',array('row'=>$row,'pageTitle'=>__lang('Revision Note').': '.$row->title));
	}

    public function timetable(Request $request,$id,$slug){

        $studentSessionTable = new StudentSessionTable();
        $studentLectureTable = new StudentLectureTable();
        $resumeLink = '';
        $resumeText = __lang('Resume');
        $enrolled = false;
        $studentCourse = false;

            $studentId = $this->getId();
            if ($studentSessionTable->enrolled($studentId, $id)) {
                $studentCourse = $this->getStudent()->studentCourses()->where('course_id',$id)->first();
                $enrolled= true;
                //check if student has started lecture
                if($studentLectureTable->hasLecture($studentId,$id)){
                    $lecture = $studentLectureTable->getLecture($studentId,$id);
                    if($lecture->sort_order == 1){
                        //  $resumeLink = $this->url()->fromRoute('view-class', ['classId' => $lecture->lesson_id, 'sessionId' => $id]);
                        $resumeLink = route('student.course.class',['lesson'=>$lecture->lesson_id,'course'=>$id]);
                    }
                    else{
                        // $resumeLink = $this->url()->fromRoute('view-lecture', ['lectureId' => $lecture->lecture_id, 'sessionId' => $id]);
                        $resumeLink = route('student.course.lecture',['lecture'=>$lecture->lecture_id,'course'=>$id]);

                    }


                }
                else{

                    $resumeLink = route('student.course.intro',['id'=>$id]);
                    $resumeText = __lang('Start');
                }

            }

        $sessionTable = new SessionTable();
        $sessionLessonTable = new SessionLessonTable();
        $sessionLessonAccountTable = new SessionLessonAccountTable();

        $sessionInstructorTable = new SessionInstructorTable();
        $discussionForm= new DiscussionForm(null,$studentId);
        $downloadSessionTable = new DownloadSessionTable();

        $row = $sessionTable->getRecord($id);
        $rowset = $sessionLessonTable->getSessionRecords($id);



        //get instructors
        $instructors = $sessionInstructorTable->getSessionRecords($id);

        //get downloads
        $downloads = $downloadSessionTable->getSessionRecords($id);

        //get session tests
        $sessionTestTable  = new SessionTestTable();
        $tests = $sessionTestTable->getSessionRecords($id);



        $output= ['rowset'=>$rowset,'row'=>$row,'pageTitle'=>__lang('Session Details'),'table'=>$sessionLessonAccountTable,'id'=>$id,
        'studentId'=>$studentId,
        'studentSessionTable'=>$studentSessionTable,
            'instructors' => $instructors,
            'form'=>$discussionForm,
            'downloads'=>$downloads,
            'fileTable'=> new DownloadFileTable(),
            'resumeLink'=>$resumeLink,
            'resumeText'=>$resumeText,
            'enrolled'=>$enrolled,
            'tests'=>$tests,
            'questionTable'=>new TestQuestionTable(),
            'studentTest'=> new StudentTestTable(),
            'totalClasses'=> $sessionLessonTable->getSessionRecords($id)->count(),
            'studentCourse'=>$studentCourse
        ];


            return viewModel('student',__CLASS__,__FUNCTION__,$output);



    }

    public function discussion(Request $request)
    {
        $table = new DiscussionTable();
        $discussionForm= new DiscussionForm(null,$this->getId());
        $discussionAccountTable = new DiscussionAccountTable();
        $sessionTable = new SessionTable();
        $paginator = $table->getPaginatedRecordsForStudent(true,$this->getId());

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return view('student.student.discussion',array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Instructor Chat'),
            'form'=>$discussionForm,
            'accountTable'=>$discussionAccountTable,
            'sessionTable'=>$sessionTable
        ));
    }

    public function adddiscussion(Request $request)
    {

        $discussionTable = new DiscussionTable();
        $discussionAccountTable = new DiscussionAccountTable();
        $studentSessionTable = new StudentSessionTable();
        $form = new DiscussionForm(null,$this->getId());
        $filter = new DiscussionFilter();
        $form->setInputFilter($filter);

        if(request()->isMethod('post'))
        {
            $formData = request()->all();
            $form->setData($formData);

            if($form->isValid())
            {
                $data = $form->getData();
                $data = removeTags($data);
                unset($data['admin_id[]']);
                $data['student_id'] = $this->getId();

                $discussionId = $discussionTable->addRecord($data);
                //check if there are accounts
                $title = __lang('New question').': '.$data['subject'];
                $user = $this->getStudent();

                //get list of sessions
                $list = '<br/><strong>'.__lang('en-courses-sessions').'</strong>:';
                if($studentSessionTable->getTotalForStudent($this->getId())==0){
                    $list .= __lang('None');
                }
                else{
                    $rowset = $studentSessionTable->getStudentRecords(false,$this->getId());
                    foreach($rowset as $row){
                        $list.=$row->name.', ';
                    }

                }
                $list= '<br/>';
                $message = __lang('new-chat-mail',['firstname'=>$user->first_name,'lastname'=>$user->last_name,'subject'=>$data['subject'],'question'=>$data['question'],'list'=>$list,'link'=>$this->getBaseUrl().'/admin']);
                $admins = 0;

                foreach($formData['admin_id'] as $value){
                    $accountId = $value;
                    if($accountId !='admins'){

                        $this->notifyAdmin($accountId,$title,$message);

                        if(Admin::find($accountId) && Discussion::find($discussionId)){

                            $discussionAccountTable->addRecord([
                                'admin_id'=>$accountId,
                                'discussion_id'=> $discussionId
                            ]);
                        }

                    }
                    else{
                        $admins = 1;
                        $this->notifyAdmins($title,$message);
                    }
                }
                $discussionTable->update(['admin'=>$admins],$discussionId);


                flashMessage(__lang('your-ques-added'));
            }
            else{
                flashMessage($this->getFormErrors($form));
           }
        }

        return back();
        //return redirect()->route('application/discussions');

    }

    public function addreply(Request $request,$id){
        $table = new DiscussionReplyTable();
        $discussionTable = new DiscussionTable();
        $discussionAccountTable = new DiscussionAccountTable();
        $form = new BaseForm();
        $form->addCSRF();
        $form->createTextArea('reply','Reply',true,null,__lang('Reply here'));

        $accountTable = new AccountsTable();
        $discussionRow = $discussionTable->getRecord($id);
        $this->validateOwner($discussionRow);
        if(request()->isMethod('post'))
        {
            $reply = $request->post('reply');
            $form->setData(request()->all());
            if(!empty($reply) && $form->isValid()){
                $data = [
                    'discussion_id'=>$id,
                    'reply'=> strip_tags($reply),
                    'user_id'=> Auth::user()->id
                ];
                $table->addRecord($data);
                $discussionTable->update(['replied'=>0],$id);
                $user = $this->getStudent();
                $name = $user->user->name.' '.$user->user->last_name;

                //send notification to admins
                $subject = __lang('New reply for').' "'.$discussionRow->subject.'"';
                $message = __lang('New reply for').' "'.$discussionRow->subject."\". $name ".__lang('said').": <br/>".$reply;
                $rowset = $table->getRepliedAdmins($id);
                foreach($rowset as $row){
                    try{
                        $account = $accountTable->getRecord($row->user_id);
                        if(!empty($account->email)){
                            $this->sendEmail($account->email,$subject,$message);
                        }
                    }
                    catch(\Exception $ex)
                    {

                    }

                }
                flashMessage(__lang('reply-added-msg'));
            }
            else{
                flashMessage(__lang('submission-failed-msg'));
            }

        }
        return redirect()->route('student.student.viewdiscussion',['id'=>$id]);
    }

    public function viewdiscussion(Request $request,$id)
    {
        $table = new DiscussionReplyTable();
        $discussionTable = new DiscussionTable();
        $row= $discussionTable->getRecord($id);
        $this->validateOwner($row);
        $form = new BaseForm();
        $form->createTextArea('reply','Reply',true,null,__lang('Reply here'));


        $paginator = $table->getPaginatedRecordsForDiscussion(true,$id);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);

        return view('student.student.viewdiscussion',array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('View Instructor Chat'),
            'row'=>$row,
            'student'=>$this->getStudent(),
            'accountTable'=> new AccountsTable(),
            'total'=>$table->getTotalReplies($id),
            'form'=>$form
        ));
    }


    public function certificates(Request $request){
        $table = new StudentSessionTable();
        $clTable =new CertificateLessonTable();
        $ctTable = new CertificateTestTable();

        $id= $this->getId();

        $paginator = $table->getCertificates(true,$id);


        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return view('student.student.certificates',array(
            'paginator'=>$paginator,
            'pageTitle'=>$this->setting('label_certificates',__lang('Certificates')),
            'clTable'=>$clTable,
            'ctTable'=>$ctTable
        ));

    }

    public function certificate(Request $request){
        $certificateTable = new CertificateTable();
        $id = $this->params('id');
        if(!$this->canAccessCertificate($id)){
            flashMessage(__lang('not-met-requirements'));
          return redirect()->route('application/certificates');
        }
        $row = $certificateTable->getRecord($id);
        $html = $this->getCertificateHtml($id);

        $viewModel = viewModel('student',__CLASS__,__FUNCTION__,['html'=>$html,'row'=>$row]);
        $viewModel->setTerminal(true);
        return $viewModel;
    }

    public function downloadcertificate(Request $request,$id){
        $certificateTable = new CertificateTable();

        $certificate = Certificate::findOrFail($id);

        if(!$this->canAccessCertificate($id)){
            flashMessage(__lang('not-met-requirements'));
            return redirect()->route('student.student.certificates');
        }


        if($certificate->payment_required==1 && !hasCertificatePayment($id)){
            flashMessage(__lang('payment-required'));

            getCart('c')->addCertificate($id);
            return redirect()->route('cart');
        }



        if(!$this->canDownload($id)){
            flashMessage(__lang('exceeded-max-downloads'));
            return redirect()->route('application/certificates');
        }



        $html = $this->getCertificateHtml($id);

        if(useDomPdf()){
            $contxt = stream_context_create([
                'ssl' => [
                    'verify_peer' => FALSE,
                    'verify_peer_name' => FALSE,
                    'allow_self_signed'=> TRUE
                ]
            ]);


            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $dompdf->setHttpContext($contxt);
            $path = realpath('./');
            $dompdf->getOptions()->setChroot($path);

            $dompdf->loadHtml($html);

            $row= $certificateTable->getRecord($id);
            $orientation = ($row->orientation=='p')?'portrait':'landscape';
            $dompdf->setPaper('A4', $orientation);

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream(safeUrl($row->name).'.pdf');

        }
        else{
            $row= $certificateTable->getRecord($id);
            $fileName  = safeUrl($row->name).'.pdf';
            $orientation = ($row->orientation=='p')?'portrait':'landscape';

            $pdf = App::make('snappy.pdf.wrapper');
            $pdf->loadHTML($html)->setPaper('a4')->setOrientation($orientation)->setOption('margin-bottom', 0)->setOption('margin-top', 0)->setOption('margin-right', 0)->setOption('margin-left', 0)->setOption('page-width',162)->setOption('page-height',230)->setOption('disable-smart-shrinking',true);
            return $pdf->download($fileName);
        }

    }

    public function downloadcertificateOld(Request $request,$id){
        $certificateTable = new CertificateTable();

        if(!$this->canAccessCertificate($id)){
            flashMessage(__lang('not-met-requirements'));
          return redirect()->route('student.student.certificates');
        }

        if(!$this->canDownload($id)){
            flashMessage(__lang('exceeded-max-downloads'));
            return redirect()->route('application/certificates');
        }



        $html = $this->getCertificateHtml($id);


        $contxt = stream_context_create([
            'ssl' => [
                'verify_peer' => FALSE,
                'verify_peer_name' => FALSE,
                'allow_self_signed'=> TRUE
            ]
        ]);


        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setHttpContext($contxt);
        $path = realpath('./');
        $dompdf->getOptions()->setChroot($path);

        $dompdf->loadHtml($html);

        $row= $certificateTable->getRecord($id);
        $orientation = ($row->orientation=='p')?'portrait':'landscape';
        $dompdf->setPaper('A4', $orientation);

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream(safeUrl($row->name).'.pdf');


        exit();

    }



    public function getCertificateHtml($id){
        $certificateTable = new CertificateTable();
        $sessionLessonTable = new SessionLessonTable();
        $attendanceTable = new AttendanceTable();
        $studentCertificateTable= new StudentCertificateTable();
        //add student record

        $sessionTable = new SessionTable();
        $row = $certificateTable->getRecord($id);
        $sessionRow = $sessionTable->getRecord($row->course_id);
        $student = $this->getStudent();

        $studentCertificate = $studentCertificateTable->addStudentEntry($this->getId(),$id);
        $name = $student->user->name.' '.$student->user->last_name;
        $elements = [
            'student_name'=>$name,
            'session_name'=>$sessionRow->name,
            'session_start_date'=>showDate('d/M/Y',$sessionRow->start_date),
            'session_end_date'=>showDate('d/M/Y',$sessionRow->end_date),
            'date_generated'=>date('d/M/Y'),
            'company_name'=> $this->getSetting('general_site_name'),
            'certificate_number' => $studentCertificate->tracking_number
        ];
        //get lessons for session
        $lessons = $sessionLessonTable->getSessionRecords($row->course_id);
        $fields = StudentField::get();


        foreach($lessons as $lesson){

            if(!empty($row->any_session)){
                $date = $attendanceTable->getStudentLessonDate($this->getId(),$lesson->lesson_id);
            }
            else{
                $date = $attendanceTable->getStudentLessonDateInSession($this->getId(),$lesson->lesson_id,$row->course_id);
            }
            if(empty($date)){
                $date = 'N/A';
            }
            $elements['class_date_'.$lesson->lesson_id.'_'.strtoupper(safeUrl($lesson->name))]=$date;
        }

        $studentFieldTable = new StudentFieldTable();

        foreach ($fields as $field){
            $fieldValue = '';
            $fieldValueRow = $studentFieldTable->getStudentFieldRecord($this->getId(),$field->id);
            if($fieldValueRow){
                $fieldValue = $fieldValueRow->value;
            }
            $elements['student_field_'.$field->id.'_'.strtoupper(safeUrl($field->name))]=$fieldValue;
        }


        $html = $row->html;



        foreach($elements as $key=>$value){
            $key = strtoupper($key);
            $html = str_replace("[$key]",$value,$html);
        }

        $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';


        //
        if($_SERVER['SERVER_PORT'] != '443'){
            $html = str_ireplace('https://','http://',$html);
        }
        else{
            $html = str_ireplace('http://','https://',$html);
        }


        //remove base path
        // $path = url('/');
       // $html = str_replace($path.'/','',$html);

        $dom = new \DOMDocument();
        //$dom->loadHTML($html);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        if($row->orientation == 'p'){
            $new_elm = $dom->createElement('style', ' @page { margin: 0; }
        @page { size: 597px 842px; page-break-inside: avoid; } ');
        }else{
            $new_elm = $dom->createElement('style', ' @page { margin: 0; }
        @page { size: 842px 597px; page-break-inside: avoid;} ');

        }


        $new_elm->setAttribute('type', 'text/css');

// Inject the new <style> Tag in the document head
        $head = $dom->getElementsByTagName('head')->item(0);
        $head->appendChild($new_elm);

        $html = $dom->saveHTML();

        $html = $this->replaceImageUrls($html);

 /*       exit($html);
        $basePath =  url('/');
        $path = realpath('./');

        $html = str_ireplace($basePath,$path,$html);*/


        return $html;
    }

    private function replaceImageUrls($html){
        $path = realpath('./');

        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $dom->preserveWhiteSpace = false;
        $images = $dom->getElementsByTagName('img');

        foreach ($images as $image)
        {
            $imageUrl= $image->getAttribute('src');
            $info = parse_url($imageUrl);
            $imagePath = $info['path'];

            $pos = strpos($imagePath,'/usermedia');
            $imagePath = substr($imagePath,$pos);
            $newUrl = $path.$imagePath;
            $html = str_ireplace($imageUrl,$newUrl,$html);

        }

        return $html;
    }


    public function canAccessCertificate($certificateid){

        $certificate = Certificate::findOrFail($certificateid);
        $certificateTable = new CertificateTable();
        $certificateLessonTable = new CertificateLessonTable();
        $certificateTestTable = new CertificateTestTable();
    //    $certificateAssignmentTable = new CertificateAssignmentTable();
        $studentSessionTable = new StudentSessionTable();
        $attendanceTable = new AttendanceTable();
        $studentTestTable = new StudentTestTable();
        $studentAssignmentTable = new AssignmentSubmissionTable();

        $certificateRow = $certificateTable->getRecord($certificateid);
        $studentId= $this->getId();
        //check that certificate is active
        if(empty($certificateRow->enabled)){
            return false;
        }

        //check that student is enrolled in session
        if(!$studentSessionTable->enrolled($this->getId(),$certificateRow->course_id)){
            flashMessage(__lang('certificate-download-error'));
           return false;
        }

        //check for attendance
        if($certificateLessonTable->getTotalForCertificate($certificateid)>0){

            $mClasses = [];
            $rowset = $certificateLessonTable->getCertificateLessons($certificateid);
            foreach($rowset as $row){
                $mClasses[] = $row->lesson_id;
            }

            if(empty($certificateRow->any_session)){
                $status = $attendanceTable->hasClassesInSession($studentId,$certificateRow->course_id,$mClasses);
            }
            else{
                $status = $attendanceTable->hasClasses($studentId,$mClasses);
            }

            if(!$status){
                flashMessage(__lang('outstanding-classes'));
                return false;
            }


        }

        if($certificateTestTable->getTotalForCertificate($certificateid)>0){
            $rowset = $certificateTestTable->getCertificateRecords($certificateid);
            foreach($rowset as $row)
            {
                $passedTest = $studentTestTable->passedTest($studentId,$row->test_id);
                if(!$passedTest){
                    $testRecord = Test::find($row->test_id);
                    flashMessage(__lang('need-take-test',['test'=>$testRecord->name]));
                    return false;
                }
            }


        }


            if($certificate->assignments()->count() > 0){
           //     $rowset = $certificateAssignmentTable->getCertificateRecords($certificateid);
                foreach($certificate->assignments as $row)
                {
                    $passedAssignment = $studentAssignmentTable->passedAssignment($studentId,$row->id);
                    if(!$passedAssignment){
                        $assignmentRecord = Assignment::find($row->id);
                        flashMessage(__lang('assignment-needed',['assignment'=>$assignmentRecord->title]));
                        return false;
                    }
                }


            }


        return true;

    }

    public function canDownload($certificateid){
        $certificateTable = new CertificateTable();


        $certificateRow = $certificateTable->getRecord($certificateid);
        $studentId= $this->getId();
        $student  = Student::find($studentId);

        $totalAllowed = $certificateRow->max_downloads;
        $totalDownloaded = $student->studentCertificateDownloads()->count();

        if($totalDownloaded >= $totalAllowed && $totalAllowed > 0){
            return false;
        }
        else{
            return true;
        }


    }

	public function getStudent()
	{
		$student = Auth::user()->student;
		return $student;
	}



    public function getId(){
        $row = $this->getStudent();
        return $row->id;
    }



    public function invoices(Request $request){
        $id = Auth::user()->id;
        $invoices= Invoice::where('user_id',$id)->orderBy('id','desc')->paginate(20);
        $this->data['pageTitle'] = __lang('My Invoices');
        $this->data['paginator'] = $invoices;
        return view('student.student.invoices',$this->data);
    }

    public function payinvoice(Request $request,$id){

        $invoice = Invoice::find($id);
        if($invoice && $invoice->user_id==Auth::id()){
            $cart =  unserialize($invoice->cart);
            $cart->store();
            return redirect()->route('cart');
        }
    }

    public function surveys(Request $request)
    {
        // TODO Auto-generated NewsController::index(Request $request) default action
        $table = new SurveyTable();
        $testQuestionTable = new SurveyQuestionTable();
        $studentTestTable = new SurveyResponseTable();

        $paginator = $table->getStudentRecords($this->getId());

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('student',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Surveys'),
            'studentTest'=>$studentTestTable,
            'questionTable'=>$testQuestionTable
        ));

    }

    public function billing(){
        $user = Auth::user();
        $countries  = Country::get();
        $pageTitle = __lang('billing-address');
        return view('student.student.billing',compact('user','countries','pageTitle'));
    }

    public function saveBilling(Request $request){
        $data = $request->all();
        $user = Auth::user();
        $user->fill($data);
        $user->save();
        flashMessage(__lang('changes-saved'));
        return back();
    }

}
