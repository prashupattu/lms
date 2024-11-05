<?php

namespace App\Http\Controllers\Api;

use App\Admin;
use App\Http\Controllers\Controller;
use App\Account;
use App\Discussion;
use App\Student;
use App\User;
use App\V2\Model\AccountsTable;
use App\V2\Model\DiscussionAccountTable;
use App\V2\Model\DiscussionReplyTable;
use App\V2\Model\DiscussionTable;
use App\V2\Model\SessionTable;
use App\V2\Model\StudentSessionTable;
use App\V2\Model\StudentTable;
use Illuminate\Http\Request;
use App\Lib\HelperTrait;
use Psr\Http\Message\ResponseInterface as Response;

class DiscussionController extends Controller
{

    use HelperTrait;

    public function options(Request $request){
        //get list of recipients
        $studentSessionTable = new StudentSessionTable();
        $rowset = $studentSessionTable->getSessionInstructors($this->getApiStudentId());
        $recipients = [];
        //  $recipients[]['admins'] = 'Administrators';
        $recipients[] = [
            'id'=>'admins',
            'name'=>'Administrators'
        ];

        $accounts= [];
        foreach($rowset as $row){
            if(!empty($row->enable_discussion)){
                $accounts[$row->admin_id]= $row->first_name.' '.$row->last_name.' ('.$row->name.')';


            }

        }

        foreach($accounts as $key=>$value){
            $recipients[] = [
                'id'=>$key,
                'name'=>$value
            ];
        }

        //get list of sessions
        $rowset = $studentSessionTable->getStudentRecords(false,$this->getApiStudentId());
        $sessions = [];
        foreach($rowset as $row){
            if(!empty($row->enable_discussion)){
                //  $sessions[$row->session_id] = $row->session_name;
                $sessions[]=[
                    'id'=>  $row->course_id,
                    'name'=>$row->name
                ];
            }

        }

        return jsonResponse(
            [
                'recipients'=>$recipients,
                'courses'=>$sessions
            ]
        );


    }

    public function discussions(Request $request){

        $params = $request->all();

        $lectureId = empty($params['lecture_id'])? null:$params['lecture_id'];
        $sessionId = empty($params['session_id'])? null:$params['session_id'];

        $table = new DiscussionTable();
        $discussionAccountTable = new DiscussionAccountTable();
        $sessionTable = new SessionTable();

        $query = Student::find($this->getApiStudentId())->discussions()->orderBy('id','desc');

        if(!empty($lectureId)){
            $query->where('lecture_id',$lectureId);
        }

        if(!empty($sessionId)){
            $query->where('course_id',$sessionId);
        }

        $rowset = $query->paginate(30);


        $output = $rowset->toArray();

        foreach($output['data'] as $key=>$value){

            //get the id
            $id = $value['id'];
            //get accounts for it
            $accounts = $discussionAccountTable->getDiscussionAccounts($id)->toArray();
            foreach($accounts as $key1=>$value1){
                $accounts[$key1]['picture'] = Admin::find($value1['admin_id'])->user->picture;
                $accounts[$key1]['account_id'] = $accounts[$key1]['admin_id'];
                $accounts[$key1]['first_name'] = $accounts[$key1]['name'];
                $accounts[$key1]['discussion_account_id'] = $accounts[$key1]['discussion_id'];
                unset($accounts[$key1]['email']);
            }

            $output['data'][$key]['recipients'] = $accounts;
            $output['data'][$key]['session_id'] = $output['data'][$key]['course_id'];
            $output['data'][$key]['discussion_id'] = $output['data'][$key]['id'];
            $output['data'][$key]['created_on'] = stamp($output['data'][$key]['created_at']);

            $output['data'][$key]['replies'] = Discussion::find($id)->discussionReplies()->count();
        }

        return jsonResponse($output);




    }

