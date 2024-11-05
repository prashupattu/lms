<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Student;
use App\StudentTest;
use App\Test;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTestOptionTable;
use App\V2\Model\StudentTestTable;
use App\V2\Model\TestGradeTable;
use App\V2\Model\TestOptionTable;
use App\V2\Model\TestQuestionTable;
use App\V2\Model\TestTable;
use Illuminate\Http\Request;
use App\Lib\HelperTrait;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;

class TestController extends Controller
{

    use HelperTrait;

    public function tests(Request $request)
    {
        $params = $request->all();

        // TODO Auto-generated NewsController::indexAction() default action
        $table = new TestTable();
        $testQuestionTable = new TestQuestionTable();
        $studentTestTable = new StudentTestTable();

        $paginator = $table->getStudentRecords($this->getApiStudent()->id);

        $currentPage = (int) (empty($params['page'])? 1 : $params['page']);
        $rowsPerPage = 30;

        $paginator->setCurrentPageNumber($currentPage);
        $paginator->setItemCountPerPage($rowsPerPage);

        $output=[];

        $output['total'] = $table->getStudentTotalRecords($this->getApiStudent()->id);
        $output['current_page'] = $currentPage;

        $totalPages = ceil($output['total']/$rowsPerPage);
        $output['total_pages'] = $totalPages;
        $output['records']=[];

        if($currentPage<=$totalPages){


            foreach($paginator as $value){
                $test = apiTest(Test::find($value->test_id));
                $test->total_questions = $test->testQuestions()->count();

                //check if student has test
                $test->total_attempts = $test->studentTests()->where('student_id',$this->getApiStudentId())->count();

                $canTake = true;
                if(empty($test->allow_multiple) && $studentTestTable->hasTest($test->test_id,$this->getApiStudentId())){
                    $canTake = false;
                }
                $test->can_take = $canTake;


                $output['records'][]= $test;
                //
            }

        }



        return jsonResponse(
            $output
        );

    }


    public function getTest(Request $request,$id)
    {

        $output = [];
        $testTable = new TestTable();

        $testRow=Test::find($id);
        $testRow->test_id = $testRow->id;
        $output['testRow'] = $testRow;

        $questionTable = new TestQuestionTable();
        $optionTable = new TestOptionTable();
        $studentTestTable = new StudentTestTable();
        $studentTestOptionTable = new StudentTestOptionTable();
        $studentSessionTable = new StudentSessionTable();

        if($studentTestTable->hasTest($id,$this->getApiStudent()->id) && empty($output['testRow']->allow_multiple)){

            return jsonResponse([
                'status'=>false,
                'message'=>__lang('test-taken-msg')
            ]);
        }


        if(!empty($testRow->private)){

            //get records for the student
            $rowset = $testTable->getStudentTestRecords($this->getApiStudent()->id,$id);
            $total = $rowset->count();


            if(empty($total)){
                return jsonResponse([
                    'status'=>false,
                    'message'=>__lang('no-test-permission')
                ]);
            }

            //now loop rowset as see if the test is opened
            $opened = false;

            foreach($rowset as $row){
                if((Carbon::parse($row->opening_date)->timestamp < time() || Carbon::parse($row->opening_date)->timestamp==0)){
                    $opened=true;
                }

            }

            $closed = false;

            foreach($rowset as $row){
                if(stamp($row->closing_date) > time() || stamp($row->closing_date)==0){
                    $closed = true;
                }

            }

            if(!($opened && $closed)){

                return jsonResponse([
                    'status'=>false,
                    'message'=>__lang('test-closed')
                ]);
            }


        }


        $rowset = $questionTable->getPaginatedRecords(false,$id);
        $rowset->buffer();
        $questions = [];
        $correct = 0;
        $totalQuestions = $rowset->count();
        /*        $counter=0;
                foreach($rowset as $row)
                {
                    $questions[$counter]['question'] = $row;
                    $questions[$counter]['options'] = $optionTable->getOptionRecords($row->test_question_id)->toArray();
                    foreach( $questions[$counter]['options'] as $key=>$value){
                        unset($questions[$counter]['options'][$key]['is_correct']);
                    }

                    $counter++;
                }
        */

        $output['totalQuestions'] = $totalQuestions;
        $testRow->total_questions = $totalQuestions;

        return jsonResponse($testRow);
    }

