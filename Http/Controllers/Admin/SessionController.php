<?php

namespace App\Http\Controllers\Admin;

use App\Course;
use App\CourseCategory;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\Lib\BaseForm;
use App\Lib\HelperTrait;
use App\Student;
use App\V2\Form\CourseFilter;
use App\V2\Form\CourseForm;
use App\V2\Model\AttendanceTable;
use App\V2\Model\LectureTable;
use App\V2\Model\LessonGroupTable;
use App\V2\Model\LessonTable;
use App\V2\Model\LessonToLessonGroupTable;
use App\V2\Model\SessionCategoryTable;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionLessonTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SessionTestTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\TestTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\InputFilter\InputFilter;
use Stripe\Checkout\Session;

class SessionController extends Controller
{
    use HelperTrait;

    public function sessiontype(Request $request,$id){

        $output = [];
        $row = Course::find($id);
        $select = new Select('course_type');
        $select->setAttribute('class','form-control');
        $select->setValueOptions([
            's'=>__lang('Training Session'),
            'b'=>__lang('training-online')
        ]);
        $select->setValue($row->type);
        if(request()->isMethod('post')){
            $type = $request->post('course_type');
            $row->type = $type;
            $row->save();
            return redirect()->route('admin.student.sessions')->with('flash_message',__lang('Changes Saved!'));
        }

        $output['select'] = $select;
        $output['id'] = $id;

        return view('admin.session.sessiontype',$output);
    }

