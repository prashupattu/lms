package com.example.api.v1;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api/v1")
public class ApiController {

    @Value("${app.mode}")
    private String appMode;

    private final AppController appController;
    private final StudentController studentController;
    private final CourseController courseController;
    private final ArticlesController articlesController;
    private final BlogController blogController;
    private final InvoiceController invoiceController;
    private final TestController testController;
    private final CertificateController certificateController;
    private final DownloadController downloadController;
    private final AssignmentsController assignmentsController;
    private final DiscussionController discussionController;
    private final ForumController forumController;

    public ApiController(AppController appController, StudentController studentController, CourseController courseController,
                         ArticlesController articlesController, BlogController blogController, InvoiceController invoiceController,
                         TestController testController, CertificateController certificateController, DownloadController downloadController,
                         AssignmentsController assignmentsController, DiscussionController discussionController, ForumController forumController) {
        this.appController = appController;
        this.studentController = studentController;
        this.courseController = courseController;
        this.articlesController = articlesController;
        this.blogController = blogController;
        this.invoiceController = invoiceController;
        this.testController = testController;
        this.certificateController = certificateController;
        this.downloadController = downloadController;
        this.assignmentsController = assignmentsController;
        this.discussionController = discussionController;
        this.forumController = forumController;
    }

    // Account and config routes
    @PostMapping("/accounts")
    public ResponseEntity<?> login() { return appController.login(); }

    @PutMapping("/accounts")
    public ResponseEntity<?> update() { return appController.update(); }

    @GetMapping("/configs")
    public ResponseEntity<?> setup() { return appController.setup(); }

    // Student routes
    @PostMapping("/students")
    public ResponseEntity<?> createStudent() { return studentController.create(); }

    // Course routes
    @GetMapping("/sessions")
    public ResponseEntity<?> getSessions() { return courseController.getSessions(); }

    @GetMapping("/courses")
    public ResponseEntity<?> getCourses() { return courseController.getCourses(); }

    @GetMapping("/courses/{id}")
    public ResponseEntity<?> getCourse(@PathVariable Long id) { return courseController.getSession(id); }

    @GetMapping("/sessions/{id}")
    public ResponseEntity<?> getSession(@PathVariable Long id) { return courseController.getSession(id); }

    // Articles and Blog routes
    @GetMapping("/articles")
    public ResponseEntity<?> articles() { return articlesController.articles(); }

    @GetMapping("/articles/{id}")
    public ResponseEntity<?> getArticle(@PathVariable Long id) { return articlesController.getArticle(id); }

    @GetMapping("/posts")
    public ResponseEntity<?> posts() { return blogController.posts(); }

    @GetMapping("/posts/{id}")
    public ResponseEntity<?> getPost(@PathVariable Long id) { return blogController.getPost(id); }

    // Token route
    @GetMapping("/tokens/{id}")
    public ResponseEntity<?> getToken(@PathVariable Long id) { return studentController.getToken(id); }

    // Video route based on APP_MODE
    @GetMapping("/videos/{id}")
    public ResponseEntity<?> getVideo(@PathVariable Long id) {
        return appMode.equals("saas") ? courseController.getSaaSVideo(id) : courseController.getVideo(id);
    }

    @GetMapping("/videos/{id}/index.m3u8")
    public ResponseEntity<?> getVideoIndex(@PathVariable Long id) { return courseController.getSaaSVideo(id); }

    // Password reset route
    @PostMapping("/password-resets")
    public ResponseEntity<?> resetPassword() { return studentController.resetPassword(); }

    // Protected routes (require 'student.api' middleware equivalent)
    @DeleteMapping("/delete-account")
    public ResponseEntity<?> deleteAccount() { return studentController.deleteAccount(); }

    @PostMapping("/student-passwords")
    public ResponseEntity<?> changePassword() { return studentController.changePassword(); }

    @GetMapping("/students/{id}")
    public ResponseEntity<?> getProfile(@PathVariable Long id) { return studentController.getProfile(id); }

    @PutMapping("/students/{id}")
    public ResponseEntity<?> updateProfile(@PathVariable Long id) { return studentController.updateProfile(id); }

    // Additional routes based on your Laravel routes configuration

    // Implement other routes following the pattern above
}
