<?php

namespace App\Http\Controllers\Api;

use App\Course;
use App\Http\Controllers\Controller;
use App\ForumParticipant;
use App\ForumPost;
use App\ForumTopic;
use App\Lecture;
use App\Lib\ForumTrait;
use App\Student;
use App\User;
use App\V2\Model\ForumParticipantTable;
use App\V2\Model\ForumTopicTable;
use App\V2\Model\SessionTable;
use App\V2\Model\StudentSessionTable;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Request;
use App\Lib\HelperTrait;

class ForumController extends Controller
{
    use HelperTrait;
    use ForumTrait;
    public function forumSessions(Request $request)
    {

        $forumTopicsTable = new ForumTopicTable();
        $params = $request->all();

        $page = !empty($params['page']) ? $params['page'] : 1;

        $rowsPerPage = 30;

        $studentSessionTable = new StudentSessionTable();
        $studentId = $this->getApiStudentId();

        $total = $studentSessionTable->getTotalStudentForumRecords($studentId);

        $totalPages = ceil($total / $rowsPerPage);
        $records = [];

        if ($page <= $totalPages) {
            $paginator = $studentSessionTable->getStudentForumRecords(true, $studentId);
            $paginator->setCurrentPageNumber((int)$page);
            $paginator->setItemCountPerPage($rowsPerPage);

            foreach ($paginator as $row) {
                $nrow = apiCourse(Course::find($row->course_id));
                $nrow->total_topics = Course::find($row->course_id)->forumTopics()->count();

                $records[] = $nrow;
            }

        }

        return jsonResponse([
            'total_pages' => $totalPages,
            'current_page' => $page,
            'total' => $total,
            'rows_per_page' => $rowsPerPage,
            'records' => $records,
        ]);

    }

    public function forumTopics(Request $request){

        $params = $request->all();

        $isValid = $this->validateGump($params,[
            'course_id'=>'required'
        ]);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        $sessionId = $params['course_id'];

        $this->validateAccess($sessionId);

        $lectureId = @$params['lecture_id'];
        $session = Course::find($sessionId);

        if(!empty($lectureId)){
            $topics = $session->forumTopics()->where('lecture_id',$lectureId)->orderBy('id','desc')->paginate(20);

        }
        else{
            $topics = $session->forumTopics()->orderBy('id','desc')->paginate(20);
        }

        $data['lecture'] = Lecture::find($lectureId);

        $data['topics'] = $topics;

        if($topics->count()==0){
            $data['message'] = __lang('no-topics');
        }



        $array = $topics->toArray();

        foreach($array['data'] as $key=>$value){

            $user = User::find($value['user_id']);

            if($user){

                $value['owner'] = [
                    'first_name'=>$user->name,
                    'last_name'=>$user->last_name,
                    'picture'=>!empty($user->picture)?$user->picture:'img/user.png'
                ];

                //unset($value['owner']['api_token'],$value['owner']['password'],$value['owner']['token_expires'],$value['owner']['token_expires']);
            }
            else{
                $value['owner'] = null;
            }

            if($user->role_id==2 && $this->getApiStudent()->user_id==$value['user_id']){
                $value['can_delete']= true;
            }
            else{
                $value['can_delete']= false;
            }

            $value['total_posts'] = ForumTopic::find($value['id'])->forumPosts()->count();
            $value['forum_topic_id'] = $value['id'];
            $value['session_id'] = $value['course_id'];
            $value['topic_title'] = $value['title'];
            $value['created_on'] = stamp($value['created_at']);
             $array['data'][$key] = $value;
        }



        return jsonResponse($array);

    }

    private function validateAccess($sessionId){
        $studentSessionTable = new StudentSessionTable();
        $sessionTable = new SessionTable();
        $session = $sessionTable->getRecord($sessionId);
        if(!$studentSessionTable->enrolled($this->getApiStudentId(),$sessionId) && $session->enable_forum==1 && $session->enabled==1){

            return jsonResponse([
                'status'=>false,
                'msg'=>'Forum is unavailable for this session/course'
            ]);

        }
    }

    public function getForumTopic(Request $request,$id){
        $output =  [];
        $row = ForumTopic::find($id);
        $row->forum_topic_id = $row->id;
        $row->topic_title = $row->title;
        $row->session_id = $row->course_id;
        $output['details'] = $row;

        $output['total_posts']= ForumTopic::find($id)->forumPosts()->count();

        return jsonResponse($output);

    }

