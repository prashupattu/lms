<?php
/**
 * Created by PhpStorm.
 * User: USER PC
 * Date: 10/27/2017
 * Time: 7:05 PM
 */

namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Lib\HelperTrait;
use App\Lib\SaasTrait;
use App\Student;
use App\V2\Form\DiscussionForm;
use Illuminate\Http\Request;


use App\Lecture;
use App\Lesson;
use App\Course;
use App\LecturePage;
use App\Video;
use App\V2\Model\AccountsTable;
use App\V2\Model\AssignmentSubmissionTable;
use App\V2\Model\AssignmentTable;
use App\V2\Model\AttendanceTable;
use App\V2\Model\BookmarkTable;
use App\V2\Model\DiscussionAccountTable;
use App\V2\Model\DiscussionTable;
use App\V2\Model\DownloadFileTable;
use App\V2\Model\DownloadSessionTable;
use App\V2\Model\LectureFileTable;
use App\V2\Model\LecturePageTable;
use App\V2\Model\LectureTable;
use App\V2\Model\LessonFileTable;
use App\V2\Model\LessonTable;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionLessonTable;
use App\V2\Model\SessionTable;
use App\V2\Model\StudentLectureTable;
use App\V2\Model\StudentSessionLogTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\StudentVideoTable;
use App\V2\Model\TestTable;
use Illuminate\Support\Carbon;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;

class CourseController extends Controller {

    use HelperTrait;
    use SaasTrait;
    private $videoDir = 'uservideo';
    private $videoPath = 'uservideo';
    private $module = 'student';

    public function __construct()
    {
        if(!defined('MODULE')){
            define('MODULE','student');
        }


    }

    public function validateEnrollment($sessionId){

        $studentSessionTable = new StudentSessionTable();
        if(!$studentSessionTable->enrolled($this->getId(),$sessionId)){
            flashMessage(__lang('you-not-enrolled'));
           redirect_now(back()->getTargetUrl());
          //  return redirect()->route('courses');
            exit();
            // return back();
        }

        $sessionTable = new SessionTable();
        $row = $sessionTable->getRecord($sessionId);
        if(!empty($row->enable_chat)){
            //get the live chat code from settings an place in constant here
            define('ENABLE_CHAT',true);
        }
    }

