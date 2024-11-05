<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/




Auth::routes();
Route::get('social/login/{network}','Auth\LoginController@social')->name('social.login');

//Route::get('/home', 'HomeController@index')->name('home');
Route::get('/migrate','IndexController@migrate');
Route::post('/setup','IndexController@setup');
Route::get('/update-video/{video}','IndexController@updateVideo');
Route::get('/test','IndexController@test');
Route::any('survey/complete','Student\SurveyController@complete')->name('survey.complete');
Route::any('survey/{hash}','Student\SurveyController@survey')->name('survey');
Route::any('survey/survey/{$hash}','Student\SurveyController@survey')->name('survey.survey');
Route::get('home', 'HomeController@index')->name('home');


Route::get('terms','Site\HomeController@terms')->name('terms');
Route::get('privacy','Site\HomeController@privacy')->name('privacy');
Route::get('instructors','Site\HomeController@instructors')->name('instructors')->middleware('frontend');
Route::get('instructors/{admin}','Site\HomeController@instructor')->name('instructor')->middleware('frontend');



Route::get('email-confirmation','Auth\RegisterController@confirm')->name('register.confirm');
Route::get('confirm/student/{hash}','Auth\RegisterController@confirmStudent')->name('confirm.student');
Route::post('register/social','Auth\LoginController@registerSocial')->name('register.social');
Route::get('register/captcha','Auth\RegisterController@newCaptcha')->name('register.captcha');

