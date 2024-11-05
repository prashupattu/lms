package com.app.controllers.admin;

import com.app.controllers.Controller;
import com.app.v2.model.LectureTable;
import com.app.v2.model.LessonTable;
import com.app.v2.model.SessionLessonTable;
import com.app.v2.model.StudentSessionTable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.servlet.view.RedirectView;
import com.app.controllers.student.CourseController;

@RestController
@RequestMapping("/admin")
public class CourseController extends CourseController {
    private static final String MODULE = "admin";

    public CourseController() {
        // No need for MODULE definition as it's a constant now
    }

    @Override
    public Boolean getId() {
        return false;
    }

    @Override
    public Object getStudent() {
        class Student {
            public int id = 1;
        }
        return new Student();
    }

    @Override
    public boolean validateEnrollment(int sessionId) {
        return true;
    }

    @Override
    public boolean verifyClass(int id, Object session, boolean abort) {
        return true;
    }

    @PostMapping("/loglecture")
    public RedirectView loglecture(@RequestBody LogLectureRequest request) {
        int lecture = request.getLectureId();
        int session = request.getCourseId();
        LectureTable lectureTable = new LectureTable();
        Object lectureRow = lectureTable.getRecord(lecture);
        LessonTable lessonTable = new LessonTable();
        SessionLessonTable sessionLessonTable = new SessionLessonTable();
        Object next = lectureTable.getNextLecture(lecture);
        if (next != null) {
            return new RedirectView("/" + MODULE + "/course/lecture?lecture=" + ((LectureNext)next).id + "&course=" + session);
        }

        Object nextClass = sessionLessonTable.getNextLessonInSession(session, ((LectureRow)lectureRow).getLessonId(), 'c');
        if (nextClass != null) {
            // forward to the next class
            return new RedirectView("/" + MODULE + "/course/class?course=" + session + "&lesson=" + ((NextLesson)nextClass).getLessonId());
        } else {
            // classes are over
            // Assuming flashMessage is a utility method
            flashMessage("course-complete-msg");
            return new RedirectView("/admin/dashboard");
        }
    }

    // Helper classes for request body and response
    private static class LogLectureRequest {
        private int lectureId;
        private int courseId;

        public int getLectureId() {
            return lectureId;
        }

        public int getCourseId() {
            return courseId;
        }
    }

    private static class LectureNext {
        public int id;
    }

    private static class LectureRow {
        private int lessonId;

        public int getLessonId() {
            return lessonId;
        }
    }

    private static class NextLesson {
        private int lessonId;

        public int getLessonId() {
            return lessonId;
        }
    }

    // Utility method (implementation not provided)
    private void flashMessage(String message) {
        // Implementation for flash message
    }
}

