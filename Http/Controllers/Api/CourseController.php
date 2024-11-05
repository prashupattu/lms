<?php

namespace App\Http\Controllers\Api;

use App\Course;
use App\Download;
use App\Http\Controllers\Controller;
use App\Bookmark;
use App\Lecture;
use App\LectureFile;
use App\LecturePage;
use App\Lesson;
use App\Lib\HelperTrait;
use App\Lib\SaasTrait;
use App\Student;
use App\Video;
use App\V2\Model\AssignmentSubmissionTable;
use App\V2\Model\AssignmentTable;
use App\V2\Model\AttendanceTable;
use App\V2\Model\DiscussionTable;
use App\V2\Model\DownloadSessionTable;
use App\V2\Model\LectureFileTable;
use App\V2\Model\LecturePageTable;
use App\V2\Model\LectureTable;
use App\V2\Model\LessonFileTable;
use App\V2\Model\LessonTable;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionLessonAccountTable;
use App\V2\Model\SessionLessonTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SessionTestTable;
use App\V2\Model\StudentLectureTable;
use App\V2\Model\StudentSessionLogTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\StudentVideoTable;
use App\V2\Model\TestTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface as Response;

class CourseController extends Controller
{
    use HelperTrait;
    use SaasTrait;

    public function getSessions(Request $request){

        $params = $request->all();

        $table = new SessionTable();

        $filter = null;

        if (isset($params['filter']) && !empty($params['filter'])) {
            $filter=$params['filter'];
        }


        $group = null;
        if (isset($params['group']) && !empty($params['group'])) {
            $group=$params['group'];
        }

        $sort = null;
        if (isset($params['sort']) && !empty($params['sort'])) {
            $sort=$params['sort'];
        }


        $type = null;
        if(isset($params['type']) && !empty($params['type'])){

            $type = explode('-',$params['type']);
        }

        if(isset($params['rows']) && !empty($params['rows']) && $params['rows'] <= 100 ){
            $rowsPerPage = $params['rows'];
        }
        else{
            $rowsPerPage = 30;
        }

        $currentPage = (int) (empty($params['page'])? 1 : $params['page']);


        $paginator = $table->getPaginatedRecords(true,null,true,$filter,$group,$sort,$type,true);
        $paginator->setCurrentPageNumber($currentPage);
        $paginator->setItemCountPerPage($rowsPerPage);

        $total = $table->getTotalRecords(true,null,true,$filter,$group,$sort,$type,true);


        $output = [
            'current_page'=>(int) (empty($params['page'])? 1 : $params['page']),
            'rows_per_page'=> $rowsPerPage,
            'total' => $total
        ];
        if($total==0){
            $total = 1;
        }
        //check for exceeded page
        $totalPages = ceil($total/$rowsPerPage);
        if($currentPage > $totalPages){
            $output['records'] = [];
            return jsonResponse($output);
        }

        foreach($paginator as $row){
            $data = Course::find($row->id)->toArray();
            if(isset($params['currency_id'])){
                $data['fee'] = price($data['fee'],$params['currency_id'],true);
            }
            $data['session_id'] = $data['id'];
            $data['session_date'] = stamp($data['start_date']);
            $data['session_status'] = $data['enabled'];
            $data['enrollment_closes'] = stamp($data['enrollment_closes']);
            $data['session_name'] = $data['name'];
            $data['session_end_date'] = stamp($data['end_date']);
            $data['session_type'] = $data['type'];
            $data['amount'] =$data['fee'];

            $output['records'][]=$data;

        }

        return jsonResponse($output);
    }

    public function getCourses(Request $request){



        $params = $request->all();

        $table = new SessionTable();

        $filter = null;

        if (isset($params['filter'])) {
            $filter=$params['filter'];
        }

        $group = null;
        if (isset($params['group'])) {
            $group=$params['group'];
        }

        $sort = null;
        if (isset($sort)) {
            $sort=$params['sort'];
        }


        if(isset($params['rows'])){
            $rowsPerPage = $params['rows'];
        }
        else{
            $rowsPerPage = 30;
        }

        $paginator = $table->getPaginatedCourseRecords(true,null,true,$filter,$group,$sort);
        $paginator->setCurrentPageNumber((int) (empty($params['page'])? 1 : $params['page']));
        $paginator->setItemCountPerPage($rowsPerPage);
        $total = $table->getTotalCourseRecords(true,null,true,$filter,$group,$sort);

        $output = [
            'current_page'=>(int) (empty($params['page'])? 1 : $params['page']),
            'rows_per_page'=> $rowsPerPage,
            'total' => $total
        ];

        foreach($paginator as $row){
            $data = Course::find($row->id)->toArray();
            if(isset($params['currency_id'])){
                $data['fee'] = price($data['fee'],$params['currency_id'],true);
            }
            $data['session_id'] = $data['id'];
            $data['session_date'] = stamp($data['start_date']);
            $data['session_status'] = $data['enabled'];
            $data['enrollment_closes'] = stamp($data['enrollment_closes']);
            $data['session_name'] = $data['name'];
            $data['session_end_date'] = stamp($data['end_date']);
            $data['session_type'] = $data['type'];
            $data['amount'] = $data['fee'];
            $output['records'][]=$data;

        }

        return jsonResponse($output);
    }