Route::group(['middleware'=>['auth','admin',\App\Http\Middleware\UserLimit::class],'prefix' => 'admin', 'as' => 'admin.','namespace'=>'Admin'],function() {

    Route::get('/', 'HomeController@index')->name('dashboard');

    Route::any('account/email','AccountController@email')->name('account.email');
    Route::any('account/password','AccountController@password')->name('account.password');
    Route::any('account/profile','AccountController@profile')->name('account.profile');
    Route::any('articles/index','ArticlesController@index')->name('articles.index');
    Route::any('articles/add','ArticlesController@add')->name('articles.add');
    Route::any('articles/edit/{id}','ArticlesController@edit')->name('articles.edit');
    Route::any('articles/delete/{id}','ArticlesController@delete')->name('articles.delete');
    Route::any('assignment/index','AssignmentController@index')->name('assignment.index');
    Route::any('assignment/add','AssignmentController@add')->name('assignment.add');
    Route::any('assignment/edit/{id}','AssignmentController@edit')->name('assignment.edit');
    Route::any('assignment/view/{id}','AssignmentController@view')->name('assignment.view');
    Route::any('assignment/delete/{id}','AssignmentController@delete')->name('assignment.delete');
    Route::any('assignment/submissions/{id}','AssignmentController@submissions')->name('assignment.submissions');
    Route::any('assignment/viewsubmission/{id}','AssignmentController@viewsubmission')->name('assignment.viewsubmission');
    Route::any('assignment/download/{id}','AssignmentController@downloadFile')->name('assignment.download');
    Route::any('assignment/exportresult/{id}','AssignmentController@exportresult')->name('assignment.exportresult');
    Route::any('assignment/sessionlessons/{id}','AssignmentController@sessionlessons')->name('assignment.sessionlessons');
    Route::any('certificate/index','CertificateController@index')->name('certificate.index');
    Route::any('certificate/add','CertificateController@add')->name('certificate.add');
    Route::any('certificate/edit/{id}','CertificateController@edit')->name('certificate.edit');
    Route::any('certificate/fix/{id}','CertificateController@fix')->name('certificate.fix');
    Route::any('certificate/reset/{id}','CertificateController@reset')->name('certificate.reset');
    Route::any('certificate/loadclasses/{id}','CertificateController@loadclasses')->name('certificate.loadclasses');
    Route::any('certificate/delete/{id}','CertificateController@delete')->name('certificate.delete');
    Route::any('certificate/duplicate/{id}','CertificateController@duplicate')->name('certificate.duplicate');
    Route::any('certificate/students/{id}','CertificateController@students')->name('certificate.students');
    Route::any('certificate/track','CertificateController@track')->name('certificate.track');


    Route::any('course/intro/{id}','CourseController@intro')->name('course.intro');
    Route::any('course/class/{lesson}/{course}','CourseController@class')->name('course.class');
    Route::any('course/lecture/{lecture}/{course}','CourseController@lecture')->name('course.lecture');
    Route::any('course/bookmark','CourseController@bookmark')->name('course.bookmark');
    Route::any('course/loglecture','CourseController@loglecture')->name('course.loglecture');
    Route::any('course/classfile/{id}/{course}','CourseController@classfile')->name('course.classfile');
    Route::any('course/allclassfiles/{id}/{course}','CourseController@allclassfiles')->name('course.allclassfiles');
    Route::any('course/lecturefile/{id}/{course}','CourseController@lecturefile')->name('course.lecturefile');
    Route::any('course/alllecturefiles/{id}/{course}','CourseController@alllecturefiles')->name('course.alllecturefiles');
    Route::any('course/bookmarks','CourseController@bookmarks')->name('course.bookmarks');
    Route::any('course/serve/{id}','CourseController@serve')->name('course.serve');

    Route::any('discuss/index','DiscussController@index')->name('discuss.index');
    Route::any('discuss/addreply/{id}','DiscussController@addreply')->name('discuss.addreply');
    Route::any('discuss/viewdiscussion/{id}','DiscussController@viewdiscussion')->name('discuss.viewdiscussion');
    Route::any('discuss/delete/{id}','DiscussController@delete')->name('discuss.delete');
    Route::any('download/index','DownloadController@index')->name('download.index');
    Route::any('download/add','DownloadController@add')->name('download.add');
    Route::any('download/edit/{id}','DownloadController@edit')->name('download.edit');
    Route::any('download/files/{id}','DownloadController@files')->name('download.files');
    Route::any('download/sessions/{id}','DownloadController@sessions')->name('download.sessions');
    Route::any('download/addfile/{id}','DownloadController@addfile')->name('download.addfile');
    Route::any('download/removefile/{id}','DownloadController@removefile')->name('download.removefile');
    Route::any('download/addsession/{id}','DownloadController@addsession')->name('download.addsession');
    Route::any('download/removesession/{id}','DownloadController@removesession')->name('download.removesession');
    Route::any('download/delete/{id}','DownloadController@delete')->name('download.delete');
    Route::any('download/duplicate/{id}','DownloadController@duplicate')->name('download.duplicate');
    Route::any('download/browsesessions/{id}','DownloadController@browsesessions')->name('download.browsesessions');
    Route::any('download/download/{id}','DownloadController@download')->name('download.download');
    Route::any('filemanager','FilemanagerController@index')->name('filemanager.home');
    Route::any('filemanager/index','FilemanagerController@index')->name('filemanager.index');
    Route::any('filemanager/connector','FilemanagerController@connector')->name('filemanager.connector');
    Route::any('filemanager/image','FilemanagerController@image')->name('filemanager.image');
    Route::any('forum/index','ForumController@index')->name('forum.index');
    Route::any('forum/addtopic','ForumController@addtopic')->name('forum.addtopic');
    Route::any('forum/topic/{id}','ForumController@topic')->name('forum.topic');
    Route::any('forum/reply/{id}','ForumController@reply')->name('forum.reply');
    Route::any('forum/notifications/{id}','ForumController@notifications')->name('forum.notifications');
    Route::any('forum/deletetopic/{id}','ForumController@deletetopic')->name('forum.deletetopic');
    Route::any('revision-notes/index','HomeworkController@index')->name('homework.index');
    Route::any('revision-notes/add','HomeworkController@add')->name('homework.add');
    Route::any('revision-notes/edit/{id}','HomeworkController@edit')->name('homework.edit');
    Route::any('revision-notes/delete/{id}','HomeworkController@delete')->name('homework.delete');
    Route::any('lecture/index/{id}','LectureController@index')->name('lecture.index');
    Route::any('lecture/add/{id}','LectureController@add')->name('lecture.add');
    Route::any('lecture/edit/{id}','LectureController@edit')->name('lecture.edit');
    Route::any('lecture/delete/{id}','LectureController@delete')->name('lecture.delete');
    Route::any('lecture/files/{id}','LectureController@files')->name('lecture.files');
    Route::any('lecture/addfile/{id}','LectureController@addfile')->name('lecture.addfile');
    Route::any('lecture/removefile/{id}','LectureController@removefile')->name('lecture.removefile');
    Route::any('lecture/download/{id}','LectureController@download')->name('lecture.download');
    Route::any('lecture/content/{id}','LectureController@content')->name('lecture.content');
    Route::any('lecture/reorder/{id}','LectureController@reorder')->name('lecture.reorder');
    Route::any('lecture/deletecontents','LectureController@deletecontents')->name('lecture.deletecontents');
    Route::any('lecture/addcontent/{id}','LectureController@addcontent')->name('lecture.addcontent');
    Route::any('lecture/addvideo/{id}','LectureController@addvideo')->name('lecture.addvideo');
    Route::any('lecture/addzoom/{id}','LectureController@addzoom')->name('lecture.addzoom');
    Route::any('lecture/editzoom/{id}','LectureController@editzoom')->name('lecture.editzoom');
    Route::any('lecture/importpdf/{id}','LectureController@importpdf')->name('lecture.importpdf');
    Route::any('lecture/importppt/{id}','LectureController@importppt')->name('lecture.importppt');
    Route::any('lecture/deletecontent/{id}','LectureController@deletecontent')->name('lecture.deletecontent');
    Route::any('lecture/addquiz/{id}','LectureController@addquiz')->name('lecture.addquiz');
    Route::any('lecture/editquiz/{id}','LectureController@editquiz')->name('lecture.editquiz');
    Route::any('lecture/addaudio','LectureController@addaudio')->name('lecture.addaudio');
    Route::any('lecture/removeaudio/{id}','LectureController@removeaudio')->name('lecture.removeaudio');
    Route::any('lecture/library/{id}','LectureController@library')->name('lecture.library');
    Route::any('lecture/addvideolibrary/{id}','LectureController@addvideolibrary')->name('lecture.addvideolibrary');
    Route::any('lecture/importimages/{id}','LectureController@importimages')->name('lecture.importimages');
    Route::any('lesson/index','LessonController@index')->name('lesson.index');
    Route::any('lesson/add','LessonController@add')->name('lesson.add');
    Route::any('lesson/edit/{id}','LessonController@edit')->name('lesson.edit');
    Route::any('lesson/delete/{id}','LessonController@delete')->name('lesson.delete');
    Route::any('lesson/groups','LessonController@groups')->name('lesson.groups');
    Route::any('lesson/addgroup','LessonController@addgroup')->name('lesson.addgroup');
    Route::any('lesson/editgroup/{id}','LessonController@editgroup')->name('lesson.editgroup');
    Route::any('lesson/deletegroup/{id}','LessonController@deletegroup')->name('lesson.deletegroup');
    Route::any('lesson/files/{id}','LessonController@files')->name('lesson.files');
    Route::any('lesson/addfile/{id}','LessonController@addfile')->name('lesson.addfile');
    Route::any('lesson/removefile/{id}','LessonController@removefile')->name('lesson.removefile');
    Route::any('lesson/download/{id}','LessonController@download')->name('lesson.download');
    Route::any('lesson/duplicate/{id}','LessonController@duplicate')->name('lesson.duplicate');
    Route::any('messages/emails','MessagesController@emails')->name('messages.emails');
    Route::any('messages/editemail/{id}','MessagesController@editemail')->name('messages.editemail');
    Route::any('messages/resetemail/{id}','MessagesController@resetemail')->name('messages.resetemail');
    Route::any('messages/sms','MessagesController@sms')->name('messages.sms');
    Route::any('messages/editsms/{id}','MessagesController@editsms')->name('messages.editsms');
    Route::any('messages/resetsms/{id}','MessagesController@resetsms')->name('messages.resetsms');
    Route::any('payment/index','PaymentController@index')->name('payment.index');
    Route::any('payment/edit/{id}','PaymentController@edit')->name('payment.edit');
    Route::any('payment/currencies/{id}','PaymentController@currencies')->name('payment.currencies');
    Route::any('payment/deletecurrency/{paymentMethod}/{id}','PaymentController@deletecurrency')->name('payment.deletecurrency');
    Route::any('payment/coupons','PaymentController@coupons')->name('payment.coupons');
    Route::any('payment/addcoupon','PaymentController@addcoupon')->name('payment.addcoupon');
    Route::any('payment/editcoupon/{id}','PaymentController@editcoupon')->name('payment.editcoupon');
    Route::any('payment/deletecoupon/{id}','PaymentController@deletecoupon')->name('payment.deletecoupon');
    Route::any('report/index','ReportController@index')->name('report.index');
    Route::any('report/classes/{id}','ReportController@classes')->name('report.classes');
    Route::any('report/students/{id}','ReportController@students')->name('report.students');
    Route::any('report/tests/{id}','ReportController@tests')->name('report.tests');
    Route::any('report/homework/{id}','ReportController@homework')->name('report.homework');
    Route::any('report/reportcard/{id}','ReportController@reportcard')->name('report.reportcard');
    Route::any('session/sessiontype/{id}','SessionController@sessiontype')->name('session.sessiontype');
    Route::any('session/addcourse/{type?}','SessionController@addcourse')->name('session.addcourse');
    Route::any('session/editcourse/{id}','SessionController@editcourse')->name('session.editcourse');
    Route::any('session/sessionclasses/{id}','SessionController@sessionclasses')->name('session.sessionclasses');
    Route::any('session/courseclasses/{id}','SessionController@courseclasses')->name('session.courseclasses');
    Route::any('session/browseclasses/{id}','SessionController@browseclasses')->name('session.browseclasses');
    Route::any('session/setclass/{id}','SessionController@setclass')->name('session.setclass');
    Route::any('session/reorder/{course}','SessionController@reorder')->name('session.reorder');
    Route::any('session/setdate/{course}/{lesson?}','SessionController@setdate')->name('session.setdate');
    Route::any('session/setstart/{course}/{lesson?}','SessionController@setstart')->name('session.setstart');
    Route::any('session/setend/{course}/{lesson?}','SessionController@setend')->name('session.setend');
    Route::any('session/setvenue/{course}/{lesson?}','SessionController@setvenue')->name('session.setvenue');
    Route::any('session/lectures/{id}','SessionController@lectures')->name('session.lectures');
    Route::any('session/deleteclass/{lesson}/{course}','SessionController@deleteclass')->name('session.deleteclass');
    Route::any('session/groups','SessionController@groups')->name('session.groups');
    Route::any('session/addgroup','SessionController@addgroup')->name('session.addgroup');
    Route::any('session/editgroup/{id}','SessionController@editgroup')->name('session.editgroup');
    Route::any('session/deletegroup/{id}','SessionController@deletegroup')->name('session.deletegroup');
    Route::any('session/createclass/{id}','SessionController@createclass')->name('session.createclass');
    Route::any('session/stats/{id}','SessionController@stats')->name('session.stats');
    Route::any('session/tests/{id}','SessionController@tests')->name('session.tests');
    Route::any('session/addtest/{id}','SessionController@addtest')->name('session.addtest');
    Route::any('session/edittest/{id}','SessionController@edittest')->name('session.edittest');
    Route::any('session/smssession','SessionController@smssession')->name('session.smssession');
    Route::any('setting/index','SettingController@index')->name('setting.index');
    Route::any('setting/fields','SettingController@fields')->name('setting.fields');
    Route::any('setting/addfield','SettingController@addfield')->name('setting.addfield');
    Route::any('setting/deletefield/{id}','SettingController@deletefield')->name('setting.deletefield');
    Route::any('setting/editfield/{id}','SettingController@editfield')->name('setting.editfield');
/*    Route::any('setting/roles','SettingController@roles')->name('setting.roles');
    Route::any('setting/addrole','SettingController@addrole')->name('setting.addrole');
    Route::any('setting/editrole/{id}','SettingController@editrole')->name('setting.editrole');*/
    Route::any('setting/deleteaccount/{id}','SettingController@deleteaccount')->name('setting.deleteaccount');
    Route::any('setting/deleterole/{id}','SettingController@deleterole')->name('setting.deleterole');
    Route::any('setting/admins','SettingController@admins')->name('setting.admins');
    Route::any('setting/addadmin','SettingController@addadmin')->name('setting.addadmin');
    Route::any('setting/editadmin/{id}','SettingController@editadmin')->name('setting.editadmin');
    Route::any('setting/migrate','SettingController@migrate')->name('setting.migrate');
    Route::any('setting/testgrades','SettingController@testgrades')->name('setting.testgrades');
    Route::any('setting/addtestgrade','SettingController@addtestgrade')->name('setting.addtestgrade');
    Route::any('setting/edittestgrade/{id}','SettingController@edittestgrade')->name('setting.edittestgrade');
    Route::any('setting/deletetestgrade/{id}','SettingController@deletetestgrade')->name('setting.deletetestgrade');
    Route::any('setting/currencies','SettingController@currencies')->name('setting.currencies');
    Route::any('setting/addcurrency','SettingController@addcurrency')->name('setting.addcurrency');
    Route::any('setting/deletecurrency/{id}','SettingController@deletecurrency')->name('setting.deletecurrency');
    Route::any('setting/updatecurrency/{id}','SettingController@updatecurrency')->name('setting.updatecurrency');
    Route::any('setting/language','SettingController@language')->name('setting.language');
    Route::any('smsgateway/index','SmsgatewayController@index')->name('smsgateway.index');
    Route::any('smsgateway/edit/{smsGateway}','SmsgatewayController@edit')->name('smsgateway.edit');
    Route::post('smsgateway/save/{smsGateway}','SmsgatewayController@save')->name('smsgateway.save');
    Route::any('smsgateway/install/{gateway}','SmsgatewayController@install')->name('smsgateway.install');
    Route::any('smsgateway/uninstall/{smsGateway}','SmsgatewayController@uninstall')->name('smsgateway.uninstall');
    Route::any('student/index','StudentController@index')->name('student.index');
    Route::any('student/active','StudentController@active')->name('student.active');
    Route::any('student/add','StudentController@add')->name('student.add');
    Route::any('student/view/{id?}','StudentController@view')->name('student.view');
    Route::any('student/edit/{id}','StudentController@edit')->name('student.edit');
    Route::any('student/removeimage/{id}','StudentController@removeimage')->name('student.removeimage');
    Route::any('student/changepassword/{id}','StudentController@changepassword')->name('student.changepassword');
    Route::any('student/delete/{id}','StudentController@delete')->name('student.delete');
    Route::any('student/sessions','StudentController@sessions')->name('student.sessions');
    Route::any('student/addsession/{type}','StudentController@addsession')->name('student.addsession');
    Route::any('student/editsession/{id}','StudentController@editsession')->name('student.editsession');
    Route::any('student/duplicatesession/{id}','StudentController@duplicatesession')->name('student.duplicatesession');
    Route::any('student/createclass','StudentController@createclass')->name('student.createclass');
    Route::any('student/deletesession/{id}','StudentController@deletesession')->name('student.deletesession');
    Route::any('student/attendance','StudentController@attendance')->name('student.attendance');
    Route::any('student/attendancebulk','StudentController@attendancebulk')->name('student.attendancebulk');
    Route::any('student/getstudents','StudentController@getstudents')->name('student.getstudents');
    Route::any('student/getsessionstudents/{id}','StudentController@getsessionstudents')->name('student.getsessionstudents');
    Route::any('student/processattendance','StudentController@processattendance')->name('student.processattendance');
    Route::any('student/sessionattendees/{id}','StudentController@sessionattendees')->name('student.sessionattendees');
    Route::any('student/sessionenrollees/{id}','StudentController@sessionenrollees')->name('student.sessionenrollees');
    Route::any('student/enroll/{id}','StudentController@enroll')->name('student.enroll');
    Route::any('student/export/{id}','StudentController@export')->name('student.export');
    Route::any('student/exportbulkattendance/{id}','StudentController@exportbulkattendance')->name('student.exportbulkattendance');
    Route::any('student/attendanceimport','StudentController@attendanceimport')->name('student.attendanceimport');
    Route::any('student/exporttel/{id}','StudentController@exporttel')->name('student.exporttel');
    Route::any('student/massenroll','StudentController@massenroll')->name('student.massenroll');
    Route::any('student/certificatelist','StudentController@certificatelist')->name('student.certificatelist');
    Route::any('student/importsession','StudentController@importsession')->name('student.importsession');
    Route::any('student/deleteattendance','StudentController@deleteattendance')->name('student.deleteattendance');
    Route::any('student/exportattendance','StudentController@exportattendance')->name('student.exportattendance');
    Route::any('student/attendancedate','StudentController@attendancedate')->name('student.attendancedate');
    Route::any('student/csvsample','StudentController@csvsample')->name('student.csvsample');
    Route::any('student/getsessionlessons/{id}','StudentController@getsessionlessons')->name('student.getsessionlessons');
    Route::any('student/sessionlessons/{id}','StudentController@sessionlessons')->name('student.sessionlessons');
    Route::any('student/certificatelessons/{id}','StudentController@certificatelessons')->name('student.certificatelessons');
    Route::any('student/sessionstudents/{id}','StudentController@sessionstudents')->name('student.sessionstudents');
    Route::any('student/unenroll/{id}','StudentController@unenroll')->name('student.unenroll');
    Route::any('student/mailsession/{id?}','StudentController@mailsession')->name('student.mailsession');
    Route::any('student/invoices','StudentController@invoices')->name('student.invoices');
    Route::any('student/approvetransactions/{id}','StudentController@approvetransaction')->name('student.approvetransaction');
    Route::any('student/payments','StudentController@payments')->name('student.payments');
    Route::any('student/instructors/{id}','StudentController@instructors')->name('student.instructors');
    Route::any('student/manageinstructors/{course}/{lesson}','StudentController@manageinstructors')->name('student.manageinstructors');
    Route::any('student/import','StudentController@import')->name('student.import');
    Route::any('student/exportstudents','StudentController@exportstudents')->name('student.exportstudents');
    Route::any('student/deleteinvoices/{id}','StudentController@deleteinvoice')->name('student.deleteinvoice');
    Route::any('survey/index','SurveyController@index')->name('survey.index');
    Route::any('survey/add','SurveyController@add')->name('survey.add');
    Route::any('survey/edit/{id}','SurveyController@edit')->name('survey.edit');
    Route::any('survey/delete/{id}','SurveyController@delete')->name('survey.delete');
    Route::any('survey/questions/{id}','SurveyController@questions')->name('survey.questions');
    Route::any('survey/addquestion/{id}','SurveyController@addquestion')->name('survey.addquestion');
    Route::any('survey/editquestion/{id}','SurveyController@editquestion')->name('survey.editquestion');
    Route::any('survey/addoptions/{id}','SurveyController@addoptions')->name('survey.addoptions');
    Route::any('survey/editoption/{id}','SurveyController@editoption')->name('survey.editoption');
    Route::any('survey/deletequestion/{id}','SurveyController@deletequestion')->name('survey.deletequestion');
    Route::any('survey/deleteoption/{id}','SurveyController@deleteoption')->name('survey.deleteoption');
    Route::any('survey/duplicate/{id}','SurveyController@duplicate')->name('survey.duplicate');
    Route::any('survey/results/{id}','SurveyController@results')->name('survey.results');
    Route::any('survey/deleteresult/{id}','SurveyController@deleteresult')->name('survey.deleteresult');
    Route::any('survey/result/{id}','SurveyController@result')->name('survey.result');
    Route::any('survey/exportresult/{id}','SurveyController@exportresult')->name('survey.exportresult');
    Route::any('survey/sessions/{id}','SurveyController@sessions')->name('survey.sessions');
    Route::any('survey/addsession/{id}','SurveyController@addsession')->name('survey.addsession');
    Route::any('survey/editsession/{id}','SurveyController@editsession')->name('survey.editsession');
    Route::any('survey/deletesession/{id}','SurveyController@deletesession')->name('survey.deletesession');
    Route::any('survey/importquestions/{id}','SurveyController@importquestions')->name('survey.importquestions');
    Route::any('survey/exportquestions/{id}','SurveyController@exportquestions')->name('survey.exportquestions');
    Route::any('survey/send/{id}','SurveyController@send')->name('survey.send');
    Route::any('survey/report/{id}','SurveyController@report')->name('survey.report');
    Route::any('test/index','TestController@index')->name('test.index');
    Route::any('test/add','TestController@add')->name('test.add');
    Route::any('test/edit/{id}','TestController@edit')->name('test.edit');
    Route::any('test/delete/{id}','TestController@delete')->name('test.delete');
    Route::any('test/questions/{id}','TestController@questions')->name('test.questions');
    Route::any('test/addquestion/{id}','TestController@addquestion')->name('test.addquestion');
    Route::any('test/editquestion/{id}','TestController@editquestion')->name('test.editquestion');
    Route::any('test/addoptions','TestController@addoptions')->name('test.addoptions');
    Route::any('test/editoption/{id}','TestController@editoption')->name('test.editoption');
    Route::any('test/deletequestion/{id}','TestController@deletequestion')->name('test.deletequestion');
    Route::any('test/deleteoption/{id}','TestController@deleteoption')->name('test.deleteoption');
    Route::any('test/duplicate/{id}','TestController@duplicate')->name('test.duplicate');
    Route::any('test/results/{id}','TestController@results')->name('test.results');
    Route::any('test/deleteresult/{id}','TestController@deleteresult')->name('test.deleteresult');
    Route::any('test/testresult/{id}','TestController@testresult')->name('test.testresult');
    Route::any('test/exportresult/{id}','TestController@exportresult')->name('test.exportresult');
    Route::any('test/sessions/{id}','TestController@sessions')->name('test.sessions');
    Route::any('test/addsession/{id}','TestController@addsession')->name('test.addsession');
    Route::any('test/editsession/{id}','TestController@editsession')->name('test.editsession');
    Route::any('test/deletesession/{id}','TestController@deletesession')->name('test.deletesession');
    Route::any('test/importquestions/{id}','TestController@importquestions')->name('test.importquestions');
    Route::any('test/exportquestions/{id}','TestController@exportquestions')->name('test.exportquestions');
    Route::any('widget/index','WidgetController@index')->name('widget.index');
    Route::any('widget/create','WidgetController@create')->name('widget.create');
    Route::any('widget/process/{id}','WidgetController@process')->name('widget.process');
    Route::any('widget/delete/{id}','WidgetController@delete')->name('widget.delete');

    if(env('APP_MODE')=='saas'){
        $videoController = 'VideoSaasController';
    }
    else{
        $videoController = 'VideoController';
    }

    Route::any('video/index',$videoController.'@index')->name('video.index');
    Route::any('video/add',$videoController.'@add')->name('video.add');
    Route::any('video/delete/{id}',$videoController.'@delete')->name('video.delete');
    Route::any('video/removeimage/{id}',$videoController.'@removeimage')->name('video.removeimage');
    Route::any('video/play/{id}',$videoController.'@play')->name('video.play');
    Route::any('video/serve/{id}',$videoController.'@serve')->name('video.serve');
    Route::any('video/disk',$videoController.'@disk')->name('video.disk');
    Route::any('video/edit/{id}',$videoController.'@edit')->name('video.edit');


    Route::resource('blog-categories', 'BlogCategoriesController');
    Route::resource('blog-posts', 'BlogPostsController');

    Route::resource('articles', 'ArticlesController');

    Route::get('blog-remove-picture/{id}','BlogPostsController@removePicture')->name('blog.remove-picture');


    Route::get('templates','TemplatesController@index')->name('templates');
    Route::get('templates/install/{templateDir}','TemplatesController@install')->name('templates.install');
    Route::get('templates/settings','TemplatesController@settings')->name('templates.settings');
    Route::get('templates/colors','TemplatesController@colors')->name('templates.colors');
    Route::post('templates/save-options/{option}','TemplatesController@saveOptions')->name('templates.save-options');
    Route::post('templates/upload','TemplatesController@upload')->name('templates.upload');
    Route::post('templates/save-colors','TemplatesController@saveColors')->name('templates.save-colors');

    Route::get('payment-gateways','PaymentGatewayController@index')->name('payment-gateways');
    Route::get('payment-gateways/install/{method}','PaymentGatewayController@install')->name('payment-gateways.install');
    Route::get('payment-gateways/uninstall/{paymentMethod}','PaymentGatewayController@uninstall')->name('payment-gateways.uninstall');
    Route::get('payment-gateways/edit/{paymentMethod}','PaymentGatewayController@edit')->name('payment-gateways.edit');
    Route::post('payment-gateways/save/{paymentMethod}','PaymentGatewayController@save')->name('payment-gateways.save');

    Route::resource('roles', 'RolesController');
    Route::resource('admins', 'AdminsController');

    Route::get('menus/header','MenuController@headerMenu')->name('menus.header');
    Route::get('menus/footer','MenuController@footerMenu')->name('menus.footer');
    Route::get('menus/load-header','MenuController@loadHeaderMenu')->name('menus.load-header');
    Route::post('menus/save-header','MenuController@saveHeaderMenu')->name('menus.save-header');
    Route::post('menus/update-header/{headerMenu}','MenuController@updateHeaderMenu')->name('menus.update-header');
    Route::get('menus/delete-header/{headerMenu}','MenuController@deleteHeaderMenu')->name('menus.delete-header');

    Route::get('menus/load-footer','MenuController@loadFooterMenu')->name('menus.load-footer');
    Route::post('menus/save-footer','MenuController@saveFooterMenu')->name('menus.save-footer');
    Route::post('menus/update-footer/{footerMenu}','MenuController@updateFooterMenu')->name('menus.update-footer');
    Route::get('menus/delete-footer/{footerMenu}','MenuController@deleteFooterMenu')->name('menus.delete-footer');
    Route::get('student/search','StudentController@search')->name('students.search');
    Route::post('student/create-invoice','StudentController@createInvoice')->name('student.create-invoice');

    Route::get('student/code','StudentController@code')->name('student.code');

    Route::get('frontend','SettingController@frontend')->name('frontend');
    Route::post('frontend','SettingController@saveFrontend')->name('save-frontend');

    Route::get('dashboard-theme','SettingController@dashboard')->name('dashboard-theme');
    Route::post('dashboard-theme','SettingController@saveDashboard')->name('save-dashboard-theme');

//end admin routes

});