    public function intro(Request $request,$id){

        $this->validateEnrollment($id);
        $sessionTable = new SessionTable();
        $sessionLessonTable = new SessionLessonTable();
        $lectureTable = new LectureTable();
        $row = $sessionTable->getRecord($id);

        $downloadSessionTable = new DownloadSessionTable();
        $downloads = $downloadSessionTable->getSessionRecords($id);
        $instructorsTable = new SessionInstructorTable();

        //get class list
        $classes = $sessionLessonTable->getSessionRecords($id,'c');

        $classes->buffer();

        //get discussions for course
        $discussionTable = new DiscussionTable();
        $discussionForm = new DiscussionForm(null,$this->getId());
        $discussions = $discussionTable->getPaginatedRecordsForStudent(false,$this->getId(),$id);

        $instructors = $instructorsTable->getSessionRecords($id);
        $discussionForm->remove('admin_id[]');
        $options = [];
        $options['admins'] = __lang('Administrators');
        foreach($instructors as $row2){

            $options[$row2->admin_id]= $row2->name.' '.$row2->last_name;


        }


        $discussionForm->createSelect('admin_id[]','Recipients (Admins/Instructors)',$options,true);
        $discussionForm->get('admin_id[]')->setAttribute('multiple','multiple');
        $discussionForm->get('admin_id[]')->setAttribute('class','form-control select2');

        //get first class
        $firstClass = $classes->current();
        if($firstClass){
            $classLink =  route(MODULE.'.course.class',['lesson'=>$firstClass->lesson_id,'course'=>$firstClass->course_id]);
            if($row->type != 'c'){
                return redirect($classLink);
            }
        }
        else{
            $classLink='#';
        }



        //calculate progress of attendance
        $attendanceTable = new AttendanceTable();
        $totalClasses = $sessionLessonTable->getSessionRecords($id,'c')->count();
        //get tottal attended
        $attended = $attendanceTable->getTotalDistinctForStudentInSession($this->getId(),$id);

        if(!empty($totalClasses)){
            $percentage= ($attended/$totalClasses) * 100;
        }
        else{
            $percentage=0;
        }


        $attendanceRecords = $attendanceTable->getAttendedRecords($this->getId(),$id);


        $output= [
            'pageTitle'=>$row->name.': '.__lang('Introduction'),
            'classes'=>$classes,
            'lectureTable'=>$lectureTable,
            'sessionRow'=>$row,
            'downloads'=>$downloads,
            'fileTable'=> new DownloadFileTable(),
            'discussions'=> $discussions,
            'form' => $discussionForm,
            'classLink' => $classLink,
            'instructors'=>$instructors,
            'accountTable'=>new DiscussionAccountTable(),
            'percentage'=>$percentage,
            'attendanceTable'=>$attendanceTable,
            'sessionLogTable'=> new StudentSessionLogTable(),
            'studentId'=> $this->getId(),
            'sessionId'=>$id,
            'attendanceRecords'=>$attendanceRecords,
            'totalClasses'=>$totalClasses
        ];

        $output['customCrumbs'] = [
            route('student.dashboard') =>__lang('Home'),
            route('student.student.mysessions')=>__lang('my-courses'),
            route('student.course-details',['id'=>$row->id,'slug'=>safeUrl($row->name)])=>__lang('Course Details'),
             '#'=>__lang('Introduction')
        ];


        //get forum topics
        $bladeData['lecture'] = null;
        $bladeData['topics'] = Course::find($id)->forumTopics()->orderBy('id','desc')->paginate(50);
        $bladeData['id'] = $id;
        $bladeData['target'] = '_blank';
        $bladeData['pageTitle'] = __lang('topics');

        $output['forumTopics'] = view('student.forum.topics-content',$bladeData)->toHtml();
        return view('student.course.intro',$output);
    }

    public function class(Request $request,Lesson $lesson,Course $course){

        $classId = $lesson->id;
        $sessionId = $course->id;

        $this->verifyClass($classId,$sessionId);

        $lessonTable = new LessonTable();
        $lesson = $lessonTable->getRecord($classId);

        //check if the enforce order flag is selected


        //check if the class is opened yet



        //get list of lectures
        $lectureTable = new LectureTable();
        $lectures = $lectureTable->getRecordsOrdered($classId);
        $lectures->buffer();
        $lecturePageTable = new LecturePageTable();
        $downloadsTable = new LessonFileTable();
        $downloads = $downloadsTable->getDownloadRecords($classId);
        $downloads->buffer();

        $sessionLessonTable = new SessionLessonTable();

        //get previous class link
        $previousClass = $sessionLessonTable->getPreviousLessonInSession($sessionId,$classId,'c');
        if($previousClass){
            $previous = route(MODULE.'.course.class',['lesson'=>$previousClass->lesson_id,'course'=>$sessionId]);

        }
        else{
            $previous = false;
        }

        if($lectureTable->getTotalLectures($classId)>0){
            $nextRow = $lectures->current();
            $next = route(MODULE.'.course.lecture',['lecture'=>$nextRow->id,'course'=>$sessionId]);

        }
        else{
            $next = false;
        }

        $output= [
            'pageTitle'=>__lang('Class').' : '.$lesson->name,
            'lectures'=>$lectures,
            'lectureTable'=>$lectureTable,
            'classRow'=>$lesson,
            'downloads'=>$downloads,
            'fileTable'=> $downloadsTable,
            'previous'=>$previous,
            'next'=>$next,
            'lecturePageTable'=>$lecturePageTable,
            'sessionId'=>$sessionId,
            'course'=>$course
        ];

        $output['customCrumbs'] = [
            route('student.dashboard') =>__lang('Home'),
            route('student.student.mysessions')=>__lang('Online Courses'),
            route('student.course-details',['id'=>$sessionId,'slug'=>safeUrl($this->getSession($sessionId)->name)])=>__lang('Course Details'),
            route(MODULE.'.course.intro', ['id'=>$sessionId])=>__lang('Introduction'),
            '#'=>__lang('Class Details')
        ];
        return view('student.course.class',$output);
    }