    public function addcourse(Request $request,$type = null){
      //  $table = new SessionTable();
        $output = array();
        $output['id']=0;

        $filter = new CourseFilter();
      //  $sessionLessonTable = new SessionLessonTable();
    //    $sessionCategoryTable = new SessionToSessionCategoryTable();
    //    $sessionInstructorTable = new SessionInstructorTable();

        $dbType = 'c';
        $type= 'c';
        $form = new CourseForm(null,$dbType);

        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setInputFilter($filter);
            $form->setData($formData);
            if($form->isValid()){
                $data = $form->getData();

                if(!empty($data['session_date'])){

                    $date = Carbon::parse($data['session_date'])->toDateString();
                }
                else{
                    $date = '';
                }

                if(!empty($data['session_end_date'])){
                    $endDate = Carbon::parse($data['session_end_date'])->toDateString();
                }
                else{
                    $endDate=null;
                }

                if(!empty($data['enrollment_closes'])){
                    $closesOn = Carbon::parse($data['enrollment_closes'])->toDateString();
                }
                else{
                    $closesOn=null;
                }


                $dbData = array(
                    'name'=>$data['session_name'],
                    'enabled'=>$data['session_status'],
                    'payment_required'=>$data['payment_required'],
                    'fee'=>$data['amount'],
                    'description'=>$data['description'],
                    'type'=>$type,
                    'picture'=>$data['picture'],
                    'enable_discussion'=>$data['enable_discussion'],
                    'start_date'=>$date,
                    'end_date'=>$endDate,
                    'enrollment_closes'=>$closesOn,
                    'effort'=>$data['effort'],
                    'length'=>$data['length'],
                    'short_description'=>$data['short_description'],
                    'introduction'=>$data['introduction'],
                    'enable_forum'=>$data['enable_forum'],
                    'capacity'=>$data['capacity'],
                    'enforce_capacity'=>$data['enforce_capacity'],
                    'admin_id'=>$this->getAdmin()->admin->id
                );


                $courseRow = Course::create($dbData);
                $sessionId= $courseRow->id;

                if(isset($formData['session_category_id'])){
                    //now put the records in
                    $courseRow->courseCategories()->attach($formData['session_category_id']);

                }


                if(isset($formData['session_instructor_id'])){

                    $courseRow->admins()->attach($formData['session_instructor_id']);

                }


                return redirect()->route('admin.session.courseclasses',['id'=>$sessionId])->with('flash_message',__lang('course-add-msg'));

            }
            else{

                $output['flash_message'] = $this->getFormErrors($form);
                $array=[];
                if(isset($formData['session_category_id'])){
                    foreach($formData['session_category_id'] as $value){
                        $array['session_category_id[]'][] = $value[0];
                    }
                }

                if(isset($formData['session_instructor_id'])){

                    foreach($formData['session_instructor_id'] as $value){

                        $array['session_instructor_id[]'][] = $value[0];;
                    }
                }


                $form->setData($array);

                /*  if(!$this->lessonSelected($formData)){
                      $output['flash_message'] .= 'Ensure that you select at least one class';
                  }*/

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
        $output['action'] = route('admin.session.addcourse');
        $output['type'] = $type;
        $output['pageTitle'] = __lang('Add Online Course');
        $output['lessonGroupTable'] = new LessonToLessonGroupTable();
        return view('admin.session.addcourse',$output);

    }

    public function editcourse(Request $request,$id){
      //  $table = new SessionTable();
        $output = array();
        $output['pageTitle'] = __lang('Edit Online Course');
        $output['lessonGroupTable'] = new LessonToLessonGroupTable();
        $course = Course::find($id);
        $type = $course->type;
        $dbType = 'c';

        $form = new CourseForm(null,$dbType);
      //  $sessionCategoryTable = new SessionToSessionCategoryTable();
      //  $sessionInstructorTable = new SessionInstructorTable();


        $filter = new CourseFilter();
     //   $sessionLessonTable = new SessionLessonTable();



        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setInputFilter($filter);
            $form->setData($formData);

            if($form->isValid()){

                $data = $form->getData();


                if(!empty($data['session_date'])){

                    $date = Carbon::parse($data['session_date'])->toDateString();
                }
                else{
                    $date = null;
                }

                if(!empty($data['session_end_date'])){
                    $endDate = Carbon::parse($data['session_end_date'])->toDateString();
                }
                else{
                    $endDate=null;
                }

                if(!empty($data['enrollment_closes'])){
                    $closesOn = Carbon::parse($data['enrollment_closes'])->toDateString();
                }
                else{
                    $closesOn=null;
                }


                $dbData = array(
                    'name'=>$data['session_name'],
                    'enabled'=>$data['session_status'],
                    'payment_required'=>$data['payment_required'],
                    'fee'=>$data['amount'],
                    'description'=>$data['description'],
                    'type'=>$type,
                    'picture'=>$data['picture'],
                    'enable_discussion'=>$data['enable_discussion'],
                    'start_date'=>$date,
                    'end_date'=>$endDate,
                    'enrollment_closes'=>$closesOn,
                    'effort'=>$data['effort'],
                    'length'=>$data['length'],
                    'short_description'=>$data['short_description'],
                    'introduction'=>$data['introduction'],
                    'enable_forum'=>$data['enable_forum'],
                    'capacity'=>$data['capacity'],
                    'enforce_capacity'=>$data['enforce_capacity'],
                    'enable_chat'=>$data['enable_chat'],
                    'enforce_order'=>$data['enforce_order'],

                );
                $course->fill($dbData);
                $course->save();

                if(isset($formData['session_category_id'])){
                    $course->courseCategories()->sync($formData['session_category_id']);
                }


                if(isset($formData['session_instructor_id'])){
                    $course->admins()->sync($formData['session_instructor_id']);
                }






                session()->flash('flash_message',__lang('Changes Saved!'));
                return adminRedirect(array('controller'=>'student','action'=>'sessions'));
            }
            else{

                $output['flash_message'] = $this->getFormErrors($form);
                $array=[];
                if(isset($formData['session_category_id'])){
                    foreach($formData['session_category_id'] as $value){
                        $array['session_category_id[]'][] = $value[0];
                    }
                }

                if(isset($formData['session_instructor_id'])){
                    foreach($formData['session_instructor_id'] as $value){

                        $array['session_instructor_id[]'][] = $value[0];;
                    }
                }

                $form->setData($array);

            }
        }
        else{
            $row = $course;
            $data = $course->toArray();
            if(!empty($data['session_date']))
                $data['session_date'] = Carbon::parse($row->start_date)->toDateString() ;

            if(!empty($data['session_end_date']))
                $data['session_end_date'] = Carbon::parse($row->end_date)->toDateString();

            if(!empty($data['enrollment_closes']))
                $data['enrollment_closes'] = Carbon::parse($row->enrollment_closes)->toDateString();
            $data['session_name'] = $row->name;
            $data['session_status'] = $row->enabled;
            $data['amount'] = $row->fee;


            foreach($course->courseCategories as $groupRow){
                $data['session_category_id[]'][] = $groupRow->id;
            }
            $sessionInstructorTable = new SessionInstructorTable();
            $rowset = $sessionInstructorTable->getSessionRecords($id);
            foreach($rowset as $groupRow){
                $data['session_instructor_id[]'][] = $groupRow->admin_id;
            }


            $form->setData($data);
        }

        $row = $course;
        if ($row->picture && file_exists($row->picture) && is_file($row->picture)) {
            $output['display_image'] = resizeImage($row->picture, 100, 100,$this->getBaseUrl());
        } else {

            $output['display_image'] = resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        }


        $output['no_image']= $this->getBaseUrl().'/'.resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());