    private function getResumeData($id){
        $studentSessionTable = new StudentSessionTable();
        $studentLectureTable = new StudentLectureTable();
        $resumeData = null;

        $student = $this->getApiStudent();
        if($student) {
            $studentId = $student->id;
            if ($studentSessionTable->enrolled($studentId, $id)) {

                //check if student has started lecture
                if($studentLectureTable->hasLecture($studentId,$id)){
                    $lecture = $studentLectureTable->getLecture($studentId,$id);
                    if($lecture->sort_order == 1){

                        $resumeData = [
                            'type'=>'class',
                            'class_id'=> $lecture->lesson_id,
                            'session_id' => $id
                        ];
                    }
                    else{
                        $resumeData = [
                            'type'=>'lecture',
                            'class_id'=> $lecture->lesson_id,
                            'lecture_id'=>$lecture->lecture_id,
                            'session_id'=>$id
                        ];
                    }
                    $resume = true;
                }
                else{

                    //  $resumeLink = $this->url()->fromRoute('application/default', ['controller' => 'course', 'action' => 'intro','id'=>$id]);
                    $resumeData = [
                        'type'=>'course',
                        'session_id'=> $id,

                    ];
                }

            }

        }
        return $resumeData;
    }

    public function getSession(Request $request,$id){


        $sessionTable = new SessionTable();
        $sessionLessonTable = new SessionLessonTable();
        $sessionLessonAccountTable = new SessionLessonAccountTable();
        $studentSessionTable = new StudentSessionTable();
        $sessionInstructorTable = new SessionInstructorTable();
        $studentLectureTable = new StudentLectureTable();
        $logTable = new StudentSessionLogTable();
        $enrolled = false;
        $resumeData = null;

        $student = $this->getApiStudent();

        $resume = false;
        if($student) {

            $studentId = $student->id;

            if ($studentSessionTable->enrolled($studentId, $id)) {

                $enrolled = true;
                //check if student has started lecture

                if($studentLectureTable->hasLecture($studentId,$id)){
                    $lecture = $studentLectureTable->getLecture($studentId,$id);
                    if ($lecture && $sessionLessonTable->lessonExists($id,$lecture->lesson_id)) {
                        if ($lecture->sort_order == 1) {

                            $resumeData = [
                                'type' => 'class',
                                'class_id' => $lecture->lesson_id,
                                'session_id' => $id
                            ];
                        } else {
                            $resumeData = [
                                'type' => 'lecture',
                                'class_id' => $lecture->lesson_id,
                                'lecture_id' => $lecture->lecture_id,
                                'session_id' => $id
                            ];
                        }
                        $resume = true;
                    }
                    else{
                        $resume = false;
                        $resumeData = [
                            'type'=>'course',
                            'session_id'=> $id,

                        ];
                    }
                }
                else{

                    $resumeData = [
                        'type'=>'course',
                        'session_id'=> $id,

                    ];
                }

            }

        }
        else{
            $studentId = 0;
        }

        $downloadSessionTable = new DownloadSessionTable();

        $row =  Course::find($id);
        $rowset = $sessionLessonTable->getSessionRecords($id);

        $classes = $rowset->toArray();


        foreach($classes as $key=>$value){
            $classes[$key]['lesson_date'] = (empty( $classes[$key]['lesson_date']))? '':date('d M Y',stamp($classes[$key]['lesson_date']));
        }

        //ensure it is an online course


        //get instructors
        $instructors = $sessionInstructorTable->getSessionRecords($id);

        //get downloads
        $downloads = $downloadSessionTable->getSessionRecords($id);

        //check if student has started course
        //get session tests
        $sessionTestTable  = new SessionTestTable();
        $tests = $sessionTestTable->getSessionRecords($id);


        //get details
        $classes = [];
        foreach($rowset as $value){
            $value->lesson_date = (empty($value->lesson_date))? '':date('d M Y',stamp($value->lesson_date));
            $value->id = $value->lesson_id;
            $classes[] = apiClass($value);
        }

        $downloadArray = [];

        foreach ($downloads as $download){
            $downloadArray[] = apiDownload($download);
        }

        $instructorArray=[];
        foreach ($instructors as $instructor){
            $instructorArray[] = apiInstructor($instructor);
        }

        $testArray = [];
        foreach($tests as $test){
            $testArray[] = apiTest($test);
        }
        $courseUrl = route('course',['course'=>$row->id,'slug'=>safeUrl($row->name)]);
        $output = [
            'details'=>apiCourse($row),
            'classes'=>$classes,
            'session_id'=>$id,
            'studentId'=>$studentId,
            'instructors' => $instructorArray,
            'downloads'=>$downloadArray,
            'resumeData'=>$resumeData,
            'enrolled'=>$enrolled,
            'tests'=>$testArray,
            'resume'=> $resume,
            'url'=>$courseUrl
        ];

        return jsonResponse($output);
    }