    public function lecture(Request $request,Lecture $lecture,Course $course){


        $lectureId = $lecture->id;
        $sessionId = $course->id;
        $this->checkLectureOrder($lectureId,$sessionId);

        $sessionLessonTable = new SessionLessonTable();
        $lectureTable = new LectureTable();
        $studentLectureTable = new StudentLectureTable();
        $lecture = $lectureTable->getRecord($lectureId);
        $this->verifyClass($lecture->lesson_id,$sessionId);
        if(MODULE=='student'){
            $studentLectureTable->logLecture($this->getId(),$sessionId,$lectureId);
        }


        $lecturePageTable = new LecturePageTable();
        $pages = $lecturePageTable->getRecordsOrdered($lectureId);
        $pages->buffer();

        $fileTable = new LectureFileTable();
        $downloads = $fileTable->getDownloadRecords($lectureId);
        $downloads->buffer();
        //get previous and next links
        $previous = $lectureTable->getPreviousLecture($lectureId);
        $next = $lectureTable->getNextLecture($lectureId);
        $previousLesson = $sessionLessonTable->getPreviousLessonInSession($sessionId,$lecture->lesson_id,'c');


        $sessionInstructorTable = new SessionInstructorTable();
        $form = new DiscussionForm(null,$this->getId());
        $instructors = $sessionInstructorTable->getSessionRecords($sessionId);

        $form->remove('admin_id[]');
        $options = [];
        $options['admins'] = __lang('Administrators');
        foreach($instructors as $row){
                $options[$row->admin_id]= $row->name.' '.$row->last_name;
         }


        $form->createSelect('admin_id[]',__lang('Recipients (Admins/Instructors)'),$options,true);
        $form->get('admin_id[]')->setAttribute('multiple','multiple');
        $form->get('admin_id[]')->setAttribute('class','form-control select2');


        $discussionTable = new DiscussionTable();
        $discussions = $discussionTable->getPaginatedRecordsForStudent(false,$this->getId(),$sessionId,$lectureId);

        //get all lectures for this lecture's class
        $lectures = $lectureTable->getRecordsOrdered($lecture->lesson_id);

        $sessionEntity = Course::find($sessionId);
        $output = [
          'pageTitle'=>__lang('Class').': '.$lecture->name,
            'pages'=>$pages,
            'lecture'=>$lecture,
            'downloads'=>$downloads,
            'previous'=>$previous,
            'next'=>$next,
            'form'=>$form,
            'instructors'=>$instructors,
            'discussions'=>$discussions,
            'sessionRow'=>$this->getSession($sessionId),
            'fileTable'=>$fileTable,
            'totalPages'=>$pages->count(),
            'sessionId'=>$sessionId,
            'accountTable'=>new DiscussionAccountTable(),
            'pageTable'=> $lecturePageTable,
            'previousLesson'=>$previousLesson,
            'lecturePageTable'=>$lecturePageTable,
            'lectures'=>$lectures,
            'session'=>$sessionEntity,
            'module'=>MODULE,
            'course'=>$course
        ];

        $output['customCrumbs'] = [
            route('student.dashboard') =>__lang('Home'),
            route('student.student.mysessions')=>__lang('Online Courses'),
            route('student.course-details',['id'=>$sessionId,'slug'=>safeUrl($this->getSession($sessionId)->name)])=>__lang('Course Details'),
            route(MODULE.'.course.intro', ['id'=>$sessionId])=>__lang('Introduction'),
            route(MODULE.'.course.class',['lesson'=>$lecture->lesson_id,'course'=>$sessionId])=>__lang('Class Details'),
            '#'=>__lang('Lecture')
        ];

        $bladeData['lecture'] = null;
        $bladeData['topics'] = Course::find($sessionId)->forumTopics()->orderBy('id','desc')->paginate(50);
        $bladeData['id'] = $sessionId;
        $bladeData['target'] = '_blank';
        $output['forumTopics'] = view('student.forum.topics-content',$bladeData);



        //check if there is a video in content
        $lecture= Lecture::find($lectureId);
        foreach ($lecture->lecturePages as $page){
            if($page->type =='l'){
                $videoId = intval($page->content);
                $video = Video::find($videoId);
                if ($video){
                    $student = $this->getStudent();
                    $studentVideoTable = new StudentVideoTable();
                    $studentVideoTable->addVideoForStudent($student->id,$videoId);
                }
                else{
                    continue;
                }

                if(saas()  && defined('USER_ID')){
                    noSSL();
                    $s3Path = USER_ID.'/'.$video->id.'/playlist.m3u8';
                    try {

                        $s3Config = [
                            'region'=>env('AWS_DEFAULT_REGION'),
                            'bucket'=>env('AWS_BUCKET'),
                            'key'=>env('AWS_ACCESS_KEY_ID'),
                            'secret'=>env('AWS_SECRET_ACCESS_KEY'),
                            'cloudfront_domain'=> env('AWS_CLOUDFRONT_DOMAIN'),
                            'cloudfront_key_pair_id'=>env('AWS_CLOUDFRONT_KEY_PAIR')
                        ];
                        $expires = time() + (86400 * 4);
                        $cloudFront = $this->getCloudFrontClient();

                        if(!isTrainEasySubdomain() && defined('BASE_DOMAIN_NAME') && !empty(BASE_DOMAIN_NAME)){
                            $s3Config['cloudfront_domain'] = 'vcdn.'.trim(BASE_DOMAIN_NAME);
                            $cookie= BASE_DOMAIN_NAME;
                        }
                        else{
                            $cookie = env('APP_SAAS_DOMAIN');
                        }

                        $resourceKey = 'http://' . $s3Config['cloudfront_domain'] . '/' . USER_ID . '/' . $video->id . '/*';

                        //$resourceKey = 'http://vcdn.traineasy.net/*';

                        $customPolicy = '{"Statement":[{"Resource":"' . $resourceKey . '","Condition":{"DateLessThan":{"AWS:EpochTime":' . $expires . '}}}]}';

                        $signedCookieCannedPolicy = $cloudFront->getSignedCookie([
                            'policy' => $customPolicy,
                            'expires' => $expires,
                            'key_pair_id' => $s3Config['cloudfront_key_pair_id'],
                            'private_key' => '../config/pk-APKAISJFXUOFGEQFNX5A.pem'
                        ]);

                        foreach ($signedCookieCannedPolicy as $name => $value) {
                            $output['cookie'][] = ['name' => $name, 'value' => $value];
                            //  setrawcookie($name, $value, time() + (86400 * 30), "/",'traineasy.net', false, false); // 86400 = 1 day
                            setrawcookie($name, $value, 0, "/", $cookie, false, false);


                        }



                        header('Access-Control-Allow-Origin: *');
                        header('Access-Control-Allow-Credentials: true');
                        $output['videoUrl'] = 'http://' . $s3Config['cloudfront_domain'] . '/' . $s3Path;

                        $detect = new \Mobile_Detect();
                        if($detect->isMobile() ){

                            //get student apitoken
                            $student = $this->getStudent();

                            //give student access to video

                            $token = $student->api_token;
                            if(empty($token)){
                                do{
                                    $token = bin2hex(random_bytes(16));
                                }while(!Student::where('api_token',$token));

                                $studentEntity = Student::find($student->id);
                                $studentEntity->api_token = $token;
                                $studentEntity->save();
                            }
                            $uri = $this->getBaseUrl().'/api/v1/videos/'.$videoId.'/index.m3u8?api_token='.$token;
                            $output['videoUrl'] = $uri;
                            $output['isMobile'] = true;
                        }


                    }
                    catch (\Exception $ex){

                    }


                }


            }
        }

        //check if there is a zoom meeting in the content
        $zoom = false;
        foreach ($lecture->lecturePages as $page){
            if($page->type =='z'){
                $zoom = true;
            }
        }

        if(!session()->has('flash_message') && $request->has('order_prompt')){
                $output['flash_message'] = __lang('complete-right-order');
        }

        $output['module']= MODULE;
        $output['append'] = '';
        $output['zoom'] = $zoom;
        $viewModel = viewModel('student',__CLASS__,__FUNCTION__,$output);

        return $viewModel;

    }