    public function getDiscussion(Request $request,$id){


        $discussion = apiDiscussion(Discussion::find($id));

        $output = [];
        $discussionAccountTable = new DiscussionAccountTable();
        $accounts = $discussionAccountTable->getDiscussionAccounts($id)->toArray();

        foreach($accounts as $key=>$value){
            $accounts[$key]['picture'] = Admin::find($value['admin_id'])->user->picture;
            $accounts[$key]['first_name'] = $accounts[$key]['name'];
            unset($accounts[$key]['email']);
        }

        $output['details'] = $discussion->toArray();

        $studentTable = new StudentTable();

        //get student info
        $output['details']['student'] = $studentTable->getRecord($discussion->student_id);
        $output['details']['student']->first_name = $output['details']['student']->name;
        $output['recipients'] = $accounts;

        //get replies
        if(isset($request->limit) ){
            $replies = $discussion->discussionReplies();
            if(isset($request->last_id) && !empty($request->last_id)){
                $replies = $replies->where('id','<',$request->last_id);
            }


            $replies = $replies->orderBy('id','desc')->limit($request->limit)->get()->toArray();

            foreach($replies as $key=>$value){
                $user= User::find($value['user_id']);

                if($user){
                    $owner = [
                        'first_name'=>$user->name,
                        'last_name'=>$user->last_name,
                        'picture'=>$user->picture
                    ];
                }
                else{
                    $owner = null;
                }
                $replies[$key]['replied_on'] = stamp($replies[$key]['created_at']);
                $user = User::find($replies[$key]['user_id']);
                if($user){
                    $replies[$key]['user_type'] = ($user->role_id==1)? 'a':'s';
                }
                $replies[$key]['owner'] = $owner;
            }



            $output['replies'] = $replies;

            return jsonResponse($output);
        }
        else{
            $replies = $discussion->discussionReplies()->orderBy('id','asc')->paginate(70)->toArray();

            foreach($replies['data'] as $key=>$value){
                $user= User::find($value['user_id']);

                if($user){
                    $owner = [
                        'first_name'=>$user->name,
                        'last_name'=>$user->last_name,
                        'picture'=>$user->picture
                    ];
                }
                else{
                    $owner = null;
                }
                $replies['data'][$key]['replied_on'] = stamp($replies['data'][$key]['created_at']);
                $user = User::find($replies['data'][$key]['user_id']);
                if($user){
                    $replies['data'][$key]['user_type'] = ($user->role_id==1)? 'a':'s';
                }
                $replies['data'][$key]['owner'] = $owner;
            }



            $output['replies'] = $replies;

            return jsonResponse($output);
        }







    }

    public function createDiscussionReply(Request $request){

        $data = $request->all();

        $this->validateParams($data,[
            'discussion_id'=>'required',
            'reply'=>'required'
        ]);

        $table = new DiscussionReplyTable();
        $discussionTable = new DiscussionTable();
        $accountTable = new AccountsTable();

        $id = $data['discussion_id'];

        $discussionRow = $discussionTable->getRecord($id);
        $this->validateApiOwner($discussionRow);

        $data['user_id']= $this->getApiStudent()->user_id;

        $table->addRecord($data);
        $discussionTable->update(['replied'=>0],$id);
        $user = $this->getApiStudent();
        $name = $user->user->name.' '.$user->user->last_name;
        $reply = $data['reply'];
        //send notification to admins
        $subject = __lang('new-reply-for').' "'.$discussionRow->subject.'"';
        $message = __lang('discussion-reply-mail',['subject'=>$discussionRow->subject,'name'=>$name]);//'New reply for "'.$discussionRow->subject."\". $name said: <br/>".$reply;
        $rowset = $table->getRepliedAdmins($id);
        foreach($rowset as $row){
            try{

                    $this->sendEmail($row->email,$subject,$message);

            }
            catch(\Exception $ex)
            {

            }

        }

        return jsonResponse([
            'status'=>true,
            'msg'=>'Reply saved'
        ]);


    }

    public function createDiscussion(Request $request){

        $data = $request->all();;
        $this->validateParams($data,[
            'subject'=>'required',
            'question'=> 'required'
        ]);

        $discussionTable = new DiscussionTable();
        $discussionAccountTable = new DiscussionAccountTable();
        $studentSessionTable = new StudentSessionTable();

        $data = removeTags($data);
        $data['student_id'] = $this->getApiStudentId();


        if(isset($data['accounts'])){
            $accounts = $data['accounts'];
            unset($data['accounts']);
        }
        else{
            $accounts = [];
        }

        if(isset($data['session_id'])){
            $data['course_id']=$data['session_id'];
            unset($data['session_id']);
        }

        $discussionId = $discussionTable->addRecord($data);

        $title = __lang('new-question').': '.$data['subject'];
        $user = $this->getApiStudent();

        //get list of sessions
        $list = '<br/><strong>'.__lang('en-courses-sessions').'</strong>:';
        if($studentSessionTable->getTotalForStudent($this->getApiStudentId())==0){
            $list .= 'None';
        }
        else{
            $rowset = $studentSessionTable->getStudentRecords(false,$this->getApiStudentId());
            foreach($rowset as $row){
                $list.=$row->name.', ';
            }

        }
        $list= '<br/>';
        $message = __lang('new-chat-mail',['firstname'=>$user->first_name,'lastname'=>$user->last_name,'subject'=>$data['subject'],'question'=>$data['question'],'list'=>$list,'link'=>$this->getBaseApiUrl($request).'/admin']);

        $admins = 0;

        foreach($accounts as $value){
            $accountId = $value;
            if($accountId !='admins'){

                $this->notifyAdmin($accountId,$title,$message);
                $discussionAccountTable->addRecord([
                    'admin_id'=>$accountId,
                    'discussion_id'=> $discussionId
                ]);
            }
            else{
                $admins = 1;
                $this->notifyAdmins($title,$message);
            }
        }
        $discussionTable->update(['admin'=>$admins],$discussionId);

        return jsonResponse([
            'status'=>true
        ]);


    }

    public function deleteDiscussion(Request $request,$id){


        $row = Discussion::find($id);
        $this->validateApiOwner($row);

        $row->delete();
        return jsonResponse([
            'status'=>true
        ]);

    }


}