    public function getClass(Request $request,$id){
        //validate
        $params = $request->all();
        $valid = $this->validateGump($params,[
            'course_id'=>'required'
        ]);

        if(!$valid){
            return jsonResponse(['error'=>$this->getValidationErrorMsg(),'status'=>false]);
        }

        $classId = $id;
        $sessionId = $params['course_id'];

        $this->verifyClass($classId,$sessionId);

        $student = $this->getApiStudent();


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
            $previous = $previousClass->lesson_id;

        }
        else{
            $previous = false;
        }

        if($lectureTable->getTotalLectures($classId)>0){
            $nextRow = $lectures->current();
            $next = $nextRow->id;

        }
        else{
            $next = false;
        }

        $lectureArray= apiRowset($lectures,'apiLecture');

        foreach($lectureArray as $key=>$value){
            $lectureId = $lectureArray[$key]['id'];
            $contents= Lecture::find($lectureId)->lecturePages()->orderBy('sort_order')->get();

            $data = $contents->toArray();

            foreach($data as $key2=>$value2){
                $data[$key2]['lecture_page_id'] = $data[$key2]['id'];
                unset($data[$key2]['content']);
                unset($data[$key2]['audio_code']);
            }

            $lectureArray[$key]['contents'] = $data;

        }


        $output= [
            'class_name'=>$lesson->name,
            'lectures'=>$lectureArray,
            'class_details'=>apiClass($lesson),
            'downloads'=>apiRowset($downloads,'apiDownload'),
            'previous'=>$previous,
            'next'=>$next,
            'sessionId'=>$sessionId,
            'status'=>true

        ];


