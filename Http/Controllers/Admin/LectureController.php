<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Lecture;
use App\LecturePage;
use App\Lib\BaseForm;
use App\Lib\HelperTrait;
use App\V2\Form\LectureFilter;
use App\V2\Form\LectureForm;
use App\V2\Model\LectureFileTable;
use App\V2\Model\LecturePageTable;
use App\V2\Model\LectureTable;
use App\V2\Model\LessonTable;
use App\Video;
use Embed\Embed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laminas\Form\Element\File;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\File\Extension;
use Laminas\Validator\File\Size;

class LectureController extends Controller
{
    use HelperTrait;
    private $count =0;
    private $images = [];
    public function index(Request $request,$id) {
        // TODO Auto-generated NewssController::index(Request $request) default action
        $table = new LectureTable();
        $lessonTable = new LessonTable();
        $paginator = $table->getPaginatedRecords(true,$id);
        $lessonRow = $lessonTable->getRecord($id);

        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(30);
        return viewModel('admin',__CLASS__,__FUNCTION__,array(
            'paginator'=>$paginator,
            'pageTitle'=>__lang('Class Lectures').': '.$lessonRow->name,
            'id'=>$id,
            'lectureFileTable'=> new LectureFileTable(),
            'lecturePageTable'=> new LecturePageTable(),
            'lesson'=>$lessonRow
        ));


    }

    public function add(Request $request,$id)
    {
        $output = array();
        $lectureTable = new LectureTable();
        $form = new LectureForm(null,$this->getServiceLocator());
        $filter = new LectureFilter();

        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();
            $form->setData($data);
            if ($form->isValid()) {

                $array = $form->getData();
                $array[$lectureTable->getPrimary()]=0;
                $array['lesson_id']=$id;

                if(empty($array['sort_order'])){
                    $array['sort_order'] = $lectureTable->getNextSortOrder($id);
                }

                $lectureId = $lectureTable->saveRecord($array);
                $lectureTable->arrangeSortOrders($id);

                $output['flash_message'] = __lang('Record Added!');
                $form = new LectureForm(null,$this->getServiceLocator());
                session()->flash('flash_message',__lang('lecture-added'));
                return adminRedirect(array('controller'=>'lecture','action'=>'content','id'=>$lectureId));
            }
            else{


                $output['flash_message'] = __lang('save-failed-msg');

            }

        }