        $output['form'] = $form;
        $output['action'] = route('admin.session.editcourse',['id'=>$id]);
        $output['id'] = $id;
        $output['type'] = $type;
        return view('admin.session.addcourse',$output);
    }
    public function sessionclasses(Request $request,$id){
        $sessionTable = new SessionTable();
        $sessionLessonTable =  new SessionLessonTable();
        $sessionLessonTable->arrangeSortOrders($id);
        $sessionTable->getRecord($id);
        //get session
        $session = Course::find($id);

        $this->data['session'] = $session;


        //$addClassView = $this->forward()->dispatch('Admin\Controller\Lesson',['action'=>'add']);

        $addClassView = app(LessonController::class)->add($request);
        $this->data = $this->data + $addClassView->getData();

        $this->data['pageTitle'] = __lang('Manage Classes').': '.$session->name;

        return view('admin.session.sessionclasses',$this->data);
    }


    public function courseclasses(Request $request,$id){

        $sessionTable = new SessionTable();
        $sessionLessonTable =  new SessionLessonTable();
        $sessionLessonTable->arrangeSortOrders($id);
        $sessionTable->getRecord($id);
        //get session
        $session = Course::find($id);

        $this->data['session'] = $session;


        $addClassView = app(LessonController::class)->add($request);
        $this->data = $this->data + $addClassView->getData();

        $this->data['pageTitle'] = __lang('Manage Classes').': '.$session->name;

        return view('admin.session.courseclasses',$this->data);
    }

    public function browseclasses(Request $request,$id) {
        // TODO Auto-generated NewssController::index(Request $request) default action
        $sessionId = $id;
        $table = new LessonTable();

        $filter = request()->get('filter');


        if (empty($filter)) {
            $filter=null;
        }

        $group = request()->get('group', null);
        if (empty($group)) {
            $group=null;
        }



        $text = new Text('filter');
        $text->setAttribute('class','form-control');
        $text->setAttribute('placeholder',__lang('Filter by class name'));
        $text->setValue($filter);

        $select = new Select('group');
        $select->setAttribute('class','form-control select2');
        $select->setEmptyOption('--'.__lang('Select Class Group').'--');


        $groupTable = new LessonGroupTable();
        $groupRowset = $groupTable->getLimitedRecords(1000);
        $options =[];

        foreach($groupRowset as $row){
            $options[$row->id] = $row->name;
        }
        $select->setValueOptions($options);
        $select->setValue($group);

        $type = $request->get('type');
        if(empty($type)){
            $type = 'online';
        }
        else{
            $type = '';
        }

        $paginator = $table->getLessons(true,$filter,$group,$type);

        /*$paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);*/
        $model= viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Classes'),
            'lectureTable'=> new LectureTable(),
            'filter'=>$filter,
            'group'=>$group,
            'text'=>$text,
            'select'=>$select,
            'sessionId'=>$sessionId
        ));
        return $model;


    }

    public function setclass(Request $request,$id){
        $sessionTable = new SessionTable();
        $sessionId = $_GET['sessionId'];
        $session = $sessionTable->getRecord($sessionId);


        $sessionLessonTable = new SessionLessonTable();

        //$last = SessionLesson::where('session_id',$sessionId)->orderBy('sort_order','desc')->first();
        $course = Course::find($sessionId);
        $last = $course->lessons()->orderBy('pivot_sort_order','desc')->limit(1)->first();
        if($last){
            $sortOrder = $last->pivot->sort_order + 1;
        }
        else{
            $sortOrder = 1;
        }

        $sessionLessonTable->addRecord([
            'course_id'=>$sessionId,
            'lesson_id'=>$id,
            'sort_order'=>$sortOrder
        ]);
        session()->flash('flash_message',__lang('Class Added!'));


        if($session->type=='c'){
            return adminRedirect(['controller'=>'session','action'=>'courseclasses','id'=>$sessionId]);

        }
        else{
            return adminRedirect(['controller'=>'session','action'=>'sessionclasses','id'=>$sessionId]);

        }

    }


    public function reorder(Request $request,Course $course){
        if(!empty($_REQUEST['row'])){

            $counter = 1;
            foreach($_REQUEST['row'] as $id){

                $row = $course->lessons()->find($id)->pivot;
                if ($row){
                    $row->sort_order = $counter;
                    $row->save();
                    $counter++;
                }

             /*   $sessionLesson =  SessionLesson::find($id);
                if($sessionLesson){
                    $sessionLesson->sort_order = $counter;
                    $sessionLesson->save();
                    $counter++;
                }*/

            }

        }
        exit('done');
    }

    public function setdate(Request $request,Course $course,Lesson $lesson=null){
        if(request()->isMethod('post')){

            $date = $request->post('date');
       /*     $sessionLesson = SessionLesson::find($id);
            if(empty($date)){
                $dbData = '';
            }
            else{
                $dbData = strtotime($date);
            }*/
            $row = $course->lessons()->find($lesson->id)->pivot;
            $row->lesson_date = Carbon::parse($date)->toDateString();
            $row->save();
            exit(__lang('Changes Saved!'));
        }

        exit('done');
    }

    public function setstart(Request $request,Course $course,Lesson $lesson=null){

        if(request()->isMethod('post')){

            $start = $request->post('start');
            $row = $course->lessons()->find($lesson->id)->pivot;
            $row->lesson_start = $start;
            $row->save();
            exit(__lang('Changes Saved!'));
        }

        exit('done');
    }

    public function setend(Request $request,Course $course,Lesson $lesson=null){

        if(request()->isMethod('post')){

            $end = $request->post('end');
       /*     $sessionLesson = SessionLesson::find($id);

            $sessionLesson->lesson_end= $end;
            $sessionLesson->save();*/
            $row = $course->lessons()->find($lesson->id)->pivot;
            $row->lesson_end = $end;
            $row->save();
            exit(__lang('Changes Saved!'));
        }

        exit('done');
    }

    public function setvenue(Request $request,Course $course,Lesson $lesson=null){
        $id = request()->get('id');
        if(request()->isMethod('post')){

            $venue = $request->post('venue');
        /*    $sessionLesson = SessionLesson::find($id);

            $sessionLesson->lesson_venue = $venue;
            $sessionLesson->save();*/

            $row = $course->lessons()->find($lesson->id)->pivot;
            $row->lesson_venue = $venue;
            $row->save();
            exit(__lang('Changes Saved!'));
        }

        exit('done');
    }

    public function lectures(Request $request){
        $id = request()->get('id');
        $sessionLesson = SessionLesson::find($id);
        $viewModel = $this->forward()->dispatch('Admin\Controller\Lecture',['action'=>'index','id'=>$sessionLesson->lesson_id]);

        return $viewModel;
    }

    public function deleteclass(Request $request,Lesson $lesson,Course $course){
     /*   $id = request()->get('id');
        $item = SessionLesson::find($id);
        $model= new SessionTable();
        $model->getRecord($item->session_id);


        $item->delete();*/
        $course->lessons()->detach($lesson->id);
        $sessionLessonTable= new SessionLessonTable();
        $sessionLessonTable->arrangeSortOrders($course->id);
        session()->flash('flash_message',__lang('Class removed'));
        return back();
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



    public function groups(Request $request){

        $table = new SessionCategoryTable();

        $categories = $table->getAllCategories();

        $paginator = $table->getPaginatedRecords(true);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Course Categories'),
            'categories'=>$categories
        ));


    }

    private function getGroupForm(){
        $table = new SessionCategoryTable();
        $categories = $table->getAllCategories();
        $form = new BaseForm();
        $form->createText('name','Category Name',true);
        $form->createTextArea('description','Description');
        $form->createSelect('enabled','Status',['1'=>__lang('Enabled'),'0'=>__lang('Disabled')],true,false);
        $form->createSelect('parent_id','Parent',$categories,false);
        $form->createText('sort_order','Sort Order',false,'form-control number',null,__lang('Digits Only'));
        return $form;
    }

    private function getGroupFilter(){
        $filter = new InputFilter();
        $filter->add(array(
            'name'=>'name',
            'required'=>true,
            'validators'=>array(
                array(
                    'name'=>'NotEmpty'
                )
            )
        ));

        $filter->add(array(
            'name'=>'description',
            'required'=>false,

        ));

        $filter->add(array(
            'name'=>'enabled',
            'required'=>false,

        ));

        $filter->add(array(
            'name'=>'parent_id',
            'required'=>false,

        ));

        $filter->add(array(
            'name'=>'sort_order',
            'required'=>false,
            'validators'=>array(
                array(
                    'name'=>'Digits'
                )
            )
        ));


        return $filter;
    }

    /**
     * Add a new group
     */
    public function addgroup(Request $request){

        $output = array();
        $table = new SessionCategoryTable();
        $form = $this->getGroupForm();
        $filter = $this->getGroupFilter();


        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = $request->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();

                if (empty($array['parent_id'])){
                    unset($array['parent_id']);
                }

                $array[$table->getPrimary()]=0;
                $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                session()->flash('flash_message',__lang('category-created'));
                return adminRedirect(array('controller'=>'session','action'=>'groups'));

            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
            }

        }


        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Course Category');
        $output['action']='addgroup';
        $output['id']=null;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);


    }

    public function editgroup(Request $request,$id){
        $output = array();
        $table = new SessionCategoryTable();

        $filter = $this->getGroupFilter();
        $form = $this->getGroupForm();

        $row = CourseCategory::find($id);
        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {



                $array = $form->getData();

                if($id==$array['parent_id']){
                    $array['parent_id'] = null;
                }
                if (empty($array['parent_id'])){
                    unset($array['parent_id']);
                }


                $array[$table->getPrimary()]=$id;
                $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                session()->flash('flash_message',__lang('Changes Saved!'));

                return adminRedirect(array('controller'=>'session','action'=>'groups'));

            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
            }

        }
        else {

            $data = $row->toArray();

            $form->setData($data);

        }

        $output['form'] = $form;
        $output['id'] = $id;
        $output['pageTitle']= __lang('Edit Course Category');
        $output['row']= $row;
        $output['action']='editgroup';

        $viewModel = viewModel('admin',__CLASS__,'addgroup',$output);
        return $viewModel ;
    }

    public function deletegroup(Request $request,$id){
        $table = new SessionCategoryTable();

        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            dd($ex->getTraceAsString());
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'session','action'=>'groups'));

    }

    public function createclass(Request $request)
    {
        $output = array();
        $lessonTable = new LessonTable();
        $form = new LessonForm(null);
        $filter = new LessonFilter();

        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();
                $array[$lessonTable->getPrimary()]=0;
                unset($array['lesson_group_id[]']);
                $id = $lessonTable->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                $form = new LessonForm(null);
                $output['lesson_id'] = $id;

                $output['row']= $lessonTable->getRecord($id);

                $sessionForm = new CourseForm(null);
                $output['form'] = $sessionForm;
                $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);
                $viewModel->setTerminal(true);
                return $viewModel;
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
                $messages=$form->getMessages();
                //      print_r($messages);
                //    print_r($output);
                exit();

            }

        }
        else{
            exit('Invalid request');
        }



    }


    public function stats(Request $request,$id)
    {
        $output = [];
        $studentSessionTable = new StudentSessionTable();

        $sessionLessonTable = new SessionLessonTable();
        $sessionTable = new SessionTable();
        $attendanceTable = new AttendanceTable();
        $testResultsTable = new StudentTestTable();

        $row = $studentSessionTable->getRecord($id);
        $output['row'] = $row;

        //get total classes
        $output['totalLessons'] = $sessionLessonTable->getSessionRecords($row->course_id)->count();


        //get list of classes student has attended
        $output['attended'] = $attendanceTable->getAttendedRecords($row->student_id,$row->course_id);

        if($output['totalLessons']>0){
            $output['percentage'] = 100 * ($output['attended']->count()/$output['totalLessons']);
        }
        else{
            $output['percentage'] = 0;
        }

        $output['percentage'] = round($output['percentage']);
        //get list of classes
        $lessons = $sessionLessonTable->getSessionRecords($row->course_id);
        $lessons->buffer();

        //get test results
        $studentTestTable = new StudentTestTable();
        $testResults = [];
        foreach($lessons as $lesson){

            if(!empty($lesson->test_required) && !empty($lesson->test_id) && $studentTestTable->hasTest($lesson->test_id,$row->student_id))
            {
                $testResults[$lesson->test_id] = $studentTestTable->getStudentRecord($row->student_id,$lesson->test_id);
            }

        }

        $output['testResults'] = $testResults;
        $output['pageTitle'] = __lang('Student Progress').': '.$row->name.' '.$row->last_name;

        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'student','action'=>'sessions'])=>__lang('session-courses'),
            adminUrl(['controller'=>'student','action'=>'sessionstudents','id'=>$row->course_id])=>__lang('Enrolled Students'),
            '#'=>__lang('Student Progress')
        ];


        return view('admin.session.stats',$output);
    }

    public function tests(Request $request,$id){
        $sessionTestTable = new SessionTestTable();
        $sessionTable= new SessionTable();
        $row = $sessionTable->getRecord($id);

        $rowset = $sessionTestTable->getSessionRecords($id);
        $total = $rowset->count();
        return view('admin.session.tests',[
            'pageTitle'=>__lang('Tests for').'  '.$row->name.' ('.$total.')',
            'rowset'=>$rowset,
            'id'=>$id
        ]);

    }

    public function addtest(Request $request,$id){
        $sessionTestTable = new SessionTestTable();
        $sessionTable = new SessionTable();
        $sessionRow = $sessionTable->getRecord($id);
        $form = $this->getSessionTestForm();
        $output = [];
        if(request()->isMethod('post')){
            $formData = $request->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();
                $data['course_id'] = $id;
                $data['opening_date']= getDateString($data['opening_date']);
                $data['closing_date']=getDateString($data['closing_date']);
                $sessionTestTable->addRecord($data);
                session()->flash('flash_message',__lang('Test added successfully'));
                return adminRedirect(['controller'=>'session','action'=>'tests','id'=>$id]);


            }
            else{
                $output['flash_message']= $this->getFormErrors($form);
            }
        }

        $output['form'] = $form;
        $output['pageTitle'] = __lang('Add Test to').' '.$sessionRow->name;
        $output['id']=$id;
        $output['crumbLabel'] = __lang('add');
        return view('admin.session.addtest',$output);
    }

    public function edittest(Request $request,$id){
        $sessionTestTable = new SessionTestTable();
        $row = $sessionTestTable->getRecord($id);
        $sessionTable = new SessionTable();
        $sessionRow = $sessionTable->getRecord($row->course_id);
        $form = $this->getSessionTestForm();
        $output = [];
        if($request->isMethod('post')){
            $formData = $request->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();

                $data['opening_date']= getDateString($data['opening_date']);
                $data['closing_date']=getDateString($data['closing_date']);
                $sessionTestTable->update($data,$id);
                session()->flash('flash_message',__lang('changes-saved'));
                return adminRedirect(['controller'=>'session','action'=>'tests','id'=>$sessionRow->id]);


            }
            else{
                $output['flash_message']= $this->getFormErrors($form);
            }
        }
        else{
            $data = getObjectProperties($row);
            if(!empty($data['opening_date']))
                $data['opening_date'] = showDate('Y-m-d',$row->opening_date);

            if(!empty($data['closing_date']))
                $data['closing_date'] = showDate('Y-m-d',$row->closing_date);

            $form->setData($data);

        }

        $output['form'] = $form;
        $output['id']= $row->course_id;
        $output['crumbLabel'] = __lang('edit');
        $output['pageTitle'] = __lang('Edit Test for').' '.$sessionRow->name;
        return view('admin.session.addtest',$output);
    }





    private function getSessionTestForm(){
        $form = new BaseForm();


        $options=[];



        $testTable = new TestTable();
        $rowset = $testTable->getLimitedRecords(1000);
        foreach($rowset as $row)
        {
            $options[$row->id]= $row->name;
        }

        $form->createSelect('test_id', 'Test', $options,true);
        $form->get('test_id')->setAttribute('class','form-control select2');


        $form->createText('opening_date','Opening Date (Optional)',false,'form-control date',null,__lang('Opening Date'));
        $form->createText('closing_date','Closing Date (Optional)',false,'form-control date',null,__lang('Closing Date'));

        $form->setInputFilter($this->getSessionTestFilter());
        return $form;


    }

    private function getSessionTestFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'test_id',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'opening_date',
            'required'=>false
        ]);

        $filter->add([
            'name'=>'closing_date',
            'required'=>false
        ]);

        return $filter;

    }

    public function smssession(Request $request){

        $studentSessionTable = new StudentSessionTable();
        $studentsTable = new StudentTable();
        $id = request()->get('id');

        $count = 0;

        if(request()->isMethod('post'))
        {
            $this->validate($request,[
               'message'=>'required',
               'gateway'=>'required'
            ]);

            $message = $request->message;
            $gateway = $request->gateway;


            if(!empty($id)){
                $totalRecords = $studentSessionTable->getTotalForSession($id);
            }
            else{
                $totalRecords = Student::count();
            }

            $rowsPerPage = 3000;
            $totalPages = ceil($totalRecords/$rowsPerPage);

            $numbers = [];
            for($i=1;$i<=$totalPages;$i++){
                if(!empty($id)){
                    $paginator = $studentSessionTable->getSessionRecords(true,$id,true);
                }
                else{
                    $paginator = $studentsTable->getPaginatedRecords(true);
                }


                $paginator->setCurrentPageNumber($i);
                $paginator->setItemCountPerPage($rowsPerPage);

                foreach ($paginator as $row){
                    if(!empty($row->mobile_number)){
                        $numbers[] = $row->mobile_number;
                        $count++;
                    }

                }

            }

            $response = sendSms($gateway,$numbers,$message);

            session()->flash('flash_message',$response);

        }

        return back();


    }



}