    public function zoomInit(Request $request, LecturePage $lecturePage){

        //get zoom data
        $zoomData = @unserialize($lecturePage->content);
        if(!$zoomData || !is_array($zoomData)){
                return response()->json([
                    'status'=>false
                ]);
        }

        //get credentials
        $meetingId = $zoomData['meeting_id'];
        $password = $zoomData['password'];
        $apiKey = setting('zoom_key');
        $apiSecret = setting('zoom_secret');
        $role = 0;


    }



    public function bookmark(Request $request){

        if(request()->isMethod('post')){

            $lecturePageId = $request->post('id');
            $sessionId = $request->post('course_id');
            $lecturePageTable = new LecturePageTable();
            $lectureTable = new LectureTable();
            $pageRow = $lecturePageTable->getRecord($lecturePageId);
            $lectureRow = $lectureTable->getRecord($pageRow->lecture_id);
            $this->verifyClass($lectureRow->lesson_id,$sessionId);

            $bookMarkTable = new BookmarkTable();

            if($bookMarkTable->addBookMark($this->getId(),$lecturePageId,$sessionId)){
                $status = true;
                $message= __lang('bookmark-added');
            }
            else{
                $status = false;
                $message = __lang('bookmark-already-exists');
            }
            exit(json_encode(array('status'=>$status,'message'=>$message)));
        }
        return back();
    }

