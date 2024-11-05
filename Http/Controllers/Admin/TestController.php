<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Lib\BaseForm;
use App\Lib\HelperTrait;
use App\Test;
use App\TestOption;
use App\TestQuestion;
use App\V2\Form\TestFilter;
use App\V2\Form\TestForm;
use App\V2\Form\TestQuestionFilter;
use App\V2\Form\TestQuestionForm;
use App\V2\Model\SessionInstructorTable;
use App\V2\Model\SessionTable;
use App\V2\Model\SessionTestTable;
use App\V2\Model\StudentTestOptionTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\TestOptionTable;
use App\V2\Model\TestQuestionTable;
use App\V2\Model\TestTable;
use Illuminate\Http\Request;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\InputFilter\InputFilter;

class TestController extends Controller
{
    use HelperTrait;

    public function index(Request $request){

        $table = new TestTable();
        $questionTable = new TestQuestionTable();
        $studentTestTable = new StudentTestTable();
        $filter = request()->get('filter');



        if (empty($filter)) {
            $filter=null;
        }
        $paginator = $table->getPaginatedRecords(true,null,$filter);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Tests'),
            'questionTable'=>$questionTable,
            'studentTestTable'=>$studentTestTable
        ));
    }


    public function add(Request $request)
    {
        $output = array();
        $table = new TestTable();
        $form = new TestForm(null,$this->getServiceLocator());
        $filter = new TestFilter();

        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();

            $form->setData($data);
            if ($form->isValid()) {

                $array = removeNull($form->getData());

                $array[$table->getPrimary()]=0;
                $id= $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Record Added!');
                session()->flash('flash_message',__lang('test-added'));
                return adminRedirect(['controller'=>'test','action'=>'questions','id'=>$id]);
            }
            else{
                $output['flash_message'] = __lang('save-failed-msg');

            }

        }

        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Test');
        $output['action']='add';
        $output['id']=null;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);
    }

    public function edit(Request $request,$id){
        $output = array();
        $table = new TestTable();
        $form = new TestForm(null,$this->getServiceLocator());
        $filter = new TestFilter();


        $row = $table->getRecord($id);
        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();

            $form->setData($data);
            if ($form->isValid()) {

                $array = removeNull($form->getData());
                $array[$table->getPrimary()]=$id;
                $table->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Changes Saved!');
                flashMessage(__lang('Changes Saved!'));
                $row = $table->getRecord($id);
                return redirect()->route('admin.test.index');
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
        $output['pageTitle']= __lang('Edit Test');
        $output['row']= $row;
        $output['action']='edit';

        $viewModel = viewModel('admin',__CLASS__,'add',$output);
        return $viewModel ;

    }

    public function delete(Request $request,$id)
    {
        $table = new TestTable();
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'test','action'=>'index'));
    }


    public function questions(Request $request,$id){
        $testTable = new TestTable();

        $table = new TestQuestionTable();
        $optionTable = new TestOptionTable();
        $row = $testTable->getRecord($id);
        $paginator = $table->getPaginatedRecords(true,$id);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Test Questions').': '.$row->name,
            'id'=>$id,
            'optionTable'=>$optionTable,
            'page'=>(int)request()->get('page', 1)
        ));
    }


    public function addquestion(Request $request,$id)
    {
        $testQuestionTable = new TestQuestionTable();
        $testOptionTable = new TestOptionTable();
        if(request()->isMethod('post'))
        {
            $data = request()->all();
            if(!empty($data['question'])){

                $dbData= [
                    'test_id'=>$id,
                    'question'=>$data['question'],
                    'sort_order'=>$data['sort_order']
                ];

                $questionId = $testQuestionTable->addRecord($dbData);
                session()->flash('flash_message',__lang('Question added'));
                //correct answer
                $correct = $data['correct_option'];
                for($i=1;$i<=5;$i++){

                    if(!empty($data['option_'.$i])){

                        $optionData = [
                            'test_question_id'=>$questionId,
                            'option'=> trim($data['option_'.$i])
                        ];

                        if($i==$correct){
                            $optionData['is_correct'] = 1;
                        }
                        else{
                            $optionData['is_correct'] = 0;
                        }

                        $testOptionTable->addRecord($optionData);
                    }


                }


            }
        }

        return back();
        // return adminRedirect(['controller'=>'test','action'=>'questions','id'=>$id]);
    }

    public function editquestion(Request $request,$id){

        $output = [];
        $questionTable = new TestQuestionTable();
        $optionTable = new TestOptionTable();
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
                return adminRedirect(['controller'=>'test','action'=>'questions','id'=>$row->test_id]);
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
            adminUrl(['controller'=>'test','action'=>'index'])=>__lang('Tests'),
            adminUrl(['controller'=>'test','action'=>'questions','id'=>$row->test_id])=>__lang('Test Questions'),
            '#'=>__lang('Edit Question')
        ];
        return view('admin.test.editquestion',$output);
    }

    public function addoptions(Request $request)
    {
        $id= request()->get('id');
        $testOptionTable = new TestOptionTable();
        if(request()->isMethod('post'))
        {
            $data = request()->all();


            //correct answer
            $correct = $data['correct_option'];
            if(!empty($correct)){
                $testOptionTable->clearIsCorrect($id);
            }
            $count = 0;
            for($i=1;$i<=5;$i++){

                if(!empty($data['option_'.$i])){

                    $optionData = [
                        'test_question_id'=>$id,
                        'option'=> trim($data['option_'.$i])
                    ];

                    if($i==$correct){
                        $optionData['is_correct'] = 1;
                    }
                    else{
                        $optionData['is_correct'] = 0;
                    }

                    $testOptionTable->addRecord($optionData);
                    $count++;

                }


            }
            session()->flash('flash_message',$count.' '.__lang('options added'));


        }

        return adminRedirect(['controller'=>'test','action'=>'editquestion','id'=>$id]);

    }

    public function editoption(Request $request,$id){

        $testOptionTable = new TestOptionTable();
        $row = $testOptionTable->getRecord($id);
        $questionId = $row->test_question_id;

        if(request()->isMethod('post'))
        {
            $data = request()->all();
            if(!empty($data['option'])) {

                $dbData = [];
                if (!empty($data['is_correct']))
                {
                    $testOptionTable->clearIsCorrect($questionId);
                    $dbData['is_correct']=$data['is_correct'];
                }
                $dbData['option']=$data['option'];
                $testOptionTable->update($dbData,$id);
                session()->flash('flash_message',__lang('Option saved'));
            }
            else{
                session()->flash('flash_message',__lang('survey-save-failed'));
            }
            return adminRedirect(['controller'=>'test','action'=>'editquestion','id'=>$questionId]);
        }

        $option = new Text('option');
        $option->setAttributes(['class'=>'form-control']);
        $option->setValue($row->option);

        $select = new Select('is_correct');
        $select->setAttribute('class','form-control');
        $select->setValueOptions([1=>'Yes',0=>'No']);
        $select->setValue($row->is_correct);

        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,['row'=>$row,'option'=>$option,'select'=>$select,'id'=>$id]);

        return $viewModel;
    }

    public function deletequestion(Request $request,$id)
    {
        $table = new TestQuestionTable();
        $row = $table->getRecord($id);
        $testId = $row->test_id;
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'test','action'=>'questions','id'=>$testId));
    }

    public function deleteoption(Request $request)
    {
        $table = new TestOptionTable();
        $id = request()->get('id');
        $row = $table->getRecord($id);
        $questionId = $row->test_question_id;
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'test','action'=>'editquestion','id'=>$questionId));
    }

    public function duplicate(Request $request,$id)
    {
        $testTable = new TestTable();
        $testQuestionTable = new TestQuestionTable();
        $testOptionTable = new TestOptionTable();

        //get all questions
        $test = $testTable->getRecord($id);
        $questions = $testQuestionTable->getPaginatedRecords(false,$test->id)->toArray();
        $options = [];
        foreach($questions as $question){
            $options[$question['id']] = $testOptionTable->getOptionRecords($question['id'])->toArray();
        }


        $testData = getObjectProperties($test);
        unset($testData['id']);

        $newId = $testTable->addRecord($testData);

        foreach($questions as $question)
        {
            $oldQuestionId=$question['id'];
            $question['test_id']= $newId;
            unset($question['id']);
            $questionId=  $testQuestionTable->addRecord($question);
            foreach($options[$oldQuestionId] as $option){
                $option['test_question_id'] = $questionId;
                unset($option['id']);
                $testOptionTable->addRecord($option);
            }

        }

        session()->flash('flash_message',__lang('Test duplicated'));
        return adminRedirect(['controller'=>'test','action'=>'index']);



    }


    public function results(Request $request,$id)
    {

        $testTable = new TestTable();
        $table = new StudentTestTable();

        $filter = request()->get('filter');
        $startDate = request()->get('start', null) ? getDateString(request()->get('start', null)):null;
        $endDate = request()->get('end', null) ? getDateString(request()->get('end', null)):null ;

        if (empty($filter)) {
            $filter=null;
        }


        $row= $testTable->getRecord($id);
        $paginator = $table->getPaginatedRecords(true,$id,$filter,$startDate,$endDate);

        $testTotal = $table->getTotalForTest($id,$startDate,$endDate);
        $totalPassed = $table->getTotalPassed($id,$row->passmark,$startDate,$endDate);
        $totalFailed= $testTotal - $totalPassed;
        $average = $table->getAverageScore($id,$startDate,$endDate);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);

        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Test results').': '.$row->name,
            'row'=>$row,
            'passed'=>$totalPassed,
            'failed'=>$totalFailed,
            'average'=>$average,
            'start'=>request()->get('start', null),
            'end'=>request()->get('end', null),
            'testID'=>$row->id
        ));
    }

    public function deleteresult(Request $request,$id)
    {
        $studentTestTable = new StudentTestTable();

        $row = $studentTestTable->getRecord($id);
        $testId = $row->test_id;
        try{
            $studentTestTable->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'test','action'=>'results','id'=>$testId));
    }

    public function testresult(Request $request,$id)
    {
        $studentTestTable = new StudentTestTable();
        $studentOptionTable = new StudentTestOptionTable();
        $row = $studentTestTable->getRecord($id);
        $rowset = $studentOptionTable->getTestRecords($id);
        $data = ['row'=>$row,'rowset'=>$rowset];
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$data);

        return $viewModel;

    }

    public function exportresult(Request $request,$id){

        $type = $_GET['type'];
        $studentTestTable = new StudentTestTable();
        $testTable = new TestTable();
        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $startDate = request()->get('start', null) ? getDateString(request()->get('start', null)):null;
        $endDate = request()->get('end', null) ? getDateString(request()->get('end', null)):null ;


        $myfile = fopen($file, "w") or die("Unable to open file!");

        $testRow = $testTable->getRecord($id);

        if($type=='pass')
        {
            $totalRecords = $studentTestTable->getTotalPassedForTest($id,$testRow->passmark,$startDate,$endDate);

        }
        else{
            $totalRecords = $studentTestTable->getTotalFailedForTest($id,$testRow->passmark,$startDate,$endDate);
        }



        $rowsPerPage = 3000;
        $totalPages = ceil($totalRecords/$rowsPerPage);
        fputcsv($myfile, array(__lang('First Name'),__lang('Last Name'),__lang('Score').'%'));
        for($i=1;$i<=$totalPages;$i++){
            if($type=='pass') {
                $paginator = $studentTestTable->getPassedPaginatedRecords(true, $id,$testRow->passmark,$startDate,$endDate);
            }
            else{
                $paginator = $studentTestTable->getFailPaginatedRecords(true, $id,$testRow->passmark,$startDate,$endDate);
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

        $sessionTestTable = new SessionTestTable();
        $testTable = new  TestTable();
        $testRow = $testTable->getRecord($id);

        $rowset = $sessionTestTable->getTestRecords($id);
        return view('admin.test.sessions',[
            'pageTitle'=>__lang('test-sessions-courses').': '.$testRow->name,
            'rowset'=>$rowset,
            'id'=>$id
        ]);

    }

    public function addsession(Request $request,$id){

        $sessionTestTable = new SessionTestTable();
        $testTable = new TestTable();
        $testRow = $testTable->getRecord($id);
        $form = $this->getSessionTestForm();
        $output = [];
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();
                $data['test_id'] = $id;
                $data['opening_date']= getDateString($data['opening_date']);
                $data['closing_date']= getDateString($data['closing_date']);
                $sessionTestTable->addRecord($data);
                session()->flash('flash_message',__lang('course-added-succ'));
                return adminRedirect(['controller'=>'test','action'=>'sessions','id'=>$id]);


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
            adminUrl(['controller'=>'test','action'=>'index'])=>__lang('Tests'),
            adminUrl(['controller'=>'test','action'=>'sessions','id'=>$id])=>__lang('Sessions/Courses'),
            '#'=>__lang('add').' '.__lang('sessions-courses')
        ];
        return view('admin.test.addsession',$output);
    }

    public function editsession(Request $request,$id){

        $sessionTestTable = new SessionTestTable();
        $row = $sessionTestTable->getRecord($id);
        $testTable = new TestTable();
        $testRow = $testTable->getRecord($row->test_id);
        $form = $this->getSessionTestForm();
        $output = [];
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){

                $data = $form->getData();

                $data['opening_date']= getDateString($data['opening_date']);
                $data['closing_date']=getDateString($data['closing_date']);
                $sessionTestTable->update($data,$id);
                session()->flash('flash_message',__lang('course-saved'));
                return adminRedirect(['controller'=>'test','action'=>'sessions','id'=>$testRow->id]);


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
        $output['pageTitle'] = __lang('edit-course-for').' '.$testRow->name;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'test','action'=>'index'])=>__lang('Tests'),
            adminUrl(['controller'=>'test','action'=>'sessions','id'=>$id])=>__lang('Sessions/Courses'),
            '#'=>__lang('edit').' '.__lang('sessions-courses')
        ];
        $viewModel = viewModel('admin',__CLASS__,'addsession',$output);

        return $viewModel;
    }

    public function deletesession(Request $request,$id){

        $testTable = new TestTable();
        $sessionTestTable= new SessionTestTable();
        $row = $sessionTestTable->getRecord($id);
        $testRow = $testTable->getRecord($row->test_id);
        if($testRow->admin_id==$this->getAdminId() || GLOBAL_ACCESS){
            $sessionTestTable->deleteRecord($id);
            session()->flash('flash_message',__lang('Record deleted'));
        }
        else{
            session()->flash('flash_message',__lang('no-permission'));
        }

       return back();

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

        $form->createSelect('course_id', 'Session/Course', $options);
        $form->get('course_id')->setAttribute('class','form-control select2');


        $form->createText('opening_date','Opening Date (Optional)',false,'form-control date',null,'Opening Date');
        $form->createText('closing_date','Closing Date (Optional)',false,'form-control date',null,'Closing Date');

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


    public function importquestions(Request $request,$id){


        $testQuestionTable = new TestQuestionTable();

        $lastSortOrder = $testQuestionTable->getLastSortOrder($id);

        if(request()->isMethod('post')){

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
                $correctOption = intval($value['Correct_Option_Number']);


                //create new question
                $testQuestion = new TestQuestion();
                $testQuestion->question = trim($question);
                if(!empty($lastSortOrder)){
                    $lastSortOrder++;
                    $testQuestion->sort_order = $lastSortOrder;
                }
                else{
                    $testQuestion->sort_order = 0 ;
                }

                $testQuestion->test_id = $id;
                $testQuestion->save();
                $imported++;
                //get options
                $optionEntries= explode(',',$options);
                $count =0;
                foreach ($optionEntries as $optionValue){

                    if(!empty($optionValue)){
                        $count++;
                        $testOption=new TestOption();
                        $testOption->test_question_id= $testQuestion->id;
                        $testOption->option = trim($optionValue);
                        if($count == $correctOption){
                            $testOption->is_correct = 1;
                        }
                        $testOption->save();
                    }

                }


            }

            session()->flash('flash_message',__lang('questions-imported',['count'=>$imported]));
            return back();

        }



    }

    public function exportquestions(Request $request,$id){

        $test =  Test::find($id);

        $file = "export.txt";
        if (file_exists($file)) {
            unlink($file);
        }

        $myfile = fopen($file, "w") or die(__lang('unable-to-open'));

        $columns = array(__lang('Question'),__lang('Options'),__lang('correct-option-number'));
        fputcsv($myfile,$columns);

        foreach ($test->testQuestions()->orderBy('sort_order')->get() as $testQuestion){

            $data = [];
            $data[0] = strip_tags($testQuestion->question);

            $optionCount = 0;
            $correct = 0;
            $optionArray = [];
            foreach ($testQuestion->testOptions as $testOption){
                $optionCount++;
                $optionArray[] = $testOption->option;
                if($testOption->is_correct==1){
                    $correct = $optionCount;
                }
            }
            $data[1] = implode(',',$optionArray);
            $data[2] = $correct;
            fputcsv($myfile,$data);
        }

        fclose($myfile);
        header('Content-type: text/csv');
        // It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.safeUrl($test->name).'_questions_'.date('d/M/Y').'.csv"');

        // The PDF source is in original.pdf
        readfile($file);
        unlink($file);
        exit();

    }
}