/*Route::group(['middleware'=>['auth','admin'],'prefix' => 'admin', 'as' => 'admin.','namespace'=>'Admin'],function() {

    Route::get('users','UsersController@index')->name('users.index');
    Route::get('users/delete/{user}','UsersController@destroy')->name('users.delete');


});*/



Route::group(['middleware'=>['auth','student'],'prefix' => 'student', 'as' => 'student.','namespace'=>'Student'],function() {

    Route::get('/dashboard','IndexController@index')->name('dashboard');
    Route::get('/course-details/{id}/{slug}','CatalogController@course')->name('course-details');
    Route::get('/session-details/{id}/{slug}','StudentController@timetable')->name('session-details');
    Route::any('assignment/index','AssignmentController@index')->name('assignment.index');
    Route::any('assignment/submit/{id}','AssignmentController@submit')->name('assignment.submit');
    Route::any('assignment/submissions','AssignmentController@submissions')->name('assignment.submissions');
    Route::any('assignment/edit/{id}','AssignmentController@edit')->name('assignment.edit');
    Route::any('assignment/delete/{id}','AssignmentController@delete')->name('assignment.delete');
    Route::any('assignment/view/{id}','AssignmentController@view')->name('assignment.view');
    Route::any('cart/index','CartController@index')->name('cart.index');
    Route::any('cart/setsession','CartController@setsession')->name('cart.setsession');
    Route::any('cart/remove','CartController@remove')->name('cart.remove');
    Route::any('cart/removecoupon','CartController@removecoupon')->name('cart.removecoupon');
    Route::any('cart/checkout','CartController@checkout')->name('cart.checkout');
    Route::any('cart/clear','CartController@clear')->name('cart.clear');
    Route::any('catalog/sessions','CatalogController@sessions')->name('catalog.sessions');
    Route::any('catalog/courses','CatalogController@courses')->name('catalog.courses');
    Route::any('catalog/course/{id}','CatalogController@course')->name('catalog.course');
    Route::any('course/intro/{id}','CourseController@intro')->name('course.intro');
    Route::any('course/class/{lesson}/{course}','CourseController@class')->name('course.class');
    Route::any('course/lecture/{lecture}/{course}','CourseController@lecture')->name('course.lecture');
    Route::any('course/bookmark','CourseController@bookmark')->name('course.bookmark');
    Route::any('course/loglecture','CourseController@loglecture')->name('course.loglecture');
    Route::any('course/classfile/{id}/{course}','CourseController@classfile')->name('course.classfile');
    Route::any('course/allclassfiles/{id}/{course}','CourseController@allclassfiles')->name('course.allclassfiles');
    Route::any('course/lecturefile/{id}/{course}','CourseController@lecturefile')->name('course.lecturefile');
    Route::any('course/alllecturefiles/{id}/{course}','CourseController@alllecturefiles')->name('course.alllecturefiles');
    Route::any('course/bookmarks','CourseController@bookmarks')->name('course.bookmarks');
    Route::any('course/serve/{id}','CourseController@serve')->name('course.serve');
    Route::any('download/index','DownloadController@index')->name('download.index');
    Route::any('download/files/{id}','DownloadController@files')->name('download.files');
    Route::any('download/file/{id}','DownloadController@file')->name('download.file');
    Route::any('download/allfiles/{id}','DownloadController@allfiles')->name('download.allfiles');
    Route::any('forum/index','ForumController@index')->name('forum.index');
    Route::any('forum/topics/{id}','ForumController@topics')->name('forum.topics');
    Route::any('forum/addtopic/{id}','ForumController@addtopic')->name('forum.addtopic');
    Route::any('forum/topic/{id}','ForumController@topic')->name('forum.topic');
    Route::any('forum/reply/{id}','ForumController@reply')->name('forum.reply');
    Route::any('forum/notifications/{id}','ForumController@notifications')->name('forum.notifications');
    Route::any('forum/deletetopic/{id}','ForumController@deletetopic')->name('forum.deletetopic');
    Route::any('index/index','IndexController@index')->name('index.index');
    Route::any('index/page','IndexController@page')->name('index.page');
    Route::any('index/cstyle','IndexController@cstyle')->name('index.cstyle');
    Route::any('index/contact','IndexController@contact')->name('index.contact');
    Route::any('index/showclass','IndexController@showclass')->name('index.showclass');
    Route::any('index/calendar','IndexController@calendar')->name('index.calendar');
    Route::any('index/migrate','IndexController@migrate')->name('index.migrate');
    Route::any('index/test','IndexController@test')->name('index.test');
    Route::any('index/terms','IndexController@terms')->name('index.terms');
    Route::any('index/privacy','IndexController@privacy')->name('index.privacy');
    Route::any('index/video','IndexController@video')->name('index.video');
    Route::any('index/changecurrency','IndexController@changecurrency')->name('index.changecurrency');
    Route::any('method/paystack','MethodController@paystack')->name('method.paystack');
    Route::any('method/stripe','MethodController@stripe')->name('method.stripe');
    Route::any('method/bank','MethodController@bank')->name('method.bank');
    Route::any('method/paypal','MethodController@paypal')->name('method.paypal');
    Route::any('method/twocheckout','MethodController@twocheckout')->name('method.twocheckout');
    Route::any('method/payu','MethodController@payu')->name('method.payu');
    Route::any('method/payusend','MethodController@payusend')->name('method.payusend');
    Route::any('method/payfast','MethodController@payfast')->name('method.payfast');
    Route::any('method/payumoney','MethodController@payumoney')->name('method.payumoney');
    Route::any('method/ipay','MethodController@ipay')->name('method.ipay');
    Route::any('method/rave','MethodController@rave')->name('method.rave');
    Route::any('method/paytabs','MethodController@paytabs')->name('method.paytabs');
    Route::any('mobile/load','MobileController@load')->name('mobile.load');
    Route::any('mobile/close','MobileController@close')->name('mobile.close');
    Route::any('payment/index','PaymentController@index')->name('payment.index');
    Route::any('payment/methodold','PaymentController@methodold')->name('payment.methodold');
    Route::any('payment/method','PaymentController@method')->name('payment.method');
    Route::any('student/index','StudentController@index')->name('student.index');
    Route::any('student/mysessions','StudentController@mysessions')->name('student.mysessions');
    Route::any('student/welcome','StudentController@welcome')->name('student.welcome');
    Route::any('student/profile','StudentController@profile')->name('student.profile');
    Route::get('student/billing','StudentController@billing')->name('student.billing');
    Route::post('student/billing','StudentController@saveBilling')->name('student.save-billing');
    Route::any('student/removeimage','StudentController@removeimage')->name('student.removeimage');
    Route::any('student/enroll','StudentController@enroll')->name('student.enroll');
    Route::any('student/cart','StudentController@cart')->name('student.cart');
    Route::any('student/setsession','StudentController@setsession')->name('student.setsession');
    Route::any('student/removesession','StudentController@removesession')->name('student.removesession');
    Route::any('student/classes','StudentController@classes')->name('student.classes');
    Route::any('student/password','StudentController@password')->name('student.password');
    Route::any('student/notes','StudentController@notes')->name('student.notes');
    Route::any('student/sessionnotes/{id}','StudentController@sessionnotes')->name('student.sessionnotes');
    Route::any('student/viewnote/{id}','StudentController@viewnote')->name('student.viewnote');
    Route::any('student/timetable/{id}','StudentController@timetable')->name('student.timetable');
    Route::any('student/discussion','StudentController@discussion')->name('student.discussion');
    Route::any('student/adddiscussion','StudentController@adddiscussion')->name('student.adddiscussion');
    Route::any('student/addreply/{id}','StudentController@addreply')->name('student.addreply');
    Route::any('student/viewdiscussion/{id}','StudentController@viewdiscussion')->name('student.viewdiscussion');
    Route::any('student/certificates','StudentController@certificates')->name('student.certificates');
    Route::any('student/certificate','StudentController@certificate')->name('student.certificate');
    Route::any('student/downloadcertificate3','StudentController@downloadcertificate3')->name('student.downloadcertificate3');
    Route::any('student/downloadcertificate1','StudentController@downloadcertificate1')->name('student.downloadcertificate1');
    Route::any('student/downloadcertificate/{id}','StudentController@downloadcertificate')->name('student.downloadcertificate');
    Route::any('student/downloadcertificate2','StudentController@downloadcertificate2')->name('student.downloadcertificate2');
    Route::any('student/invoices','StudentController@invoices')->name('student.invoices');
    Route::any('student/payinvoice/{id}','StudentController@payinvoice')->name('student.payinvoice');
    Route::any('student/surveys','StudentController@surveys')->name('student.surveys');

    Route::any('test/index','TestController@index')->name('test.index');
    Route::any('test/taketest/{id}','TestController@taketest')->name('test.taketest');
    Route::any('test/processtest/{id}','TestController@processtest')->name('test.processtest');
    Route::any('test/starttest/{id}','TestController@starttest')->name('test.starttest');
    Route::any('test/result/{id}','TestController@result')->name('test.result');
    Route::any('test/testresults/{id}','TestController@testresults')->name('test.testresults');
    Route::any('test/reportcard/{id}','TestController@reportcard')->name('test.reportcard');
    Route::any('test/statement','TestController@statement')->name('test.statement');



});