    private function checkOrder($classId,$sessionId){
        $session = $this->getSession($sessionId);
        $sessionLessonTable = new SessionLessonTable();
        $lesson = $sessionLessonTable->getSessionLessonRecord($sessionId,$classId);
        if(!empty($session->enforce_order) && $lesson->sort_order > 1){

            $attendanceTable = new AttendanceTable();
            if($attendanceTable->hasAttendance($this->getId(),$classId,$sessionId)){
                return true;
            }
                //get previous class
            $previousClass = $sessionLessonTable->getPreviousLessonInSession($sessionId,$classId);
            if(!$attendanceTable->hasAttendance($this->getId(),$previousClass->lesson_id,$sessionId)){
                //get the last class student attended
                $lessons = $sessionLessonTable->getSessionRecords($sessionId);

                //getStudentLog

                $nextLesson = null;
                foreach($lessons as $lesson){

                    if(!$attendanceTable->hasAttendance($this->getId(),$lesson->lesson_id,$sessionId)){

                        $nextLesson = $lesson->lesson_id;
                        break;
                    }

                }//end lessons loop
                flashMessage(__lang('complete-right-order'));

                if($nextLesson){
                    return redirect()->route(MODULE.'.course.class',['course'=>$sessionId,'lesson'=>$nextLesson]);
                }
                else{
                    return back();
                }
            }

        }

    }

    private function checkLectureOrder($lectureId,$sessionId){

        $lectureTable= new LectureTable();
        $sessionLogTable = new StudentSessionLogTable();
        $lectureRow = $lectureTable->getRecord($lectureId);
        $lessonTable = new LessonTable();
        $classId = $lectureRow->lesson_id;
        $lesson = $lessonTable->getRecord($classId);
        if(!empty($lesson->enforce_lecture_order) && $lectureRow->sort_order > 1){

            if($sessionLogTable->hasAttendance($this->getId(),$sessionId,$lectureId)){
                return true;
            }
            //get previous class
            $previousLecture = $lectureTable->getPreviousLecture($lectureId);
            if($previousLecture && !$sessionLogTable->hasAttendance($this->getId(),$sessionId,$previousLecture->id)){
                //get the last lecture student attended
                $lectures = $lectureTable->getRecordsOrdered($classId);
                //getStudentLog
                $nextLecture = null;
                foreach($lectures as $lecture){

                    if(!$sessionLogTable->hasAttendance($this->getId(),$sessionId,$lecture->id)){

                        $nextLecture = $lecture->id;
                        break;
                    }

                }//end lessons loop
                flashMessage(__lang('complete-right-order'));


                if($nextLecture){
                    redirect_now(route(MODULE.'.course.lecture',['course'=>$sessionId,'lecture'=>$nextLecture]).'?order_prompt=true&');
                }
                else{
                      redirect_now(back()->getTargetUrl().'?order_prompt=true&');
                }
            }

        }

    }