    public function createForumTopic(Request $request){
        $data = $request->all();

        $isValid= $this->validateGump($data,[
            'topic_title'=>'required',
            'message'=>'required',
            'session_id'=>'required'
        ]);

        if(!$isValid){
            return jsonResponse(['status'=>false,'msg'=>$this->getValidationErrors()]);
        }

        $this->validateAccess($data['session_id']);

        $id = $data['session_id'];


            $userId= $this->getApiStudent()->user_id;
            $forumTopic = ForumTopic::create([
                'title'=>$data['topic_title'],
                'user_id'=>$userId,
                'course_id'=>$id
            ]);



            $message = $data['message'];
            $forumTopic->forumPosts()->create([
                'message'=>$message,
                'user_id'=>$userId
            ]);

            $fpTable = new ForumParticipantTable();
            $fpTable->updateParticipant($forumTopic->id,$userId,'s');





        return jsonResponse([
            'status'=>true,
            //         'topic'=> $forumTopic,
            //         'post'=>$post
        ]);



    }

    public function deleteForumTopic(Request $request,$id){

        $forumTopic = ForumTopic::findOrFail($id);

        $student = $this->getApiStudent();
        if($student->user_id == $forumTopic->user_id){
            $forumTopic->delete();
            return jsonResponse([
                'status'=>true
            ]);
        }
        else{
            return jsonResponse([
                'status'=>false,
                'msg'=>__lang('error-try-again')
            ]);
        }

    }

    public function getForumPosts(Request $request){
       
        $params = $request->all();


        $this->validateParams($params,[
            'forum_topic_id'=>'required'
        ]);


        $id = $params['forum_topic_id'];

        $forumTopic = ForumTopic::find($id);
        $sessionId = $forumTopic->course_id;
        $this->validateAccess($sessionId);

        $data = [];
        $data['status'] = true;
        if(isset($request->limit) ){

            $posts = $forumTopic->forumPosts();
            if(isset($request->last_id) && !empty($request->last_id)){
                $posts = $posts->where('id','<',$request->last_id);
            }


            $data['posts']['data'] = $posts->orderBy('id','desc')->limit($request->limit)->get()->toArray();


        }
        else{
            $data['posts'] = $forumTopic->forumPosts()->paginate(70)->toArray();
        }



        foreach($data['posts']['data'] as $key=>$value){

            $user = User::find($value['user_id']);

            if($user){
                    if(empty($user->picture)){
                        $user->picture ='img/user.png';
                    }
                $owner = [
                    'first_name'=>$user->name,
                    'last_name'=>$user->last_name,
                    'picture'=>$user->picture
                ];

                //unset($value['owner']['api_token'],$value['owner']['password'],$value['owner']['token_expires'],$value['owner']['token_expires']);
            }
            else{
                $owner = null;
            }

            if(isset($request->text_only)){
                $data['posts']['data'][$key]['message'] = strip_tags(br2nl($data['posts']['data'][$key]['message']));
            }

            $data['posts']['data'][$key]['forum_post_id'] = $data['posts']['data'][$key]['id'];
            $data['posts']['data'][$key]['post_created_on'] = stamp($data['posts']['data'][$key]['created_at']);
            $data['posts']['data'][$key]['owner'] = $owner;
        }

        //check for participant

        $data['forum_topic'] = [
            'topic_title'=>$forumTopic->title,
            'topic_owner'=>$forumTopic->user_id,
            'forum_topic_id'=>$forumTopic->id,
            'session_id'=>$forumTopic->course_id,
            'session_name'=>$forumTopic->course->name
        ];


        return jsonResponse($data);



    }

    public function createForumPost(Request $request)
    {
        $post = $request->all();

        $this->validateParams($post, [
            'forum_topic_id' => 'required',
            'message' => 'required'
        ]);

        ini_set('post_max_size', '5M');
        $id = $post['forum_topic_id'];
        $topic = ForumTopic::find($id);
        $this->validateAccess($topic->course_id);


        $message = nl2br($post['message']);

        $student = $this->getApiStudent();
        $reply = new ForumPost();
        $reply->forum_topic_id = $id;
        $reply->message = $message;
        $reply->user_id = $student->user_id;
        if (!empty($post['post_reply_id'])) {
            $reply->post_reply_id = $post['post_reply_id'];
        }
        try {
            $reply->save();
        } catch (\Exception $ex) {

            return jsonResponse([
                'status' => false,
                'msg' => 'An error occurred.'
            ]);
        }


        $fpTable = new ForumParticipantTable();
        $fpTable->updateParticipant($id, $student->user_id, 's');
        $this->notifyParticipants($id,true,$this->getBaseApiUrl($request));

        return jsonResponse([
            'status' => true,
            'msg' => __lang('reply-saved!'),
            'reply'=>$reply
        ]);

    }
}