Route::group(['namespace'=>'Site','middleware'=>'site'],function(){


    Route::group(['middleware'=>'frontend'],function(){
        Route::get('/','HomeController@index')->name('homepage');
        Route::get('/contact','HomeController@contact')->name('contact');
        Route::post('/contact/send-mail','HomeController@sendMail')->name('contact.send-mail');
        Route::get('/blog','BlogController@index')->name('blog');
        Route::get('/blog/{blogPost}/{slug?}','BlogController@post')->name('blog.post');
    });

    Route::get('/courses','CatalogController@courses')->name('courses');
    Route::get('/courses/{course}/{slug?}','CatalogController@course')->name('course');
    Route::get('/sessions','CatalogController@sessions')->name('sessions');



    Route::any('/cart','CartController@index')->name('cart');
    Route::get('/cart/add/{course}','CartController@add')->name('cart.add');
    Route::get('/cart/add-certificate/{certificate}','CartController@addCertificate')->name('cart.add-certificate');
    Route::get('/cart/remove/{course}','CartController@remove')->name('cart.remove');
    Route::get('/cart/remove-certificate/{certificate}','CartController@removeCertificate')->name('cart.remove-certificate');
    Route::post('/save-cart','CartController@save')->name('cart.save');
    Route::get('/cart/remove-coupon','CartController@removeCoupon')->name('cart.remove-coupon');
    Route::post('/cart/checkout','CartController@process')->name('cart.process');
    Route::get('cart/change-currency/{currency}','CartController@currency')->name('cart.currency');
    Route::get('mobile/pay','CartController@mobileLoad')->name('mobile-load');
    Route::get('mobile/close','CartController@mobileClose')->name('mobile-close');

    Route::group(['middleware'=>'auth'],function (){
        Route::get('/cart/checkout','CartController@checkout')->name('cart.checkout');
        Route::get('/cart/complete','CartController@complete')->name('cart.complete');
        Route::any('/cart/callback/{code}','CartController@callback')->name('cart.callback');
    });


    Route::any('/ipn/{code}','CartController@ipn')->name('cart.ipn');
    Route::any('/cart/method/{code}/{function}','CartController@method')->name('cart.method');

});

//this route should point to a controller that fetches articles
Route::get('/{slug}','Site\HomeController@article')->name('article')->middleware('frontend');
Route::get('/cron/{method}','Site\HomeController@cron')->name('cron');