    private function getSession($id){
        return Course::find($id);
    }

    public function verifyClass($id,$session,$abort=true){

      //  $sessionContainer = new Container('course');
        $sessionLessonTable = new SessionLessonTable();
        $this->validateEnrollment($session);
        $this->checkOrder($id,$session);

        //check previous class and see if there is any outstanding assignment
        $previousClass = $sessionLessonTable->getPreviousLessonInSession($session,$id,'c');
        if($previousClass){

            //get assignments
            $assignementTable = new AssignmentTable();
            $assignments = $assignementTable->getSessionLessonAssignments($session,$previousClass->lesson_id);
            $assignments->buffer();
            $assignmentSubmissionTable = new AssignmentSubmissionTable();
            $total = $assignments->count();
            $firstAssignment = false;
            if(!empty($total)){
                //assignments exist
          //      $sessionContainer->url = selfURL();
                   session(['course-url',selfURL()]);
                //loop through assignments and verify student has submission for each
                foreach($assignments as $assignment){


                    if(!$assignmentSubmissionTable->hasSubmission($this->getId(),$assignment->id)){
                        if($firstAssignment==false){
                            $firstAssignment = $assignment;
                        }

                        $subject = __lang('You have Homework for').' '.$previousClass->name;

                        $message = __lang('homework-notification-mail',['homework'=>$assignment->title,'class'=>$previousClass->name,'link'=>route('student.assignment.submit',['id'=>$assignment->id])]);

                        $this->notifyStudent($this->getId(),$subject,$message);

                    }
                }

                if($firstAssignment){
                    //redirect to assignment page
                    flashMessage(__lang('homework-reminder-flash',['title'=>$firstAssignment->title,'class'=>$previousClass->name]));

                    redirect_now(route('student.assignment.submit',['id'=>$firstAssignment->id]));

                }


            }

        }

        //check that class is opened
        $lessonRow = $sessionLessonTable->getSessionLessonRecord($session,$id);
        if(!empty($lessonRow->lesson_date) && Carbon::parse($lessonRow->lesson_date)->timestamp > time()){
      /*      flashMessage('The class "'.$lessonRow->name.'" is scheduled to start on '.date('d/M/Y',$lessonRow->lesson_date));
            return redirect()->route('course-details',['id'=>$session]);*/

            $lesson = Lesson::find($lessonRow->lesson_id);
            flashMessage(__lang('class-starts-on',['class'=>$lesson->name,'date'=>showDate('d/M/Y',$lessonRow->lesson_date)]));
            // return redirect()->route('course-details',['id'=>$session]);
            redirect_now(route(MODULE.'.course.intro',['id'=>$session]));

        }


        if($sessionLessonTable->lessonExists($session,$id)){
            return true;
        }
        else{
            if($abort){
                exit('Invalid record');
            }
            else{
                return false;
            }
        }

    }