    public function createStudentTest(Request $request)
    {
        $data = $request->all();

        $rules = [
            'test_id'=>'required',
        ];
        $isValid = $this->validateGump($data,$rules);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        $studentTestTable = new StudentTestTable();



        $id = $data['test_id'];

        $test = Test::find($id);

        if(empty($test->allow_multiple) && $studentTestTable->hasTest($test->id,$this->getApiStudentId())){
            return jsonResponse([
                'status'=>false,
                'msg'=>__lang('test-taken-msg')
            ]);

        }


        if(!empty($test->private)){
            $testTable = new TestTable();
            //get records for the student
            $rowset = $testTable->getStudentTestRecords($this->getApiStudentId(),$id);
            $total = $rowset->count();


            if(empty($total)){

                return jsonResponse([
                    'status'=>false,
                    'msg'=>__lang('no-test-permission')
                ]);

            }

            //now loop rowset as see if the test is opened
            $opened = false;

            foreach($rowset as $row){
                if(($row->opening_date < Carbon::now() || $row->opening_date==0)){
                    $opened=true;
                }

            }

            $closed = false;

            foreach($rowset as $row){
                if($row->closing_date > Carbon::now() || $row->closing_date==0){
                    $closed = true;
                }

            }

            if(!($opened && $closed)){

                return jsonResponse([
                    'status'=>false,
                    'msg'=>__lang('test-closed')
                ]);
            }


        }








        $studentTestId = $studentTestTable->addRecord([
            'student_id'=>$this->getApiStudent()->id,
            'test_id'=>$id,
            'score'=>0
        ]);

        $output = ['id'=>$studentTestId,'status'=>true];

        //get test questions
        $questionTable = new TestQuestionTable();
        $optionTable = new TestOptionTable();

        $rowset = $questionTable->getPaginatedRecords(false,$id);
        $rowset->buffer();
        $questions = [];
        $correct = 0;
        $totalQuestions = $rowset->count();
        $counter=0;
        foreach($rowset as $row)
        {
            $row->test_question_id = $row->id;
            $questions[$counter]['question'] = $row;
            $questions[$counter]['options'] = $optionTable->getOptionRecords($row->id)->toArray();
            foreach( $questions[$counter]['options'] as $key=>$value){
                $questions[$counter]['options'][$key]['id'] = ''.$questions[$counter]['options'][$key]['id'].'';
                $questions[$counter]['options'][$key]['test_option_id'] = $questions[$counter]['options'][$key]['id'];

                unset($questions[$counter]['options'][$key]['is_correct']);
            }

            $counter++;
        }


        $output['total_questions'] = $totalQuestions;
        $output['questions'] = $questions;



        return jsonResponse($output);

    }

    public function updateStudentTest(Request $request,$id){

        $data = $request->all();



        $studentTestId = $id;
        $testId = StudentTest::find($studentTestId)->test_id;
        $output = [];
        $testTable = new TestTable();
        $test = $testTable->getRecord($testId);

        $output['testRow'] = $test;
        //check if student has taken test before



        $questionTable = new TestQuestionTable();
        $optionTable = new TestOptionTable();
        $studentTestTable = new StudentTestTable();
        $studentTestOptionTable = new StudentTestOptionTable();




        $rowset = $questionTable->getPaginatedRecords(false,$testId);
        $rowset->buffer();
        $questions = [];
        $correct = 0;
        $totalQuestions = $rowset->count();
        foreach($rowset as $row)
        {
            $questions[$row->id]['question'] = $row;
            $questions[$row->id]['options'] = $optionTable->getOptionRecords($row->id);
        }


        $row = $studentTestTable->getRecord($studentTestId);
        $this->validateApiOwner($row);

        foreach($rowset as  $row)
        {
            if(!empty($data['question_'.$row->id]))
            {
                $optionId = $data['question_'.$row->id];
                $studentTestOptionTable->addRecord([
                    'student_test_id'=>$studentTestId,
                    'test_option_id'=>$optionId
                ]);
                //check if option is correct
                $optionRow = $optionTable->getRecord($optionId);
                if($optionRow->is_correct==1){
                    $correct++;
                }

            }
        }

        //calculate score
        $score = ($correct/$totalQuestions)  * 100;
        $score = round($score,2);
        //update
        $studentTestTable->update(['score'=>$score],$studentTestId);

        $studentTestRecord = StudentTest::find($studentTestId)->toArray();
        $studentTestRecord['student_test_id'] = $studentTestRecord['id'];
        $studentTestRecord['status'] = true;



        return jsonResponse($studentTestRecord);

    }

    public function getStudentTest(Request $request,$id){


        $row = StudentTest::find($id);
        $row->student_test_id = $row->id;
        $data = [];
        $data['details'] = $row->toArray();

        $data['test'] = $row->test->toArray();

        return jsonResponse($data);

    }


    public function studentTests(Request $request){

        $params = $request->all();

        $isValid= $this->validateGump($params,[
            'test_id'=>'required'
        ]);

        if(!$isValid){

            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);

        }

        $id =  $params['test_id'];
        $test = Test::findOrFail($id);
        if(empty($test->show_result)){

            return jsonResponse(['status'=>false,'msg'=>__lang('not-allowed-result')]);
        }

        $rowsPerPage= 30;
        //get test
        $studentId = $this->getApiStudentId();
        $student = Student::find($studentId);
        $rowset = $student->studentTests()->orderBy('created_at','desc')->where('test_id',$id)->paginate($rowsPerPage);

        $gradeTable = new TestGradeTable();
        $output = [];
        $output['rows_per_page'] = $rowsPerPage;
        $output['current_page'] = empty($params['page']) ? 1:$params['page'];
        $output['total'] = $student->studentTests()->count();
        foreach($rowset as $row){
            $data = $row->toArray();
            $data['grade'] = $gradeTable->getGrade($row->score);
            $output['records'][] = $data;
        }

        return jsonResponse($output);
    }


}