        $output['form'] = $form;
        $output['pageTitle']= __lang('Add Lecture');
        $output['action']='add';
        $output['id']=$id;
        return viewModel('admin',__CLASS__,__FUNCTION__,$output);
    }

    public function edit(Request $request,$id){
        $output = array();
        $lectureTable = new LectureTable();
        $form = new LectureForm(null,$this->getServiceLocator());
        $filter = new LectureFilter();


        $row = $lectureTable->getRecord($id);
        if (request()->isMethod('post')) {

            $form->setInputFilter($filter);
            $data = request()->all();

            $form->setData($data);
            if ($form->isValid()) {

                //add groups
                $array = $form->getData();


                $array[$lectureTable->getPrimary()]=$id;
                if(empty($array['sort_order'])){
                    $array['sort_order'] = $lectureTable->getNextSortOrder($row->lesson_id);
                }
                $lectureTable->saveRecord($array);
                //    flashMessage(__lang('Changes Saved!'));
                $output['flash_message'] = __lang('Changes Saved!');
                $row = $lectureTable->getRecord($id);
                $lectureTable->arrangeSortOrders($row->lesson_id);
                session()->flash('flash_message',__lang('Changes Saved!'));
                return adminRedirect(array('controller'=>'lecture','action'=>'index','id'=>$row->lesson_id));

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
        $output['pageTitle']= __lang('Edit Lecture');
        $output['row']= $row;
        $output['action']='edit';
        $output['customCrumbs'] = [
            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$row->lesson_id])=>__lang('Class Lectures'),
            '#'=>__lang('Edit Lecture')
        ];


        $viewModel = viewModel('admin',__CLASS__,'add',$output);
        return $viewModel ;

    }



    public function delete(Request $request,$id)
    {
        $table = new LectureTable();
        $row = $table->getRecord($id);
        $lessonId = $row->lesson_id;
        try{
            $table->deleteRecord($id);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return adminRedirect(array('controller'=>'lecture','action'=>'index','id'=>$lessonId));
    }

    public function files(Request $request,$id){

        $table = new LectureFileTable();

        $lectureTable = new LectureTable();
        $lectureRow = $lectureTable->getRecord($id);
        $rowset = $table->getDownloadRecords($id);
        $output = [];
        $output['customCrumbs'] = [
            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lectureRow->lesson_id])=>__lang('Class Lectures'),
            '#'=>__lang('Lecture Downloads')
        ];
        $output['rowset'] = $rowset;
        $output['pageTitle'] = __lang('Lecture Downloads').': '.$lectureRow->title;
        $output['id'] = $id;
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);

        return $viewModel;
    }

    public function addfile(Request $request,$id){
        $path = $request->get('path');

        $downloadFileTable = new LectureFileTable();
        $path = str_ireplace('usermedia/','',$path);
        if(!$downloadFileTable->fileExists($id,$path)){
            $downloadFileTable->addRecord([
                'lecture_id'=>$id,
                'path'=>$path,
                'enabled'=>1
            ]);
        }


        //$filesViewModel = $this->forward()->dispatch('Admin\Controller\Lecture',['action'=>'files','id'=>$id]);
        $filesViewModel = app(LectureController::class)->files($request,$id);
        return $filesViewModel;
    }

    public function removefile(Request $request,$id){

        $downloadFileTable = new LectureFileTable();
        $row = $downloadFileTable->getRecord($id);
        $downloadId = $row->lecture_id;

        $downloadFileTable->deleteRecord($id);
        //$filesViewModel = $this->forward()->dispatch('Admin\Controller\Lecture',['action'=>'files','id'=>$downloadId]);
        $filesViewModel = app(LectureController::class)->files($request,$downloadId);
        return $filesViewModel;
    }

    public function download(Request $request,$id){
        set_time_limit(86400);
        $table = new LectureFileTable();
        $row = $table->getRecord($id);
        $path = 'usermedia/'.$row->path;



        header('Content-type: '.getFileMimeType($path));

// It will be called downloaded.pdf
        header('Content-Disposition: attachment; filename="'.basename($path).'"');

// The PDF source is in original.pdf
        readfile($path);
        exit();
    }

    public function content(Request $request,$id){

        $table = new LecturePageTable();

        $lectureTable = new LectureTable();
        $lectureRow = $lectureTable->getRecord($id);
        $paginator = $table->getPaginatedRecords(true,$id);


        $paginator->setCurrentPageNumber((int)request()->get('page', 1));
        $paginator->setItemCountPerPage(1000);

        $output = [];
        $output['customCrumbs'] = [
            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lectureRow->lesson_id])=>__lang('Class Lectures'),
            '#'=>__lang('Lecture Content')
        ];
        $output['paginator'] = $paginator;
        $output['pageTitle'] = __lang('Lecture Content').': '.$lectureRow->title;
        $output['id'] = $id;
        $output['no_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        $output['display_image']= resizeImage('img/no_image.jpg', 100, 100,$this->getBaseUrl());
        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,$output);

        return $viewModel;
    }

    public function reorder(Request $request){
        if(!empty($_REQUEST['row'])){

            $counter = 1;
            foreach($_REQUEST['row'] as $id){
                $lecturePage = LecturePage::find($id);
                if($lecturePage){
                    $lecturePage->sort_order = $counter;
                    $lecturePage->save();
                    $counter++;
                }

            }

        }
        exit('done');
    }

    public function deletecontents(Request $request){
        $count = 0;
        if(request()->isMethod('post')){

            foreach(request()->all() as $key=>$value){
                if($value>0){
                    try{
                        $lecturePage = LecturePage::find($value);
                        if($lecturePage){
                            $lecturePage->delete();
                            $count++;
                        }


                    }
                    catch(\Exception $ex){

                    }

                }
            }
            session()->flash('flash_message',$count.' '.__lang('items deleted'));
        }

        return back();
    }

    public function addcontent(Request $request,$id){

        $table = new LecturePageTable();
        $form = $this->getContentForm();
        $form->setInputFilter($this->getContentFilter());

        if(request()->isMethod('post')){
            $formData = $request->all();

            $form->setData($formData);
            if($form->isValid()){
                $data = $form->getData();
                $data['lecture_id'] = $id;

                if(empty($data['sort_order'])){
                    $data['sort_order'] = $table->getNextSortOrder($id);
                }

                $data['id'] = $formData['id'];
                $table->saveRecord($data);
                $table->arrangeSortOrders($id);
                session()->flash('flash_message',__lang('Content added'));
            }
            else{
                session()->flash('flash_message',$this->getFormErrors($form));
            }
        }

        return adminRedirect(['controller'=>'lecture','action'=>'content','id'=>$id]);


    }

    public function addvideo(Request $request,$id){
        $table = new LecturePageTable();
        if(request()->isMethod('post')){
            $formData = request()->all();
            $url = trim($formData['url']);

            if(!empty($url)){

                $data = $formData;
                unset($data['url'],$data['_token']);

                $data['lecture_id'] = $id;

                if(empty($data['sort_order'])){
                    $data['sort_order'] = $table->getNextSortOrder($id);
                }


                //get link from url
                try{
                    $info = Embed::create($url);

                    if($info->type != 'video'){
                        session()->flash('flash_message',__lang('invalid-v-url-msg'));
                        back();
                    }
                    $data['title'] = $info->title;
                    $data['content']= $info->code;

                    $table->addRecord($data);
                    $table->arrangeSortOrders($id);
                    session()->flash('flash_message',__lang('Video added'));

                }
                catch(\Exception $ex){

                    session()->flash('flash_message',__lang('error-occurred-msg').': '.$ex->getMessage());

                }


            }
            else
            {
                session()->flash('flash_message',__lang('supply-valid-url'));
            }


        }

        return back();
    }

    public function addzoom(Request $request,$id){
        $table = new LecturePageTable();
        if(request()->isMethod('post')){
            $formData = request()->all();
            $form = $this->getZoomForm();
            $form->setData($formData);

            if($form->isValid()){

                $data = $form->getData();
                $data['lecture_id'] = $id;

                if(empty($data['sort_order'])){
                    $data['sort_order'] = $table->getNextSortOrder($id);
                }


                //get link from url
                try{

                    $data['content']= serialize($data);

                    $table->addRecord([
                        'lecture_id'=>$data['lecture_id'],
                        'title'=>$data['title'],
                        'content'=>$data['content'],
                        'sort_order'=>$data['sort_order'],
                        'type'=>'z'
                    ]);
                    $table->arrangeSortOrders($id);
                    session()->flash('flash_message',__lang('record-added!'));

                }
                catch(\Exception $ex){

                    session()->flash('flash_message',$ex->getMessage());

                }


            }
            else
            {
                session()->flash('flash_message',$this->getFormErrors($form));
            }


        }

       return back();
    }

    public function editzoom(Request $request,$id){
        $lecturePage = LecturePage::findOrFail($id);
        $table = new LecturePageTable();

        if(request()->isMethod('post')){
            $form = $this->getZoomForm();
            $formData = request()->all();
            $form->setData($formData);
            if($form->isValid()){
                $data = $form->getData();

                try{

                    $data['content']= serialize($data);

                    $table->update([
                        'title'=>$data['title'],
                        'content'=>$data['content'],
                        'sort_order'=>$data['sort_order'],
                    ],$id);
                    $table->arrangeSortOrders($id);
                    session()->flash('flash_message',__lang('changes-saved!'));

                }
                catch(\Exception $ex){

                    session()->flash('flash_message',$ex->getMessage());

                }
            }
            else{
                session()->flash('flash_message',$this->getFormErrors($form));
            }
            return back();
        }

        $data = unserialize($lecturePage->content);
        $data['title'] = $lecturePage->title;
        $data['sort_order'] = $lecturePage->sort_order;

        $viewModel = viewModel('admin',__CLASS__,__FUNCTION__,[
            'data'=> $data,
            'id'=>$id
        ]);

        return $viewModel;

    }



    public function importpdf(Request $request){
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING );
        set_time_limit(3600);
        if(getenv('APP_MODE') != 'live' ){
            // change pdftohtml bin location
            \Gufy\PdfToHtml\Config::set('pdftohtml.bin', 'C:/poppler-0.51/bin/pdftohtml.exe');

            \Gufy\PdfToHtml\Config::set('pdfinfo.bin', 'C:/poppler-0.51/bin/pdfinfo.exe');
        }

        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $filePath = 'public/usermedia'.$user;
        $userBaseUrl = $this->getBaseUrl() . '/usermedia'.$user;



        if(!$this->hasPermission('misc/global_access')){

            if(!is_dir(USER_PATH.'/admin_files')){
                mkdir(USER_PATH.'/admin_files') or die('unable to make folder');
            }

            if(!is_dir(USER_PATH.'/admin_files/'.$this->getAdminId())){
                mkdir(USER_PATH.'/admin_files/'.$this->getAdminId()) or die('unable to make user folder');
            }


            $basePath = USER_PATH.'/admin_files/'.$this->getAdminId().'/pdf_import';
            if(!is_dir($basePath)){
                mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
            }

            $fileUrl = $userBaseUrl.'/admin_files/'.$this->getAdminId().'/pdf_import';
            define('BASE_URL',$fileUrl);

        }else{
            $basePath= USER_PATH.'/pdf_import';
            if(!is_dir($basePath)){
                mkdir($basePath) or die('unable to create pdf directory');
            }

            $fileUrl = $userBaseUrl.'/pdf_import';
            define('BASE_URL',$fileUrl);
        }


        $basePath = realpath($basePath);

        define('BASE_PATH',$basePath);




        // change output file location
        define('IMAGE_PATH', BASE_PATH . '/'.date('Y_m_d') . DIRECTORY_SEPARATOR);
        define('IMAGE_URL', BASE_URL . '/'.date('Y_m_d') .'/');