    /**
     * This action is for logging a lectures attendance
     */
    public function loglecture(Request $request){

        $sessionLogTable = new StudentSessionLogTable();
        $studentTestTable = new StudentTestTable();
        $sessionLessonTable = new SessionLessonTable();
        $testTable = new TestTable();
        $studentLectureTable = new StudentLectureTable();
        //$container = new Container('classTest');
        if(request()->isMethod('post'))
        {

            $lecture = $request->post('lecture_id');
            $session = $request->post('course_id');


            $lectureTable = new LectureTable();
            $lectureRow = $lectureTable->getRecord($lecture);
            $lessonTable = new LessonTable();
            $this->verifyClass($lectureRow->lesson_id,$session);

            $logId = $sessionLogTable->addRecord([
                'student_id'=>$this->getId(),
                'course_id'=>$session,
                'lecture_id'=>$lecture,
            ]);

            //check if there is another lecture and redirect to it
            $next = $lectureTable->getNextLecture($lecture);
            if($next){
                return redirect()->route(MODULE.'.course.lecture',['lecture'=>$next->id,'course'=>$session]);
            }
            else{
                //check for outstanding lectures
                $allLectures = $lectureTable->getLectureRecords($lectureRow->lesson_id);
                foreach($allLectures as $row){

                    if(!$sessionLogTable->hasAttendance($this->getId(),$session,$row->id)){
                        flashMessage(__lang('outstanding-lectures'));
                        return redirect()->route(MODULE.'.course.lecture',['lecture'=>$row->id,'course'=>$session]);
                    }

                }



                //check if class has test
                $lessonRow = $lessonTable->getRecord($lectureRow->lesson_id);
                if(!empty($lessonRow->test_required) && !empty($lessonRow->test_id) && $testTable->recordExists($lessonRow->test_id) && !$studentTestTable->passedTest($this->getId(),$lessonRow->test_id)){

                    $testInfo = serialize([$lessonRow->test_id => [
                        'lesson_id' => $lectureRow->lesson_id,
                        'course_id' => $session,
                        'name' => $lessonRow->name
                    ]]);

                     session(['testInfo'=>$testInfo]);


                   // flashMessage('You are required to take this test in order to complete the \''.$lessonRow->name.'\' class');
                    return redirect()->route('student.test.taketest',['id'=>$lessonRow->test_id]);
                }
                else{

                    //log attendance for this class
                    $attendanceTable = new AttendanceTable();
                    $attendanceTable->setAttendance([
                        'student_id'=>$this->getId(),
                        'course_id'=>$session,
                        'lesson_id'=>$lectureRow->lesson_id
                    ]);

                    //get next class
                    $nextClass = $sessionLessonTable->getNextLessonInSession($session,$lectureRow->lesson_id,'c');
                    if($nextClass){
                        //forward to the next class
                        return redirect()->route(MODULE.'.course.class',['course'=>$session,'lesson'=>$nextClass->lesson_id]);
                    }
                    else{
                        //classes are over
                        $studentLectureTable->clearLecture($this->getId(),$session);
                        $studentSessionTable = new StudentSessionTable();
                        $studentSessionTable->markCompleted($this->getId(),$session);
                        flashMessage(__lang('course-complete-msg'));
                        return redirect()->route('student.catalog.course',['id'=>$session]);
                    }

                }

            }

        }
        else{
            return back();
        }
    }




    public function classfile(Request $request,$id,Course $course){
        set_time_limit(86400);

        $sessionId = $course->id;
        $table = new LessonFileTable();
        $row = $table->getRecord($id);

        $this->verifyClass($row->lesson_id,$sessionId);

        $path = 'usermedia/'.$row->path;



        header('Content-type: '.getFileMimeType($path));

// It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.basename($path).'"');

// The PDF source is in original.pdf
        readfile($path);
        exit();
    }