        return jsonResponse($output);

    }

    public function getLecture(Request $request,$id){

        $params = $request->all();

        $valid = $this->validateGump($params,[
            'course_id'=>'required'
        ]);

        if(!$valid){
            return jsonResponse(['error'=>$this->getValidationErrorMsg(),'status'=>false]);
        }

        $lectureId = $id;
        $sessionId = $params['course_id'];

        $this->checkLectureOrder($lectureId,$sessionId);

        $studentVideoTable = new StudentVideoTable();
        $sessionLessonTable = new SessionLessonTable();
        $lectureTable = new LectureTable();
        $studentLectureTable = new StudentLectureTable();
        $lecture = $lectureTable->getRecord($lectureId);
        $this->verifyClass($lecture->lesson_id,$sessionId);
        $studentLectureTable->logLecture($this->getApiStudent()->id,$sessionId,$lectureId);

        $lecturePageTable = new LecturePageTable();
        $pages = $lecturePageTable->getRecordsOrdered($lectureId);
        $pages->buffer();

        $fileTable = new LectureFileTable();
        $downloads = $fileTable->getDownloadRecords($lectureId);
        $downloads->buffer();
        //get previous and next links
        $previous = $lectureTable->getPreviousLecture($lectureId);
        if($previous){
            $previous= apiLecture(Lecture::find($previous->id));
        }


        $next = $lectureTable->getNextLecture($lectureId);
        if($next){
            $next = apiLecture(Lecture::find($next->id));
        }


        $previousLesson = $sessionLessonTable->getPreviousLessonInSession($sessionId,$lecture->lesson_id,'c');


        $sessionInstructorTable = new SessionInstructorTable();

        $instructors = $sessionInstructorTable->getSessionRecords($sessionId);




        $discussionTable = new DiscussionTable();
        $discussions = $discussionTable->getPaginatedRecordsForStudent(false,$this->getApiStudent()->id,$sessionId,$lectureId);

        //get all lectures for this lecture's class
        $lectures = $lectureTable->getRecordsOrdered($lecture->lesson_id);

        $lectureArray= apiRowset($lectures,'apiLecture');

        foreach($lectureArray as $key=>$value){
            $lectureId = $lectureArray[$key]['id'];
            $contents= Lecture::find($lectureId)->lecturePages()->orderBy('sort_order')->get();

            $data = $contents->toArray();

            foreach($data as $key2=>$value2){
                $data[$key2]['lecture_page_id'] = $data[$key2]['id'];
                unset($data[$key2]['content']);
                unset($data[$key2]['audio_code']);
            }

            $lectureArray[$key]['contents'] = $data;

        }

        $pageArray = $pages->toArray();
        foreach($pageArray as $key=>$value){
            $pageArray[$key]['lecture_page_id'] = $pageArray[$key]['id'];
            if($value['type']=='c'){
                $pageArray[$key]['content'] = htmlentities($pageArray[$key]['content']);
            }
            elseif($value['type']=='v'){
                preg_match('/src="([^"]+)"/', $pageArray[$key]['content'], $match);
                $url = $match[1];
                $pageArray[$key]['content'] = $url;
            }
            elseif($value['type']=='q'){
                $pageArray[$key]['content'] = json_decode($pageArray[$key]['content']);
            }
            elseif($value['type']=='l'){

                //give student access to video
                $studentVideoTable->addVideoForStudent($this->getApiStudentId(),$pageArray[$key]['content']);

                $video = Video::find($pageArray[$key]['content']);
                $video->video_id = $video->id;
                $pageArray[$key]['content'] = $video;


                if($pageArray[$key]['content']){
                    $video = $pageArray[$key]['content'];
                    $poster  = "uservideo/".$video->id."/".videoImage($video->file_name);
                    if (file_exists($poster)){
                        $pageArray[$key]['poster'] = $poster;
                    }

                }

            }
            elseif ($value['type']=='z'){
                $pageArray[$key]['content'] = unserialize($pageArray[$key]['content']);
            }

            if(!empty($value['audio_code'])){
                preg_match('/src="([^"]+)"/', $pageArray[$key]['audio_code'], $match);
                $url = $match[1];
                $pageArray[$key]['audio_code'] = $url;
            }
        }


        $sessionEntity = Course::find($sessionId);
        $output = [
            'lecture_name'=>$lecture->title,
            'pages'=>$pageArray,
            'lecture'=>apiLecture($lecture),
            'downloads'=>apiRowset($downloads,'apiDownload'),
            'previous'=>$previous,
            'next'=>$next,
            'instructors'=>apiRowset($instructors,'apiInstructor'),
            'discussions'=>apiRowset($discussions,'apiDiscussion'),
            'totalPages'=>$pages->count(),
            'sessionId'=>$sessionId,
            'previousLesson'=>apiClass($previousLesson),
            'lectures'=>$lectureArray,
            'session'=>apiCourse($sessionEntity),
            'status'=>true
        ];







        return jsonResponse($output);

    }


    public function getVideo(Request $request,$id){
        $params = $request->all();
        $valid = $this->validateGump($params,[
            'api_token'=>'required'
        ]);

        if(!$valid){
            return jsonResponse(['error'=>$this->getValidationErrorMsg(),'status'=>false]);
        }

        $videoId= $id;

        $token = $params['api_token'];
        $student = Student::where('api_token',$token)->first();
        if(!$student){
            return jsonResponse(['error'=>'Invalid student','status'=>false]);
        }


        $studentVideoTable = new StudentVideoTable();
        if(!$studentVideoTable->hasVideo($student->id,$videoId)){
            exit('You do not have access to this video');
        }



        /*    header('Content-Type: application/x-mpegURL');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');*/

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');

        $video = Video::find($videoId);

        $path = 'uservideo/'.$video->id.'/'.$video->file_name;

        if($video->location=='r'){
            $url = Storage::cloud()->temporaryUrl($path,now()->addHours(12));
            return response()->stream(function () use ($url,$video) {
                readfile($url);
            },200,['Content-Type'=>$video->mime_type]);
        }

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


    public function getSaaSVideo(Request $request,$id){
        $params = $request->all();
        $valid = $this->validateGump($params,[
            'api_token'=>'required'
        ]);

        if(!$valid){
            return jsonResponse(['error'=>$this->getValidationErrorMsg(),'status'=>false]);
        }

        $videoId= $id;

        $token = $params['api_token'];
        $student = Student::where('api_token',$token)->first();
        if(!$student){
            return jsonResponse(['error'=>'Invalid student','status'=>false]);
        }


        $studentVideoTable = new StudentVideoTable();
        if(!$studentVideoTable->hasVideo($student->id,$videoId)){
            exit('You do not have access to this video');
        }


        $quality = isset($params['quality'])? $params['quality']:'360' ;
        $userId = USER_ID;
        $videoDir  = "{$userId}/{$videoId}/";
        $playlistPath = $videoDir."{$quality}p.m3u8";
        $s3Config = [
            'region'=>env('AWS_DEFAULT_REGION'),
            'bucket'=>env('AWS_BUCKET'),
            'key'=>env('AWS_ACCESS_KEY_ID'),
            'secret'=>env('AWS_SECRET_ACCESS_KEY'),
            'cloudfront_domain'=> env('AWS_CLOUDFRONT_DOMAIN'),
            'cloudfront_key_pair_id'=>env('AWS_CLOUDFRONT_KEY_PAIR')
        ];

        $expires = time() + (86400*4);
        $cloudFront = $this->getCloudFrontClient();


        $resourceKey = 'http://' . $s3Config['cloudfront_domain'] . '/'.$playlistPath;

        $signedUrlCannedPolicy = $cloudFront->getSignedUrl([
            'url'         => $resourceKey,
            'expires'     => $expires,
            'private_key' => '../config/pk-APKAISJFXUOFGEQFNX5A.pem',
            'key_pair_id' => $s3Config['cloudfront_key_pair_id'],
        ]);

        $file= file_get_contents($signedUrlCannedPolicy);
        $array = explode("\n", $file);

        //loop through
        foreach($array as $key=>$value)
        {
            $file = $value;
            if(preg_match('#.ts#',$file))
            {
                $fullPath = 'http://' . $s3Config['cloudfront_domain'] . '/'.$videoDir.$file;
                $signedUrl = $cloudFront->getSignedUrl([
                    'url'         => $fullPath,
                    'expires'     => $expires,
                    'private_key' => '../config/pk-APKAISJFXUOFGEQFNX5A.pem',
                    'key_pair_id' => $s3Config['cloudfront_key_pair_id'],
                ]);
                $array[$key] = $signedUrl;
            }
        }

        $output = implode(PHP_EOL, $array);

        header('Content-Type: application/x-mpegURL');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        exit($output);




    }

    public function  getServiceManager(){
        return $GLOBALS['serviceManager'];
    }

    private function checkLectureOrder($lectureId,$sessionId){


        $lectureTable= new LectureTable();
        $sessionLogTable = new StudentSessionLogTable();
        $lectureRow = $lectureTable->getRecord($lectureId);
        $lessonTable = new LessonTable();
        $classId = $lectureRow->lesson_id;
        $lesson = $lessonTable->getRecord($classId);
        if(!empty($lesson->enforce_lecture_order) && $lectureRow->sort_order > 1){

            if($sessionLogTable->hasAttendance($this->getApiStudent()->id,$sessionId,$lectureId)){
                return true;
            }
            //get previous class
            $previousLecture = $lectureTable->getPreviousLecture($lectureId);
            if(!$sessionLogTable->hasAttendance($this->getApiStudent()->id,$sessionId,$previousLecture->id)){
                //get the last lecture student attended
                $lectures = $lectureTable->getRecordsOrdered($classId);
                //getStudentLog
                $nextLecture = null;
                foreach($lectures as $lecture){

                    if(!$sessionLogTable->hasAttendance($this->getApiStudent()->id,$sessionId,$lecture->id)){

                        $nextLecture = $lecture->id;
                        break;
                    }

                }//end lessons loop

                return jsonResponse([
                    'status'=>false,
                    'error'=>__lang('lecture-order-msg'),
                    'next'=>$nextLecture
                ]);

            }

        }

    }

    private function verifyClass($id,$session){
//        $sessionContainer = new Container('course');
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
//                $sessionContainer->url = selfURL();

                //loop through assignments and verify student has submission for each
                foreach($assignments as $assignment){


                    if(!$assignmentSubmissionTable->hasSubmission($this->getApiStudent()->id,$assignment->id)){
                        if($firstAssignment==false){
                            $firstAssignment = $assignment;
                        }

                        $subject = __lang('you-have-homework-for').' '.$previousClass->name;
                        $message= __lang('homework-reminder-flash',['title'=>$assignment->title,'class'=>$previousClass->name]);

                        $this->notifyStudent($this->getApiStudent()->id,$subject,$message);

                    }
                }

                if($firstAssignment){
                    return jsonResponse([
                        'status'=>false,
                        'error'=> __lang('homework-reminder-flash',['title'=>$firstAssignment->title,'class'=>$previousClass->name]),
                        'path'=>'submit-assignment',
                        'param'=>$firstAssignment->id
                    ]);

                    //redirect to assignment page
                    /*  $this->flashMessenger()->addMessage();

                      return $this->redirect()->toRoute('application/submit-assignment',['id'=>$firstAssignment->assignment_id]);*/

                }


            }

        }

        //check that class is opened
        $lessonRow = $sessionLessonTable->getSessionLessonRecord($session,$id);
        if(!empty($lessonRow->lesson_date) && stamp($lessonRow->lesson_date) > time()){
            /*      $this->flashMessenger()->addMessage('The class "'.$lessonRow->lesson_name.'" is scheduled to start on '.date('d/M/Y',$lessonRow->lesson_date));
                  $this->redirect()->toRoute('course-details',['id'=>$session]);*/

            $lesson = Lesson::find($lessonRow->lesson_id);
            return jsonResponse([
                'status'=>false,
                'error'=>__lang('class-starts-on',['class'=>$lesson->name,'date'=>showDate('d/M/Y',$lessonRow->lesson_date)]),
                'path'=>'course-intro',
                'param'=>$session
            ]);
        }


        if($sessionLessonTable->lessonExists($session,$id)){
            return true;
        }
        else{
            exit(jsonResponse(['Invalid record']));
        }

    }

    public function getId(){
        return $this->getApiStudent()->id;
    }

    private function validateEnrollment($sessionId){

        $studentSessionTable = new StudentSessionTable();
        if(!$studentSessionTable->enrolled($this->getApiStudent()->id,$sessionId)){
            return jsonResponse([
                'status'=>false,
                'error'=>'You are not enrolled into this course'
            ]);

        }

    }

    private function checkOrder($classId,$sessionId){
        $sessionTable= new SessionTable();
        $session = $sessionTable->getRecord($sessionId);
        $sessionLessonTable = new SessionLessonTable();
        $lesson = $sessionLessonTable->getSessionLessonRecord($sessionId,$classId);
        if(!empty($session->enforce_order) && $lesson->sort_order > 1){

            $attendanceTable = new AttendanceTable();
            if($attendanceTable->hasAttendance($this->getApiStudent()->id,$classId,$sessionId)){
                return true;
            }
            //get previous class
            $previousClass = $sessionLessonTable->getPreviousLessonInSession($sessionId,$classId);
            if(!$attendanceTable->hasAttendance($this->getApiStudent()->id,$previousClass->lesson_id,$sessionId)){
                //get the last class student attended
                $lessons = $sessionLessonTable->getSessionRecords($sessionId);

                //getStudentLog

                $nextLesson = null;
                foreach($lessons as $lesson){

                    if(!$attendanceTable->hasAttendance($this->getApiStudent()->id,$lesson->lesson_id,$sessionId)){

                        $nextLesson = $lesson->lesson_id;
                        break;
                    }

                }
                jsonResponse(['error'=>__lang('complete-right-order'),'status'=>false,'param'=>$nextLesson,'path'=>'class']);


            }

        }

    }

    public function  studentCourses(){
        //sleep(3);
        $student = $this->getApiStudent();
        $studentId = $student->id ;
        $studentSessionTable = new StudentSessionTable();
        $attendanceTable = new AttendanceTable();
        $paginator = $studentSessionTable->getStudentRecords(false,$studentId);
//            $paginator->setCurrentPageNumber((int)$this->params()->fromQuery('page', 1));
//            $paginator->setItemCountPerPage(10);

        $total = $studentSessionTable->getTotalForStudent($studentId);
        //$records = $paginator->toArray();
        $records = [];
        foreach ($paginator as $row){
            $row->id = $row->course_id;
            $records[] = apiCourse($row);
        }


        foreach($records as $key=>$value){
         //   $records[$key] = get
            $records[$key]['resume']= $this->getResumeData($value['course_id']);
        }


        $output = [
            'sessions'=>$records,
            'total'=>$total
        ];

        return jsonResponse($output);

    }

    public function getIntro(Request $request,$id){

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
        $discussions = $discussionTable->getPaginatedRecordsForStudent(false,$this->getApiStudent()->id,$id);

        $instructors = $instructorsTable->getSessionRecords($id);
        $instructors->buffer();
        $options = [];
        $options['admins'] = 'Administrators';
        foreach($instructors as $row2){

            $options[$row2->admin_id]= $row2->name.' '.$row2->last_name;


        }




        //get first class


        //calculate progress of attendance
        $attendanceTable = new AttendanceTable();
        $totalClasses = $sessionLessonTable->getSessionRecords($id)->count();
        //get tottal attended
        $attended = $attendanceTable->getTotalDistinctForStudentInSession($this->getApiStudent()->id,$id);

        if (!empty($totalClasses) && !empty($attended)){
            $percentage= ($attended/$totalClasses) * 100;
        }
        else{
            $percentage = 0;
        }


        $attendanceRecords = $attendanceTable->getAttendedRecords($this->getApiStudent()->id,$id);

        // $classesArray = $classes->toArray();

        $classesArray=[];
        foreach($classes as $class){

            $data = Lesson::find($class->lesson_id);

            $lectures = $lectureTable->getPaginatedRecords(false,$class->lesson_id);
            $lectureArray =[];
            foreach($lectures as $lecture){
                $lectureArray[] = apiLecture($lecture);
            }

            $data['lectures'] = $lectureArray;
            $classesArray[] = apiClass($data);


        }

        //get classes not attended
        $pendingClasses = [];
        foreach($classes as $class){
            if(!$attendanceTable->hasAttendance($this->getApiStudent()->id,$class->lesson_id,$id)){
                $pendingClasses[] = apiClass(Lesson::find($class->lesson_id));
            }
        }

        $row = apiCourse($row);

        $attendance = [];
        foreach($attendanceRecords as $record){
            $record->attendance_id = $record->id;
            $record->session_id = $record->course_id;
            $record->attendance_date = stamp($record->attendance_date);
            $record->lesson_name = $record->name;
            $attendance[] = $record;
        }

        $output= [
            'session_name'=>$row->name,
            'classes'=>$classesArray,
            'sessionRow'=>$row,
            'downloads'=>apiRowset($downloads,'apiDownload'),
            'discussions'=> apiRowset($discussions,'apiDiscussion'),
            'sessionId'=>$id,
            'instructors'=>apiRowset($instructors,'apiInstructor'),
            'percentage'=>$percentage,
            'studentId'=> $this->getApiStudent()->id,
            'classesAttended'=>$attendance,
            'pendingClasses'=>$pendingClasses
        ];

        return jsonResponse($output);
    }




    public function classFile(Request $request,$id){
        set_time_limit(86400);
        $params = $request->all();
        $sessionId = $params['course_id'];
        $table = new LessonFileTable();
        $row = $table->getRecord($id);
        $this->verifyClass($row->lesson_id,$sessionId);

        $path = 'usermedia/'.$row->path;
        header('Content-type: '.getFileMimeType($path));
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit();
    }


    public function lectureFile(Request $request,$id){
        set_time_limit(86400);
        $params = $request->all();
        $sessionId = $params['course_id'];
        $table = new LectureFileTable();
        $row = $table->getRecord($id);

        $fileRow = LectureFile::find($id);
        $this->verifyClass($fileRow->lecture->lesson_id,$sessionId);

        $path = 'usermedia/'.$row->path;
        header('Content-type: '.getFileMimeType($path));
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit();
    }


    public function loglecture(Request $request){


        $data = $request->all();

        $rules = [
            'lecture_id'=>'required',
            'session_id'=>'required',
        ];
        $isValid = $this->validateGump($data,$rules);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }


        $sessionLogTable = new StudentSessionLogTable();
        $studentTestTable = new StudentTestTable();
        $sessionLessonTable = new SessionLessonTable();
        $testTable = new TestTable();
        $studentLectureTable = new StudentLectureTable();


        $lecture = $data['lecture_id'];
        $session = $data['session_id'];

        $lectureTable = new LectureTable();
        $lectureRow = $lectureTable->getRecord($lecture);
        $lessonTable = new LessonTable();
        $this->verifyClass($lectureRow->lesson_id,$session);

        $logId = $sessionLogTable->addRecord([
            'student_id'=>$this->getApiStudent()->id,
            'course_id'=>$session,
            'lecture_id'=>$lecture
        ]);

        //check if there is another lecture and redirect to it
        $next = $lectureTable->getNextLecture($lecture);
        if($next){
            return jsonResponse([
                'status'=>true,
                'redirect_type'=>'lecture',
                'lecture_id'=>$next->id,
                'message'=>false
            ]);

        }
        else{
            //check for outstanding lectures
            $allLectures = $lectureTable->getLectureRecords($lectureRow->lesson_id);
            foreach($allLectures as $row){

                if(!$sessionLogTable->hasAttendance($this->getApiStudent()->id,$session,$row->id)){

                    return jsonResponse([
                        'status'=>true,
                        'redirect_type'=>'lecture',
                        'lecture_id'=>$row->id,
                        'message'=>__lang('outstanding-lectures')
                    ]);

                }

            }



            //check if class has test
            $lessonRow = $lessonTable->getRecord($lectureRow->lesson_id);
            if(!empty($lessonRow->test_required) && !empty($lessonRow->test_id) && $testTable->recordExists($lessonRow->test_id) && !$studentTestTable->passedTest($this->getApiStudent()->id,$lessonRow->test_id)){

                /* $container->testInfo = serialize([$lessonRow->test_id=>[
                     'lesson_id'=>  $lectureRow->lesson_id,
                     'session_id'=>$session,
                     'lesson_name'=>$lessonRow->lesson_name
                 ]]);*/


                $nextClass = $sessionLessonTable->getNextLessonInSession($session,$lectureRow->lesson_id,'c');


                return jsonResponse([
                    'status'=>true,
                    'redirect_type'=>'test',
                    'test_id'=>$lessonRow->test_id,
                    'lesson_id'=>  $lectureRow->lesson_id,
                    'session_id'=>$session,
                    'lesson_name'=>$lessonRow->name,
                    'message'=>__lang('class-test',['class'=>$lessonRow->name])
                ]);
            }
            else{

                //log attendance for this class
                $attendanceTable = new AttendanceTable();
                $attendanceTable->setAttendance([
                    'student_id'=>$this->getApiStudent()->id,
                    'course_id'=>$session,
                    'lesson_id'=>$lectureRow->lesson_id
                ]);

                //get next class
                $nextClass = $sessionLessonTable->getNextLessonInSession($session,$lectureRow->lesson_id,'c');
                if($nextClass){
                    //forward to the next class
                    return jsonResponse([
                        'status'=>true,
                        'redirect_type'=>'class',
                        'class_id'=>$nextClass->lesson_id,
                        'message'=>false
                    ]);

                }
                else{
                    //classes are over
                    $studentLectureTable->clearLecture($this->getApiStudent()->id,$session);
                    $studentSessionTable = new StudentSessionTable();
                    $studentSessionTable->markCompleted($this->getApiStudent()->id,$session);

                    return jsonResponse([
                        'status'=>true,
                        'redirect_type'=>'course',
                        'session_id'=>$session,
                        'message'=>__lang('course-complete-msg')
                    ]);
                }

            }

        }



    }


    public function bookmarks(Request $request){

        $rowset = Bookmark::where('student_id',$this->getApiStudentId())->paginate(30);

        $data = $rowset->toArray();

        foreach($data['data'] as $key=>$value){

            $data['data'][$key]['session_name'] = Course::find($value['course_id'])->name;
            $lecturePage = LecturePage::find($value['lecture_page_id']);
            $data['data'][$key]['lesson_name'] = $lecturePage->lecture->lesson->name;
            $data['data'][$key]['lecture'] = $lecturePage->lecture->title;
            $data['data'][$key]['lecture_page'] = $lecturePage->title;
        }

        return jsonResponse($data);
    }

    public function storeBookmark(Request $request){

        $data = $request->all();
        $this->validateParams($data,[
            'session_id'=>'required',
            'lecture_page_id'=>'required',
        ]);
        $data['student_id'] = $this->getApiStudentId();


        $bookMark = Bookmark::create($data);
        return jsonResponse([
            'status'=>true
        ]);

    }

    public function removeBookmark(Request $request,$id){

        $row = Bookmark::find($id);
        $this->validateApiOwner($row);

        $row->delete();
        return jsonResponse([
            'status'=>true
        ]);
    }
}