// BASE_PATH and BASE_URL set elsewhere in the script
        list($fn, $ext) = explode('.', $filename);

        \Gufy\PdfToHtml\Config::set('pdftohtml.output', IMAGE_PATH . "{$fn}/");

        $id = request()->get('id');
        $table = new LecturePageTable();
        $lecture = Lecture::find($id);

        $form = $this->getPdfForm();
        $output =[];

        if(request()->isMethod('post'))
        {

            $postData = request()->all();
            $form->setData($postData);
            if($form->isValid()){
                $data = $form->getData();
                $type = $data['type'];
                $path =  'public/'.$postData['path'];
                $title = $data['title'];

                $pdf = new Pdf($path);
                // check if your pdf has more than one pages
                $total_pages = $pdf->getPages();

                if($type=='all'){

                    for($i=1;$i<=$total_pages;$i++){

                        $page = $pdf->html($i);
                        if(substr_count($page,'src="//')>0){
                            $page = str_replace('src="//', 'src="'.IMAGE_URL, $page);
                        }
                        else{
                            $page = str_replace('src="', 'src="'.IMAGE_URL, $page);
                            //$page = str_replace('src="', 'src="'.$userBaseUrl.'/', $page);
                        }
                        //
                        //
                        $page = $this->formatPdfPage($page);
                        if(empty($page)){
                            continue;
                        }

                        $lecturePage = new LecturePage();
                        $lecturePage->sort_order = $table->getNextSortOrder($id);
                        $lecturePage->lecture_id =$id;
                        $lecturePage->title = $title ." ($i)";
                        $lecturePage->content = $page;
                        $lecturePage->type= 't';
                        $lecturePage->save();

                    }
                    $table->arrangeSortOrders($id);
                    flashMessage('PDF file imported. '.$total_pages.' pages created');


                }
                elseif($type=='range'){
                    $start = $data['start'];
                    $end = $data['end'];


                    if($end > $total_pages){
                        $end = $total_pages;
                    }
                    $count =  0;

                    if($end<= $start){
                        session()->flash('flash_message','The end page must be greater than the start page');
                        return $this->redirect()->toUrl(selfURL());

                    }

                    for($i=$start;$i<=$end;$i++){

                        $page = $pdf->html($i);
                        if(substr_count($page,'src="//')>0){
                            $page = str_replace('src="//', 'src="'.IMAGE_URL, $page);
                        }
                        else{
                            $page = str_replace('src="', 'src="'.IMAGE_URL, $page);
                            // $page = str_replace('src="', 'src="'.$userBaseUrl.'/', $page);
                        }
                        $page = $this->formatPdfPage($page);
                        if(empty($page)){
                            continue;
                        }
                        $count++;
                        $lecturePage = new LecturePage();
                        $lecturePage->sort_order = $table->getNextSortOrder($id);
                        $lecturePage->lecture_id =$id;
                        $lecturePage->title = $title ." ($count)";
                        $lecturePage->type= 't';
                        $lecturePage->content = $page;
                        $lecturePage->save();

                    }
                    $table->arrangeSortOrders($id);
                    flashMessage('PDF file imported. '.$count.' pages created');



                }
                elseif($type='choose'){

                    $pages = $data['pages'];
                    $pageArray = explode(',',$pages);
                    $count =  0;

                    foreach($pageArray as $value){
                        $pageNo = intval($value);
                        if(empty($pageNo) || $pageNo > $total_pages ){
                            continue;
                        }

                        $page = $pdf->html($pageNo);
                        if(substr_count($page,'src="//')>0){
                            $page = str_replace('src="//', 'src="'.IMAGE_URL, $page);
                        }
                        else{
                            $page = str_replace('src="', 'src="'.IMAGE_URL, $page);
                            //$page = str_replace('src="', 'src="'.$userBaseUrl.'/', $page);
                        }
                        $page = $this->formatPdfPage($page);

                        if(empty($page)){
                            continue;
                        }
                        $count++;
                        $lecturePage = new LecturePage();
                        $lecturePage->sort_order = $table->getNextSortOrder($id);
                        $lecturePage->lecture_id =$id;
                        $lecturePage->title = $title ." ($count)";
                        $lecturePage->type= 't';
                        $lecturePage->content = $page;
                        $lecturePage->save();

                    }

                    $table->arrangeSortOrders($id);
                    flashMessage('PDF file imported. '.$count.' pages created');

                }
                $this->cropImages();
                return adminRedirect(['controller'=>'lecture','action'=>'content','id'=>$id]);

            }
            else{
                $output['flash_message'] = $this->getFormErrors($form);

            }



        }

        $output['lecture']= $lecture;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lecture->lesson->lesson_id])=>__lang('Class Lectures'),
            adminUrl(['controller'=>'lecture','action'=>'content','id'=>$lecture->lecture_id])=>'Lecture Content',
            '#'=>'Import PDF'
        ];
        $output['pageTitle'] = 'Import PDF to lecture: '.$lecture->title;
        $output['form'] = $form;
        return $output;
    }

    public function importppt(Request $request){
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING );
        set_time_limit(3600);


        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $filePath = 'public/usermedia'.$user;
        $userBaseUrl = 'usermedia'.$user;



        // change output file location

        $id = request()->get('id');
        $table = new LecturePageTable();
        $lecture = Lecture::find($id);

        $form = $this->getPptForm();
        $output =[];

        if(request()->isMethod('post'))
        {



            $postData = request()->all();
            $form->setData($postData);
            if($form->isValid()){

                $time = time();
                $oFilePath = $request->post('path');
                $baseName= basename($oFilePath);
                $safeName= safeUrl($baseName);
                $safeName= substr($safeName,0,15);
                $time = $safeName.'_'.$time;

                if(!$this->hasPermission('misc/global_access')){

                    if(!is_dir(USER_PATH.'/admin_files')){
                        mkdir(USER_PATH.'/admin_files') or die('unable to make folder');
                    }

                    if(!is_dir(USER_PATH.'/admin_files/'.$this->getAdminId())){
                        mkdir(USER_PATH.'/admin_files/'.$this->getAdminId()) or die('unable to make user folder');
                    }


                    $basePath = USER_PATH.'/admin_files/'.$this->getAdminId().'/ppt_import';
                    if(!is_dir($basePath)){
                        mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                    }

                    $basePath = $basePath. '/'.date('Y_m_d');
                    if(!is_dir($basePath)){
                        mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                    }

                    $pptFolder = $basePath.'/'.$time;

                    if(!is_dir($pptFolder)){
                        mkdir($pptFolder) or die('unable to create subdirectory: '.$pptFolder);
                    }

                    $fileUrl = $userBaseUrl.'/admin_files/'.$this->getAdminId().'/ppt_import/'.date('Y_m_d').'/'.$time;
                    define('BASE_URL',$fileUrl);

                }else{
                    $basePath= USER_PATH.'/ppt_import';
                    if(!is_dir($basePath)){
                        mkdir($basePath) or die('unable to create ppt directory');
                        //  chmod($basePath, 0777);
                    }

                    $basePath = $basePath. '/'.date('Y_m_d');
                    if(!is_dir($basePath)){
                        mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                        //  chmod($basePath, 0777);
                    }


                    $pptFolder = $basePath.'/'.$time;

                    if(!is_dir($pptFolder)){
                        mkdir($pptFolder) or die('unable to create subdirectory: '.$pptFolder);
                        //   chmod($pptFolder, 0777);
                    }


                    $fileUrl = $userBaseUrl.'/ppt_import/'.date('Y_m_d').'/'.$time;
                    define('BASE_URL',$fileUrl);
                }


                $basePath = realpath($pptFolder);

                define('BASE_PATH',$basePath);




                $data = $form->getData();
                $type = $data['type'];
                $path =  'public/'.$postData['path'];
                $title = $data['title'];

                $fileName = basename($path);
                $newFile = BASE_PATH.'/'.$fileName;

                //ensure file is a valid ppt document
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $ext = strtolower(trim($ext));


                if($ext != 'ppt' && $ext != 'pptx' && $ext != 'odp')
                {
                    session()->flash('flash_message','This is not a valid Powerpoint/ODP file: '.$fileName);
                    return $this->redirect()->toUrl(selfURL());
                }


                //first copy the file into our new directory
                if(!copy($path,$newFile)){
                    session()->flash('flash_message','Unable to copy file');
                    return $this->redirect()->toUrl(selfURL());
                }


                //now convert file to pdf
                $converter = new OfficeConverter($newFile);
                $result = $converter->convertTo('output.pdf');

                $pdfFile = BASE_PATH.'/output.pdf';
                //now convert pdf file to images
                chdir(BASE_PATH);
                //shell_exec("convert -density 400 $pdfFile -resize 2000x1500 image%d.jpg");

                //get total number of pages in pdf file
                $pdf = new \Spatie\PdfToImage\Pdf($pdfFile);
                $pdf->setCompressionQuality(80);
                $pages = $pdf->getNumberOfPages();

                for($i=1;$i<=$pages;$i++){
                    $pdf->setPage($i)->saveImage(BASE_PATH."/image$i.jpg");

                }



                //delete files
                unlink($pdfFile);
                unlink($fileName);
                $counter=0;
                //scan directory to get all images
                $fi = new \FilesystemIterator(__DIR__, \FilesystemIterator::SKIP_DOTS);
                $totalFiles = iterator_count($fi);

                for($i=1;$i <= $pages;$i++){

                    $imageName= 'image'.$i.'.jpg';

                    if(is_file($imageName) && $this->isImage($imageName))
                    {

                        $lecturePage = new LecturePage();
                        $lecturePage->sort_order = $table->getNextSortOrder($id);
                        $lecturePage->lecture_id =$id;
                        $lecturePage->title = $title .' ('.($counter+1).')';
                        $lecturePage->content = BASE_URL.'/'.$imageName ;
                        $lecturePage->type= 'i';
                        $lecturePage->save();
                        $counter++;
                    }
                }

                session()->flash('flash_message',"$counter slides imported successfully");

                return adminRedirect(['controller'=>'lecture','action'=>'content','id'=>$id]);

            }
            else{
                $output['flash_message'] = $this->getFormErrors($form);

            }



        }

        $output['lecture']= $lecture;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lecture->lesson->lesson_id])=>__lang('Class Lectures'),
            adminUrl(['controller'=>'lecture','action'=>'content','id'=>$lecture->lecture_id])=>'Lecture Content',
            '#'=>'Import PowerPoint File'
        ];
        $output['pageTitle'] = 'Import Powerpoint Slide to lecture: '.$lecture->title;
        $output['form'] = $form;
        return $output;
    }



    public function isImage($file)
    {
        if(@!is_array(getimagesize($file))){
            $image = false;
        }
        else {
            $image = true;
        }
        return $image;
    }

    private function getPdfForm(){
        $form = new BaseForm();
        $form->createHidden('path');
        $form->get('path')->setAttribute('id','path');
        $form->createText('title','Content Title',true);
        $form->createText('start','Start',false,'form-control number');
        $form->createText('end','End',false,'form-control number');
        $form->createText('pages','Pages',false,null,null,'e.g. 1,2,5,8,11');
        $form->createSelect('type','Pages to Import',['all'=>'All Pages','range'=>'Range','choose'=>'Choose Pages'],true);
        $form->setInputFilter($this->getPdfFilter());
        return $form;
    }

    private function getPdfFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'title',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'path',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'type',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'start',
            'required'=>false,
            'validators'=>[
                [
                    'name'=>'Digits'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'end',
            'required'=>false,
            'validators'=>[
                [
                    'name'=>'Digits'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'pages',
            'required'=>false,
        ]);
        return $filter;
    }


    private function getZoomForm(){
        $form = new BaseForm();
        $form->createText('title','Title',true);
        $form->createText('meeting_id','Meeting ID');
        $form->createText('password','Meeting Password');
        $form->createTextArea('instructions','Instructions');
        $form->createText('sort_order','Sort Order');
        $form->setInputFilter($this->getZoomFilter());
        return $form;
    }

    private function getZoomFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'title',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'meeting_id',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'password',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);


        return $filter;
    }


    private function getPptForm(){
        $form = new BaseForm();
        $form->createHidden('path');
        $form->get('path')->setAttribute('id','path');
        $form->createText('title','Content Title',true);
        $form->setInputFilter($this->getPptFilter());
        return $form;
    }

    private function getPptFilter(){
        $filter = new InputFilter();
        $filter->add([
            'name'=>'title',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'path',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);


        return $filter;
    }

    public function deletecontent(Request $request,$id)
    {
        $table = new LecturePageTable();
        $row = $table->getRecord($id);
        $lessonId = $row->lecture_id;
        try{
            $table->deleteRecord($id);
            $table->arrangeSortOrders($lessonId);
            flashMessage(__lang('Record deleted'));
        }
        catch(\Exception $ex){
            $this->deleteError();
        }

        return back();
    }


    private function getContentForm(){
        $form = new BaseForm();
        $form->createText('title','Title',true);
        $form->createTextArea('content','Content',false);
        $form->createText('sort_order','Sort Order',false,'form-control number');
        $form->createSelect('type','Content Type',['t'=>__lang('Text'),'v'=>__lang('Video'),'l'=>__lang('Video'),'c'=>__lang('Html Code'),'i'=>__lang('Image'),'z'=>__lang('zoom-meeting')],true);

        return $form;
    }

    private function getContentFilter(){
        $filter = new InputFilter();

        $filter->add([
            'name'=>'title',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'sort_order',
            'required'=>false,
            'validators'=>array(
                array(
                    'name'=>'Digits'
                )
            )
        ]);

        $filter->add([
            'name'=>'type',
            'required'=>true,
            'validators'=>[
                [
                    'name'=>'NotEmpty'
                ]
            ]
        ]);

        $filter->add([
            'name'=>'content',
            'required'=>false,

        ]);

        return $filter;

    }





    private function formatPdfPage($html){

        $object = \phpQuery::newDocumentHTML($html);

        $object->find('img')->removeAttr('width');
        $object->find('img')->removeAttr('height');
        $object->find('img')->attr('style','max-width:100%');
        $object->find('div')->removeAttr('id');
        $object->find('div')->removeAttr('style');
        //$object->find('div')->attr('style','position:relative; max-width:100%');
        $object->find('p')->removeAttr('style');

        //get the image name
        $url = $object->find('img')->attr('src');
        if(!empty($url)){
            $fileName = IMAGE_PATH.basename($url);

            $this->images[] = $fileName;
        }

        $returnContent = $object->html();

        return $returnContent;
    }

    private function cropImages(){
        foreach($this->images as $fileName){
            $original_img = imagecreatefrompng($fileName);
            unlink($fileName);
            $cropped_img_white = imagecropauto($original_img , IMG_CROP_THRESHOLD, null, 16777215);

            imagepng($cropped_img_white,$fileName,1);
            /*
            if($this->count==2){
                header('Content-Type: image/png');
                imagepng($cropped_img_white);
            }
            */

            imagedestroy($cropped_img_white);
            imagedestroy($original_img);
        }
        $fullPath = IMAGE_PATH ;
        array_map('unlink', glob( "$fullPath*.html"));
    }

    private function delete_recursively_($path,$match){
        static $deleted = 0,
        $deleted_size = 0;
        $dirs = glob($path."*");
        $files = glob($path.$match);
        foreach($files as $file){
            if(is_file($file)){
                $deleted_size += filesize($file);
                unlink($file);
                $deleted++;
            }
        }
        foreach($dirs as $dir){
            if(is_dir($dir)){
                $dir = basename($dir) . "/";
                delete_recursively_($path.$dir,$match);
            }
        }
        return __lang('lecture-files-del-msg',['deleted'=>$deleted,'deleted_size'=>$deleted_size]);

    }

    public function addquiz(Request $request,$id){

        $table = new LecturePageTable();
        if(request()->isMethod('post')){

            $data = request()->all();
            if(!empty($data['name'])){

                //create the json

                $info = new \stdClass();
                $info->name = $data['name'];
                $info->main =  $data['main'];
                $info->results = __lang('quiz-thanks');
                $info->level1 = __lang('Excellent');
                $info->level2 = __lang('Good');
                $info->level3 = __lang('Average');
                $info->level4 = __lang('Below Average');
                $info->level5 = __lang('Poor');


                $obj = new \stdClass();
                $obj->json = new \stdClass();
                $obj->json->info = $info;
                $obj->json->questions = [];
                $obj->checkAnswerText = __lang('Check My Answer!');
                $obj->nextQuestionText = __lang('Next').' &raquo;';
                $obj->backButtonText = '';
                $obj->completeQuizText = '';
                $obj->tryAgainText = '';
                $obj->questionCountText = __lang('Question').' %current '.__lang('of').' %total';
                $obj->preventUnansweredText = __lang('you-must-select');
                $obj->questionTemplateText=  '%count. %text';
                $obj->scoreTemplateText= '%score / %total';
                $obj->nameTemplateText=  '<span>'.__lang('Quiz').': </span>%name';
                $obj->skipStartButton= false;
                $obj->numberOfQuestions= null;
                $obj->randomSortQuestions= false;
                $obj->randomSortAnswers= false;
                $obj->preventUnanswered= false;
                $obj->disableScore= false;
                $obj->disableRanking= false;
                $obj->scoreAsPercentage= false;
                $obj->perQuestionResponseMessaging= true;
                $obj->perQuestionResponseAnswers= false;
                $obj->completionResponseMessaging= false;
                $obj->displayQuestionCount= true;
                $obj->displayQuestionNumber= true;




                $json = json_encode($obj);

                $lecturePage = new LecturePage();
                $lecturePage->title = $data['name'];
                $lecturePage->lecture_id = $id;
                $lecturePage->type = 'q';
                $lecturePage->sort_order = $table->getNextSortOrder($id);
                $lecturePage->content = $json;
                $lecturePage->save();

                //now forward to editing page
                return adminRedirect(['controller'=>'lecture','action'=>'editquiz','id'=>$lecturePage->id]);
            }



        }

        return back();


    }

    public function editquiz(Request $request,$id){

        $table = new LecturePageTable();
        $lecturePage = LecturePage::find($id);
        //dd(json_decode($lecturePage->content) );
        if(request()->isMethod('post')){

            $info = new \stdClass();


            $obj = new \stdClass();
            $obj->json = new \stdClass();
            $obj->json->info = $info;

            //dd($request->post('content'));
            $quizPost = $request->post('content');
            foreach($quizPost as $key=>$value){
                $obj->$key = booleanValue($value);
            }

            unset($obj->json['questions']);
            $obj->json['questions'] = [];
            if(isset($quizPost['json']['questions'])){
                foreach($quizPost['json']['questions'] as $question){
                    $questionObject = new \stdClass();
                    $questionObject->q = $question['q'];
                    $questionObject->correct = $question['correct'];
                    $questionObject->incorrect = $question['incorrect'];
                    if(isset($question['select_any'])){
                        $questionObject->select_any = booleanValue($question['select_any']);
                    }

                    if(isset($question['force_checkbox'])){
                        $questionObject->force_checkbox = booleanValue($question['force_checkbox']);
                    }

                    if(isset($question['a'])){
                        foreach($question['a'] as $option){
                            $optionObs = new \stdClass();
                            $optionObs->option= $option['option'];
                            $optionObs->correct = booleanValue(@$option['correct']);
                            $questionObject->a[] = $optionObs;
                        }
                    }


                    $obj->json['questions'][] = $questionObject;
                }
            }



            $lecturePage->content = json_encode($obj);
            $lecturePage->sort_order = $request->post('sort_order');
            $lecturePage->title = $request->post('title');
            $lecturePage->save();

            $table->arrangeSortOrders($lecturePage->lecture_id);
            exit('true');
        }


        return view('admin.lecture.editquiz',[
            'pageTitle'=>__lang('Edit Quiz').': '.$lecturePage->title,
            'lecturePage'=>$lecturePage,
            'customCrumbs' => [
                route('admin.dashboard')=>__('default.dashboard'),
                adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
                adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lecturePage->lecture->lesson->id])=>__lang('Class Lectures'),
                adminUrl(['controller'=>'lecture','action'=>'content','id'=>$lecturePage->lecture->id])=>__lang('Lecture Content'),
                '#'=>__lang('Edit Quiz')
            ]
        ]);
    }

    public function addaudio(Request $request){

        if(request()->isMethod('post')){

            $id = $request->post('id');
            $lecturePage = LecturePage::find($id);
            $url = $request->post('url');
            $url = trim($url);
            if(!isUrl($url)){

                session()->flash('flash_message',__lang('Invalid url'));
                return back();
            }

            try{

                $getValues=file_get_contents('http://soundcloud.com/oembed?format=js&url='.$url.'&iframe=true');
                //Clean the Json to decode
                $decodeiFrame=substr($getValues, 1, -2);
                //json decode to convert it as an array
                $jsonObj = json_decode($decodeiFrame);

                //Change the height of the embed player if you want else uncomment below line
                // echo $jsonObj->html;

                $code = str_replace('height="400"', 'height="140"', $jsonObj->html);
                if(!empty($code)){
                    $lecturePage->audio_code = $code;
                    $lecturePage->save();
                    session()->flash('flash_message',__lang('Audio added successfully'));
                }
                else{
                    session()->flash('flash_message',__lang('unable-to-add-audio'));
                }

            }
            catch (\Exception $ex){
                session()->flash('flash_message',__lang('Invalid url'));
                return back();
            }



        }

        return back();
    }

    public function removeaudio(Request $request,$id){

        $lecturePage = LecturePage::find($id);
        $lecturePage->audio_code = null;
        $lecturePage->save();
        session()->flash('flash_message',__lang('Audio removed'));
        return back();
    }

    public function library(Request $request,$id){

        //forward to controller


        $viewModel = app(VideoController::class)->index($request);

        $data = $viewModel->getData();
        $data['paginator']->setItemCountPerPage(10);

        $data['lectureId']= $id;
        $vModel = viewModel('admin',__CLASS__,__FUNCTION__,$data);

        return $vModel;
    }

    public function addvideolibrary(Request $request,$id){
        $table = new LecturePageTable();
        $videoId = $id;
        $lectureId = request()->get('lecture');
        if (saas()){
            //check if there is another video in library
            $count = LecturePage::where('lecture_id',$lectureId)->where('type','l')->count();
            if($count>0){
                flashMessage(__lang('multiple-videos-warning'));
                return redirect()->route('admin.lecture.content',['id'=>$lectureId]);

            }
        }


        $video= Video::find($videoId);
        $data = [];
        $data['lecture_id'] = $lectureId;


        $data['sort_order'] = $table->getNextSortOrder($lectureId);

        $data['title'] = $video->name;
        $data['content']= $videoId;
        $data['type']='l';

        $table->addRecord($data);
        $table->arrangeSortOrders($lectureId);
        session()->flash('flash_message',__lang('video-added'));
        return back();

    }

    public function importimages(Request $request,$id){

        $user= '';
        if(defined('USER_ID')){
            $user = '/'.USER_ID;
        }
        $filePath = 'usermedia'.$user;
        $userBaseUrl = 'usermedia'.$user;


        $table = new LecturePageTable();
        $lecture = Lecture::find($id);

        if(request()->isMethod('post')){
            $form = $this->getUploadForm();

            $form->setData($_FILES);
            $file =  $_FILES['files'];



            if(!$form->isValid()){
                $json = json_encode([
                    'files'=>[
                        [
                            'name'=>$file['name'],
                            'size'=>$file['size'],
                            'error'=>__lang('Invalid upload')
                        ]
                    ]
                ]);

                exit($json);
            }

            //get file name
            $name = $file['name'];
            $tmpName = $file['tmp_name'];


            $time = time();
            $safeName= safeUrl($name);
            //   $safeName= substr($safeName,0,15);
            $time = $id;

            if(!GLOBAL_ACCESS){

                if(!is_dir(USER_PATH.'/admin_files')){
                    mkdir(USER_PATH.'/admin_files') or die('unable to make folder');
                }

                if(!is_dir(USER_PATH.'/admin_files/'.$this->getAdminId())){
                    mkdir(USER_PATH.'/admin_files/'.$this->getAdminId()) or die('unable to make user folder');
                }


                $basePath = USER_PATH.'/admin_files/'.$this->getAdminId().'/image_import';
                if(!is_dir($basePath)){
                    mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                }

                $basePath = $basePath. '/'.date('Y_m_d');
                if(!is_dir($basePath)){
                    mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                }

                $pptFolder = $basePath.'/'.$time;

                if(!is_dir($pptFolder)){
                    mkdir($pptFolder) or die('unable to create subdirectory: '.$pptFolder);
                }

                $fileUrl = $userBaseUrl.'/admin_files/'.$this->getAdminId().'/image_import/'.date('Y_m_d').'/'.$time;
                define('BASE_URL',$fileUrl);

            }else{
                $basePath= USER_PATH.'/image_import';
                if(!is_dir($basePath)){
                    mkdir($basePath) or die('unable to create ppt directory');
                    //  chmod($basePath, 0777);
                }

                $basePath = $basePath. '/'.date('Y_m_d');
                if(!is_dir($basePath)){
                    mkdir($basePath) or die('unable to create subdirectory: '.$basePath);
                    //  chmod($basePath, 0777);
                }


                $pptFolder = $basePath.'/'.$time;

                if(!is_dir($pptFolder)){
                    mkdir($pptFolder) or die('unable to create subdirectory: '.$pptFolder);
                    //   chmod($pptFolder, 0777);
                }


                $fileUrl = $userBaseUrl.'/image_import/'.date('Y_m_d').'/'.$time;
                define('BASE_URL',$fileUrl);
            }


            $basePath = realpath($pptFolder);

            define('BASE_PATH',$basePath);

            $path_parts = pathinfo($name);

            $newName = substr($path_parts['filename'],0,15).'_'.time();
            $safeName = safeUrl($newName).'.'.$path_parts['extension'];
            //move image to new path
            rename($tmpName,BASE_PATH.'/'.$safeName);
            @chmod(BASE_PATH.'/'.$safeName,0644);



            $lecturePage = new LecturePage();
            $lecturePage->sort_order = $table->getNextSortOrder($id);
            $lecturePage->lecture_id =$id;
            $lecturePage->title = $path_parts['filename'];
            $lecturePage->content = BASE_URL.'/'.$safeName ;
            $lecturePage->type= 'i';
            $lecturePage->save();




            $json = json_encode([
                'files'=>[
                    [
                        'name'=>__lang('File Upload Successful').': '.$file['name'],
                        'size'=>$file['size'],
                        'thumbnailUrl'=>$this->getBaseUrl().'/img/success.png'
                    ]
                ]
            ]);


            exit($json);

        }


        $output['lecture']= $lecture;
        $output['customCrumbs'] = [

            route('admin.dashboard')=>__('default.dashboard'),
            adminUrl(['controller'=>'lesson','action'=>'index'])=>__lang('Classes'),
            adminUrl(['controller'=>'lecture','action'=>'index','id'=>$lecture->lesson->id])=>__lang('Class Lectures'),
            adminUrl(['controller'=>'lecture','action'=>'content','id'=>$lecture->id])=>__lang('Lecture Content'),
            '#'=>__lang('Import Images')
        ];
        $output['pageTitle'] = __lang('Import Images to lecture').': '.$lecture->title;

        return view('admin.lecture.importimages',$output);

    }


    private function getUploadForm(){

        $form = new BaseForm();

        $file = new File('files');
        $file->setLabel(__lang('Your File'))
            ->setAttribute('id','file_path')
            ->setAttribute('required','required');
        $form->add($file);
        $form->setInputFilter($this->getUploadFilter());
        return $form;

    }

    private function getUploadFilter(){

        $filter = new InputFilter();



        $input = new Input('files');
        $input->setRequired(true);
        $input->getValidatorChain()
            ->attach(new Size(env('MAX_UPLOAD_SIZE')))
            ->attach(new Extension('jpg,jpeg,png,gif'));

        $filter->add($input);



        return $filter;
    }
}
