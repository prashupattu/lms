<?php

namespace App\Http\Controllers\Admin;

use App\Course;
use App\Http\Controllers\Controller;
use App\Lib\BaseForm;
use App\Lib\HelperTrait;
use App\Survey;
use App\SurveyOption;
use App\SurveyQuestion;
use App\V2\Form\SurveyFilter;
use App\V2\Form\SurveyForm;
use App\V2\Form\TestQuestionFilter;
use App\V2\Form\TestQuestionForm;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionSurveyTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SurveyOptionTable;
use App\V2\Model\SurveyQuestionTable;
use App\V2\Model\SurveyResponseOptionTable;
use App\V2\Model\SurveyResponseTable;
use App\V2\Model\SurveyTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laminas\Form\Element\Text;
use Laminas\InputFilter\InputFilter;

class SurveyController extends Controller
{
    use HelperTrait;

    public function index(Request $request){

        $table = new SurveyTable();
        $questionTable = new SurveyQuestionTable();
        $studentSurveyTable = new SurveyResponseTable();
        $filter = request()->get('filter');



        if (empty($filter)) {
            $filter=null;
        }
        $paginator = $table->getPaginatedRecords(true,null,$filter);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('surveys'),
            'questionTable'=>$questionTable,
            'studentSurveyTable'=>$studentSurveyTable
        ));
    }


    public function add(Request $request)
    {
        $output = array();
        $table = new SurveyTable();
        $form = new SurveyForm(null,$this->getServiceLocator());
        $filter = new SurveyFilter();

        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();
                $array['hash'] = Str::random(40);
                $array[$table->getPrimary()]=0;
                $id= $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                session()->flash('flash_message',__lang('survey-add-msg'));
                return adminRedirect(['controller'=>'survey','action'=>'questions','id'=>$id]);
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');

            }

        }

        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Survey');
        $output['action']='add';
        $output['id']=null;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);
    }

    public function edit(Request $request,$id){
        $output = array();
        $table = new SurveyTable();
        $form = new SurveyForm(null,$this->getServiceLocator());
        $filter = new SurveyFilter();

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
                return redirect()->route('admin.survey.index');
                $row = $table->getRecord($id);
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
        $output['pageTitle']= __lang('Edit Survey');
        $output['row']= $row;
        $output['action']='edit';

        $viewModel = viewModel('admin',__CLASS__,'add',$output);

        return $viewModel ;

    }

    public function delete(Request $request,$id)
    {
        $table = new SurveyTable();
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'survey','action'=>'index'));
    }


    public function questions(Request $request,$id){
        $testTable = new SurveyTable();

        $table = new SurveyQuestionTable();
        $optionTable = new SurveyOptionTable();
        $row = $testTable->getRecord($id);
        $paginator = $table->getPaginatedRecords(true,$id);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Survey Questions').': '.$row->name,
            'id'=>$id,
            'optionTable'=>$optionTable,
            'page'=>(int)request()->get('page', 1)
        ));
    }


    public function addquestion(Request $request,$id)
    {
        $testQuestionTable = new SurveyQuestionTable();
        $testOptionTable = new SurveyOptionTable();
        if(request()->isMethod('post'))
        {
            $data = request()->all();
            if(!empty($data['question'])){

                $dbData= [
                    'survey_id'=>$id,
                    'question'=>$data['question'],
                    'sort_order'=>intval($data['sort_order'])
                ];

                $questionId = $testQuestionTable->addRecord($dbData);
                session()->flash('flash_message',__lang('Question added'));
                //correct answer

                for($i=1;$i<=5;$i++){

                    if(!empty($data['option_'.$i])){

                        $optionData = [
                            'survey_question_id'=>$questionId,
                            'option'=> trim($data['option_'.$i])
                        ];



                        $testOptionTable->addRecord($optionData);
                    }


                }


            }
        }

        return back();
        // return adminRedirect(['controller'=>'survey','action'=>'questions','id'=>$id]);
    }

    public function editquestion(Request $request,$id){

        $output = [];
        $questionTable = new SurveyQuestionTable();
        $optionTable = new SurveyOptionTable();
        $form = new TestQuestionForm();
        $filter = new TestQuestionFilter();
        $form->setInputFilter($filter);
        $row = $questionTable->getRecord($id);
        $rowset = $optionTable->getOptionRecords($id);



        if(request()->isMethod('post'))
        {
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){
                $data = $form->getData();
                $questionTable->update($data,$id);
                session()->flash('flash_message',__lang('Changes Saved!'));
                return adminRedirect(['controller'=>'survey','action'=>'questions','id'=>$row->survey_id]);
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');
            }
        }
        else{
            $form->setData(getObjectProperties($row));
        }


        $output['row'] = $row;
        $output['rowset']= $rowset;
        $output['form'] = $form;
        $output['id'] = $id;
        $output['pageTitle'] = __lang('Edit Question/Options');
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'survey','action'=>'index'])=>__lang('Surveys'),
            adminUrl(['controller'=>'survey','action'=>'questions','id'=>$row->survey_id])=>__lang('Survey Questions'),
            '#'=>__lang('Edit Question')
        ];
        return view('admin.survey.editquestion',$output);
    }

    public function addoptions(Request $request,$id)
    {
        $testOptionTable = new SurveyOptionTable();
        if(request()->isMethod('post'))
        {
            $data = request()->all();


            //correct answer

            $count = 0;
            for($i=1;$i<=5;$i++){

                if(!empty($data['option_'.$i])){

                    $optionData = [
                        'survey_question_id'=>$id,
                        'option'=> trim($data['option_'.$i])
                    ];


                    $testOptionTable->addRecord($optionData);
                    $count++;

                }


            }
            session()->flash('flash_message',$count.' '.__lang('options added'));


        }

        return adminRedirect(['controller'=>'survey','action'=>'editquestion','id'=>$id]);

    }

    public function editoption(Request $request,$id){

        $testOptionTable = new SurveyOptionTable();
        $row = $testOptionTable->getRecord($id);
        $questionId = $row->survey_question_id;

        if(request()->isMethod('post'))
        {
            $data = request()->all();
            if(!empty($data['option'])) {

                $dbData = [];

                $dbData['option']=$data['option'];
                $testOptionTable->update($dbData,$id);
                session()->flash('flash_message',__lang('Option saved'));
            }
            else{
                session()->flash('flash_message',__lang('survey-save-failed'));
            }
            return adminRedirect(['controller'=>'survey','action'=>'editquestion','id'=>$questionId]);
        }

        $option = new Text('option');
        $option->setAttributes(['class'=>'form-control']);
        $option->setValue($row->option);



        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,['row'=>$row,'option'=>$option,'id'=>$id]);

        return $viewModel;
    }

    public function deletequestion(Request $request,$id)
    {
        $table = new SurveyQuestionTable();
        $row = $table->getRecord($id);
        $testId = $row->survey_id;
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'survey','action'=>'questions','id'=>$testId));
    }

    public function deleteoption(Request $request,$id)
    {
        $table = new SurveyOptionTable();
        $row = $table->getRecord($id);
        $questionId = $row->survey_question_id;
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'survey','action'=>'editquestion','id'=>$questionId));
    }

    public function duplicate(Request $request,$id)
    {
        $testTable = new SurveyTable();
        $testQuestionTable = new SurveyQuestionTable();
        $testOptionTable = new SurveyOptionTable();

        //get all questions
        $test = $testTable->getRecord($id);
        $questions = $testQuestionTable->getPaginatedRecords(false,$test->id)->toArray();
        $options = [];
        foreach($questions as $question){
            $options[$question['id']] = $testOptionTable->getOptionRecords($question['id'])->toArray();
        }


        $testData = getObjectProperties($test);
        $testData ['hash'] = Str::random(40);
        unset($testData['id']);

        $newId = $testTable->addRecord($testData);

        foreach($questions as $question)
        {
            $oldQuestionId=$question['id'];
            $question['survey_id']= $newId;
            unset($question['id']);
            $questionId=  $testQuestionTable->addRecord($question);
            foreach($options[$oldQuestionId] as $option){
                $option['survey_question_id'] = $questionId;
                unset($option['id']);
                $testOptionTable->addRecord($option);
            }

        }

        session()->flash('flash_message',__lang('Survey duplicated'));
        return adminRedirect(['controller'=>'survey','action'=>'index']);



    }


    public function results(Request $request,$id)
    {

        $testTable = new SurveyTable();
        $table = new SurveyResponseTable();

        $filter = request()->get('filter');
        $startDate = request()->get('start', null) ? strtotime(request()->get('start', null)):null;
        $endDate = request()->get('end', null) ? strtotime(request()->get('end', null)):null ;

        if (empty($filter)) {
            $filter=null;
        }


        $row= $testTable->getRecord($id);
        $paginator = $table->getPaginatedRecords(true,$id);

        $testTotal = $table->getTotalForTest($id,$startDate,$endDate);


        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);

        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Survey results').':('.$testTotal.') '.$row->name,
            'row'=>$row
        ));
    }

    public function deleteresult(Request $request,$id)
    {
        $studentSurveyTable = new SurveyResponseTable();

        $row = $studentSurveyTable->getRecord($id);
        $testId = $row->survey_id;
        try{
            $studentSurveyTable->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'survey','action'=>'results','id'=>$testId));
    }

    public function result(Request $request,$id)
    {
        $studentSurveyTable = new SurveyResponseTable();
        $studentOptionTable = new SurveyResponseOptionTable();
        $row = $studentSurveyTable->getRecord($id);
        $rowset = $studentOptionTable->getSurveyRecords($id);
        $data = ['row'=>$row,'rowset'=>$rowset];
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$data);
        return $viewModel;

    }

    public function exportresult(Request $request,$id){

        $type = $_GET['type'];
        $studentSurveyTable = new SurveyResponseTable();
        $testTable = new SurveyTable();
        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $startDate = request()->get('start', null) ? strtotime(request()->get('start', null)):null;
        $endDate = request()->get('end', null) ? strtotime(request()->get('end', null)):null ;


        $myfile = fopen($file, "w") or die("Unable to open file!");
        $testRow = $testTable->getRecord($id);
        if($type=='pass')
        {
            $totalRecords = $studentSurveyTable->getTotalPassedForTest($id,$testRow->passmark,$startDate,$endDate);
        }
        else{
            $totalRecords = $studentSurveyTable->getTotalFailedForTest($id,$testRow->passmark,$startDate,$endDate);
        }

        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        fputcsv($myfile, array('First Name','Last Name','Score%'));
        for($i=1;$i<=$totalPages;$i++){
            if($type=='pass') {
                $paginator = $studentSurveyTable->getPassedPaginatedRecords(true, $id,$testRow->passmark,$startDate,$endDate);
            }
            else{
                $paginator = $studentSurveyTable->getFailPaginatedRecords(true, $id,$testRow->passmark,$startDate,$endDate);
            }

            $paginator->setCurrentPageNumber($i);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row){

                fputcsv($myfile, array($row->first_name,$row->last_name,$row->score));

            }



        }
        $paginator = array();
        fclose($myfile);
        header('Content-type: text/csv');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.$type.'_student_test_export_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();
    }


    public function sessions(Request $request,$id){

        $sessionSurveyTable = new SessionSurveyTable();
        $surveyTable = new  SurveyTable();
        $surveyRow = $surveyTable->getRecord($id);

        $rowset = $sessionSurveyTable->getSurveyRecords($id);
        return view('admin.survey.sessions',[
            'pageTitle'=>__lang('survey-sessions-courses').': '.$surveyRow->name,
            'rowset'=>$rowset,
            'id'=>$id
        ]);

    }

    public function addsession(Request $request,$id){

        $sessionSurveyTable = new SessionSurveyTable();
        $testTable = new SurveyTable();
        $testRow = $testTable->getRecord($id);
        $form = $this->getSessionTestForm();
        $output = [];
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();
                $data['survey_id'] = $id;
                $sessionSurveyTable->addRecord($data);
                session()->flash('flash_message',__lang('course-added-succ'));
                return adminRedirect(['controller'=>'survey','action'=>'sessions','id'=>$id]);


            }
            else{
                $output['flash_message']= $this->getFormErrors($form);
            }
        }

        $output['form'] = $form;
        $output['pageTitle'] = __lang('add-course-to').' '.$testRow->name;
        $output['id']=$id;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'survey','action'=>'index'])=>__lang('surveys'),
            adminUrl(['controller'=>'survey','action'=>'sessions','id'=>$id])=>__lang('Sessions/Courses'),
            '#'=>__lang('add').' '.__lang('sessions-courses')
        ];
        return view('admin.survey.addsession',$output);
    }

    public function editsession(Request $request,$id){

        $sessionSurveyTable = new SessionSurveyTable();
        $row = $sessionSurveyTable->getRecord($id);
        $testTable = new SurveyTable();
        $testRow = $testTable->getRecord($row->survey_id);
        $form = $this->getSessionTestForm();
        $output = [];
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();

                $sessionSurveyTable->update($data,$id);
                session()->flash('flash_message',__lang('course-saved'));
                return adminRedirect(['controller'=>'survey','action'=>'sessions','id'=>$testRow->id]);


            }
            else{
                $output['flash_message']= $this->getFormErrors($form);
            }
        }
        else{
            $data = getObjectProperties($row);

            $form->setData($data);

        }

        $output['form'] = $form;
        $output['pageTitle'] = __lang('edit-course-for').' '.$testRow->name;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'survey','action'=>'index'])=>__lang('surveys'),
            adminUrl(['controller'=>'survey','action'=>'sessions','id'=>$id])=>__lang('Sessions/Courses'),
            '#'=>__lang('edit').' '.__lang('sessions-courses')
        ];
        $viewModel = viewModel('admin',__CLASS__,'addsession',$output);

        return $viewModel;
    }

    public function deletesession(Request $request,$id){

        $testTable = new SurveyTable();
        $sessionSurveyTable= new SessionSurveyTable();
        $row = $sessionSurveyTable->getRecord($id);
        $testRow = $testTable->getRecord($row->survey_id);
        if($testRow->admin_id==$this->getAdminId() || GLOBAL_ACCESS){
            $sessionSurveyTable->deleteRecord($id);
            session()->flash('flash_message',__lang('Record deleted'));
        }
        else{
            session()->flash('flash_message','You do not have permission to do this');
        }

      return  back();

    }

    private function getSessionTestForm(){
        $form = new BaseForm();

        //get all sessions for user
        $sessionTable = new SessionTable();
        $sessions = $sessionTable->getPaginatedRecords(true);
        $sessions->setCurrentPageNumber(1);
        $sessions->setItemCountPerPage(500);
        $options=array();
        foreach ($sessions as $row)
        {
            $options[$row->id]=$row->name;
        }

        $sessionInstructorTable = new SessionInstructorTable();
        $rowset = $sessionInstructorTable->getAccountRecords(ADMIN_ID);
        foreach($rowset as $row){
            $options[$row->course_id] = $row->name;
        }

        $form->createSelect('course_id', __lang('Session/Course'), $options);
        $form->get('course_id')->setAttribute('class','form-control select2');



        $form->setInputFilter($this->getSessionTestFilter());
        return $form;


    }

    private function getSessionTestFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'course_id',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        return $filter;

    }


    public function importquestions(Request $request,$id){

        $testQuestionTable = new SurveyQuestionTable();

        $lastSortOrder = $testQuestionTable->getLastSortOrder($id);

        if(request()->isMethod('post')){
            $post = request()->all();
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
            $imported=0;
            foreach($all_rows as $value){


                $question = $value['Question'];

                if(empty($question)){
                    continue;
                }
                $options = $value['Options'];


                //create new question
                $testQuestion = new SurveyQuestion();
                $testQuestion->question = trim($question);
                if(!empty($lastSortOrder)){
                    $lastSortOrder++;
                    $testQuestion->sort_order = $lastSortOrder;
                }
                else{
                    $testQuestion->sort_order = 0 ;
                }

                $testQuestion->survey_id = $id;
                $testQuestion->save();
                $imported++;
                //get options
                $optionEntries= explode(',',$options);
                $count =0;
                foreach ($optionEntries as $optionValue){

                    if(!empty($optionValue)){
                        $count++;
                        $testOption=new SurveyOption();
                        $testOption->survey_question_id= $testQuestion->id;
                        $testOption->option = trim($optionValue);

                        $testOption->save();
                    }

                }


            }

            session()->flash('flash_message',__lang('questions-imported',['count'=>$imported]));
            return back();

        }



    }

    public function exportquestions(Request $request,$id){

        $survey =  Survey::find($id);

        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die(__lang('unable-to-open'));

        $columns = array(__lang('Question'),__lang('Options'));
        fputcsv($myfile,$columns);

        foreach ($survey->surveyQuestions()->orderBy('sort_order')->get() as $surveyQuestion){

            $data = [];
            $data[0] = strip_tags($surveyQuestion->question);

            $optionCount = 0;
            $correct = 0;
            $optionArray = [];
            foreach ($surveyQuestion->surveyOptions as $surveyOption){
                $optionCount++;
                $optionArray[] = $surveyOption->option;

            }
            $data[1] = implode(',',$optionArray);
            fputcsv($myfile,$data);
        }

        fclose($myfile);
        header('Content-type: text/csv');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.safeUrl($survey->name).'_questions_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();

    }


    public function send(Request $request,$id){

        $table = new SessionSurveyTable();
        $sessionSurvey = $table->getRecord($id);
        $sessionID = $sessionSurvey->course_id;
        $session = Course::find($sessionID);
        $count = 0;


        foreach($session->studentCourses as $student){
            try{
                $this->mailSurvey($sessionSurvey->survey_id,$student->student_id);
                $count++;
            }
            catch(\Exception $ex){

            }


        }
        session()->flash('flash_message',__lang('survey-sent-msg',['count'=>$count]));
        return back();
    }


    public function report(Request $request,$id){
        $survey = Survey::find($id);

        return view('admin.survey.report',[
            'pageTitle'=>__lang('survey-report').': '.$survey->name,
            'survey'=>$survey,
            'controller'=>$this
        ]);
    }


    public function getOptionPercent($id){

        $surveyOption = SurveyOption::find($id);
        $questionID = $surveyOption->survey_question_id;

        $table = new SurveyResponseOptionTable();
        $totalForOption = $table->getOptionCount($id);

        $totalForQuestion = $table->getQuestionCount($questionID);

        if($totalForQuestion < 1){
            $totalForQuestion = 1;
        }

        $percentage = ($totalForOption/$totalForQuestion) * 100;

        $percentage = round($percentage);
        return $percentage;
    }
}