    public function allclassfiles(Request $request,$id,Course $course){
        set_time_limit(86400);
        $sessionId = $course->id;
        $this->verifyClass($id,$sessionId);
        $downloadTable = new LessonTable();
        $downloadFileTable= new LessonFileTable();
        $rowset = $downloadFileTable->getDownloadRecords($id);
        $downloadRow = $downloadTable->getRecord($id);

        $zipname = safeUrl($downloadRow->name).'_resources.zip';
        $zip = new \ZipArchive;
        $zip->open($zipname, \ZipArchive::CREATE);
        $count = 1;
        $deleteFiles = [];
        foreach ($rowset as $row) {
            $path = 'usermedia/' . $row->path;

            if (file_exists($path)) {
                $newFile = $count.'-'.basename($path);
                copy($path,$newFile);
                $zip->addFile($newFile);
                $count++;
                $deleteFiles[] = $newFile;
            }



        }
        $zip->close();

        foreach($deleteFiles as $value){
            unlink($value);
        }

        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);
        unlink($zipname);
        exit();
    }

    public function lecturefile(Request $request,$id,Course $course){
        set_time_limit(86400);
        $sessionId = $course->id;
        $table = new LectureFileTable();
        $row = $table->getRecord($id);

        //get lecture table
        $lectureTable = new LectureTable();
        $lecture = $lectureTable->getRecord($row->lecture_id);

        $this->verifyClass($lecture->lesson_id,$sessionId);

        $path = 'usermedia/'.$row->path;



        header('Content-type: '.getFileMimeType($path));

// It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.basename($path).'"');

// The PDF source is in original.pdf
        readfile($path);
        exit();
    }


    public function alllecturefiles(Request $request,$id,Course $course){
        set_time_limit(86400);
        $sessionId = $course->id;

        $downloadTable = new LectureTable();
        $downloadFileTable= new LectureFileTable();
        $rowset = $downloadFileTable->getDownloadRecords($id);
        $downloadRow = $downloadTable->getRecord($id);
        $this->verifyClass($downloadRow->lesson_id,$sessionId);
        $zipname = safeUrl($downloadRow->title).'_resources.zip';
        $zip = new \ZipArchive;
        $zip->open($zipname, \ZipArchive::CREATE);
        $count = 1;
        $deleteFiles = [];
        foreach ($rowset as $row) {
            $path = 'usermedia/' . $row->path;

            if (file_exists($path)) {
                $newFile = $count.'-'.basename($path);
                copy($path,$newFile);
                $zip->addFile($newFile);
                $count++;
                $deleteFiles[] = $newFile;
            }



        }
        $zip->close();

        foreach($deleteFiles as $value){
            unlink($value);
        }

        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename='.$zipname);
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);
        unlink($zipname);
        exit();
    }

    public function bookmarks(Request $request){
        $studentId = $this->getId();
        $bookmarkTable = new BookmarkTable();
        $paginator = $bookmarkTable->getPaginatedStudentRecords(true,$studentId);
        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);

        $output = [
            'pageTitle'=>__lang('My Bookmarks'),
            'paginator'=>$paginator,
            'id'=>$studentId,
        ];
        return viewModel('student',__CLASS__,__FUNCTION__,$output);
    }

    public function serve(Request $request,$id){
        $video = Video::find($id);
        $studentVideoTable = new StudentVideoTable();
        $student = $this->getStudent();
        if(MODULE=='student'){
            if (!$studentVideoTable->hasVideo($student->id,$id)){
                exit(__lang('no-permission-view'));
            }
        }


        $path = $this->videoPath.'/'.$video->id.'/'.$video->file_name;

        if ($fp = fopen($path, "rb")) {
            $size = filesize($path);
            $type = mime_content_type($path);
            $length = $size;
            $start = 0;
            $end = $size - 1;
            header('Content-type: '.$type);
            header("Accept-Ranges: 0-$length");
            if (isset($_SERVER['HTTP_RANGE'])) {
                $c_start = $start;
                $c_end = $end;
                list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if (strpos($range, ',') !== false) {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    header("Content-Range: bytes $start-$end/$size");
                    exit;
                }
                if ($range == '-') {
                    $c_start = $size - substr($range, 1);
                } else {
                    $range = explode('-', $range);
                    $c_start = $range[0];
                    $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
                }
                $c_end = ($c_end > $end) ? $end : $c_end;
                if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    header("Content-Range: bytes $start-$end/$size");
                    exit;
                }
                $start = $c_start;
                $end = $c_end;
                $length = $end - $start + 1;
                fseek($fp, $start);
                header('HTTP/1.1 206 Partial Content');
            }
            header("Content-Range: bytes $start-$end/$size");
            header("Content-Length: ".$length);
            $buffer = 1024 * 8;
            while(!feof($fp) && ($p = ftell($fp)) <= $end) {
                if ($p + $buffer > $end) {
                    $buffer = $end - $p + 1;
                }
                set_time_limit(0);
                echo fread($fp, $buffer);
                flush();
            }
            fclose($fp);
            exit();
        } else {
            die('file not found');
        }

    }


}
