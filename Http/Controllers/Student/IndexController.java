package Demo.App.Http.Controllers.Student;

import java.util.*;
import javax.persistence.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;

@Controller
@RequestMapping("/student")
public class IndexController {

    @Autowired
    private CatalogController catalogController;

    @Autowired
    private StudentController studentController;

    @Autowired
    private DownloadController downloadController;

    @Autowired
    private AssignmentController assignmentController;

    @Autowired
    private StudentRepository studentRepository;

    @Autowired
    private TestGradeRepository testGradeRepository;

    @Autowired
    private AssignmentSubmissionRepository assignmentSubmissionRepository;

    @Autowired
    private AttendanceRepository attendanceRepository;

    @Autowired
    private CourseRepository courseRepository;

    @GetMapping("/index")
    public String index(Model model) {
        Map<String, Object> output = new HashMap<>();

        // Sessions
        Map<String, Object> sessionsData = catalogController.sessions();
        output.put("sessions", sessionsData);
        ((Page<?>) sessionsData.get("paginator")).setPageSize(5);

        // Courses
        Map<String, Object> coursesData = catalogController.courses();
        output.put("courses", coursesData);
        ((Page<?>) coursesData.get("paginator")).setPageSize(5);

        Long studentId = getCurrentUserId();

        // My Sessions
        Map<String, Object> mySessionsData = studentController.mysessions();
        output.put("mysessions", mySessionsData);
        ((Page<?>) mySessionsData.get("paginator")).setPageSize(3);

        // Notes
        Map<String, Object> notesData = studentController.notes();
        output.put("notes", notesData);
        ((Page<?>) notesData.get("paginator")).setPageSize(5);

        // Downloads
        Map<String, Object> downloadsData = downloadController.index();
        output.put("downloads", downloadsData);
        ((Page<?>) downloadsData.get("paginator")).setPageSize(5);

        // Discussions
        Map<String, Object> discussionsData = studentController.discussion();
        output.put("discussions", discussionsData);
        ((Page<?>) discussionsData.get("paginator")).setPageSize(5);

        // Homework
        Map<String, Object> homeworkData = assignmentController.index();
        output.put("homework", homeworkData);
        ((Page<?>) homeworkData.get("paginator")).setPageSize(100);

        int totalHomework = (int) homeworkData.get("total");
        boolean homeworkPresent = false;
       
        output.put("homeworkPresent", homeworkPresent);

        output.put("controller", this);
        output.put("student", studentRepository.findById(studentId).orElse(null));
        output.put("gradeTable", testGradeRepository);

        // Certificates
        Map<String, Object> certificateData = studentController.certificates();
        output.put("certificate", certificateData);
        ((Page<?>) certificateData.get("paginator")).setPageSize(7);

        // Forum Topics
        Page<ForumTopic> forumTopics = getForumTopics(studentId);
        forumTopics.setPageSize(10);
        output.put("forumTopics", forumTopics);

        output.put("pageTitle", "Dashboard");

        model.addAllAttributes(output);
        return "student/index/index";
    }

    public int getStudentProgress(Long sessionId) {
        Course session = courseRepository.findById(sessionId).orElse(null);
        if (session == null) {
            return 0;
        }

        int totalLessons = session.getLessons().size();
        int totalAttended = attendanceRepository.getTotalDistinctForStudentInSession(getCurrentUserId(), sessionId);

        if (totalLessons == 0) {
            totalLessons = 1;
        }

        int percentage = (int) Math.round((double) totalAttended / totalLessons * 100);
        return percentage;
    }

    private Long getCurrentUserId() {
        // Implement method to get current user ID
        return 0L;
    }

    private Page<ForumTopic> getForumTopics(Long studentId) {
        // Implement method to get forum topics
        return null;
    }
}

