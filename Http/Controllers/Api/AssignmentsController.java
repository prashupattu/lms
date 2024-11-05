import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.nio.file.StandardCopyOption;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.validation.BindingResult;
import org.springframework.validation.annotation.Validated;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.multipart.MultipartFile;

import com.fasterxml.jackson.databind.ObjectMapper;

@RestController
@RequestMapping("/api/assignments")
public class AssignmentsController {

    @Autowired
    private StudentSessionTable studentSessionTable;

    @Autowired
    private AssignmentSubmissionTable submissionTable;

    @Autowired
    private AssignmentTable assignmentTable;

    @Autowired
    private HomeworkTable homeworkTable;

    @Autowired
    private SessionTable sessionTable;

    @Autowired
    private RevisionNoteTable revisionNoteTable;

    private String uploadDir;

    public AssignmentsController() {
        String user = "";
        if (System.getenv("USER_ID") != null) {
            user = "/" + System.getenv("USER_ID");
        }
        this.uploadDir = "usermedia" + user + "/student_uploads/" + new SimpleDateFormat("yyyy_MM").format(new Date());
    }

    private void makeUploadDir() {
        File path = new File("public/" + this.uploadDir);
        if (!path.exists()) {
            path.mkdirs();
        }
    }

    @GetMapping("/assignments")
    public ResponseEntity<Map<String, Object>> getAssignments(HttpServletRequest request) {
        Map<String, String[]> params = request.getParameterMap();
        int studentId = getApiStudentId();
        int page = params.containsKey("page") ? Integer.parseInt(params.get("page")[0]) : 1;
        int rowsPerPage = 30;
        int total = studentSessionTable.getTotalAssignments(studentId);
        int totalPages = (int) Math.ceil((double) total / rowsPerPage);
        List<Map<String, Object>> records = new ArrayList<>();

        if (page <= totalPages) {
            List<Map<String, Object>> paginator = studentSessionTable.getAssignments(studentId);
            for (Map<String, Object> row : paginator) {
                Map<String, Object> data = new HashMap<>(row);
                data.put("due_at", data.get("due_date"));
                if (data.get("due_date") != null) {
                    data.put("due_date", ((Date) data.get("due_date")).getTime());
                }
                data.put("created_on", ((Date) row.get("created_at")).getTime());
                data.put("account_id", row.get("admin_id"));
                data.put("session_name", row.get("course_name"));
                data.put("has_submission", !submissionTable.hasSubmission(studentId, (Integer) row.get("assignment_id")));

                if ((Boolean) data.get("has_submission")) {
                    Map<String, Object> submission = submissionTable.getAssignment((Integer) row.get("assignment_id"), getApiStudentId());
                    submission.put("assignment_submission_id", submission.get("id"));
                    submission.put("created", ((Date) submission.get("created_at")).getTime());
                    submission.put("modified", ((Date) submission.get("updated_at")).getTime());
                    data.put("submission", submission);
                }
                records.add(data);
            }
        }

        Map<String, Object> response = new HashMap<>();
        response.put("total_pages", totalPages);
        response.put("current_page", page);
        response.put("total", total);
        response.put("rows_per_page", rowsPerPage);
        response.put("records", records);

        return new ResponseEntity<>(response, HttpStatus.OK);
    }

    @GetMapping("/assignment/{id}")
    public ResponseEntity<Map<String, Object>> getAssignment(HttpServletRequest request, @PathVariable int id) {
        Map<String, Object> data = assignmentTable.findById(id);
        if (data.get("due_date") != null) {
            data.put("due_date", ((Date) data.get("due_date")).getTime());
        }
        data.put("created_on", ((Date) data.get("created_at")).getTime());
        data.put("account_id", data.get("admin_id"));
        data.put("session_id", data.get("course_id"));
        data.put("assignment_id", data.get("id"));
        data.put("assignment_type", data.get("type"));

        int studentId = getApiStudentId();
        data.put("has_submission", !submissionTable.hasSubmission(studentId, (Integer) data.get("id")));
        if ((Boolean) data.get("has_submission")) {
            Map<String, Object> submission = submissionTable.getAssignment((Integer) data.get("id"), getApiStudentId());
            submission.put("assignment_submission_id", submission.get("id"));
            submission.put("created", ((Date) submission.get("created_at")).getTime());
            submission.put("modified", ((Date) submission.get("updated_at")).getTime());
            data.put("submission", submission);
        }

        return new ResponseEntity<>(data, HttpStatus.OK);
    }

    @PostMapping("/submission")
    public ResponseEntity<Map<String, Object>> createSubmission(@Validated @RequestBody Map<String, Object> data, BindingResult bindingResult) {
        if (bindingResult.hasErrors()) {
            return new ResponseEntity<>(Map.of("status", false, "msg", bindingResult.getAllErrors().toString()), HttpStatus.BAD_REQUEST);
        }

        int id = (Integer) data.get("assignment_id");
        Map<String, Object> assignmentRow = assignmentTable.getRecord(id);

        validateAssignment(id);

        if (submissionTable.hasSubmission(getApiStudentId(), id)) {
            Map<String, Object> submissionRow = submissionTable.getAssignment(id, getApiStudentId());
            if (canEdit((Integer) submissionRow.get("id"))) {
                return new ResponseEntity<>(Map.of("status", false, "msg", "Submission already exists", "redirect", true, "id", submissionRow.get("id")), HttpStatus.OK);
            } else {
                return new ResponseEntity<>(Map.of("status", false, "msg", "Cannot edit submission", "redirect", false), HttpStatus.OK);
            }
        }

        if (assignmentRow.get("type").equals("f") || assignmentRow.get("type").equals("b")) {
            MultipartFile file = (MultipartFile) data.get("file_path");
            if (file == null) {
                return new ResponseEntity<>(Map.of("status", false, "msg", "File is required"), HttpStatus.BAD_REQUEST);
            }

            String newPath = this.uploadDir + "/" + System.currentTimeMillis() + getApiStudentId() + "_" + sanitize(getApiStudent().getUser().getName() + "_" + getApiStudent().getUser().getLastName()) + "." + getExtensionForMime(file.getContentType());
            makeUploadDir();
            try {
                Files.copy(file.getInputStream(), Paths.get(newPath), StandardCopyOption.REPLACE_EXISTING);
            } catch (IOException e) {
                e.printStackTrace();
            }

            data.put("file_path", newPath);
        }

        data.put("content", clean((String) data.get("content")));
        data.put("student_id", getApiStudentId());
        data.put("assignment_id", id);
        data.put("editable", 1);

        int aid = submissionTable.addRecord(data);
        if ((Boolean) data.get("submitted") && aid > 0) {
            Map<String, Object> student = getApiStudent();
            String message = student.get("user").getName() + " " + student.get("user").getLastName() + " has submitted " + assignmentRow.get("title");
            notifyAdmin((Integer) assignmentRow.get("admin_id"), "New homework submission", message);
        }

        return new ResponseEntity<>(Map.of("status", true, "record", submissionTable.findById(aid)), HttpStatus.OK);
    }

    @PutMapping("/submission/{id}")
    public ResponseEntity<Map<String, Object>> updateSubmission(@PathVariable int id, @Validated @RequestBody Map<String, Object> data, BindingResult bindingResult) {
        if (bindingResult.hasErrors()) {
            return new ResponseEntity<>(Map.of("status", false, "msg", bindingResult.getAllErrors().toString()), HttpStatus.BAD_REQUEST);
        }

        Map<String, Object> row = submissionTable.getRecord(id);
        int assignmentId = (Integer) row.get("assignment_id");
        Map<String, Object> assignmentRow = assignmentTable.getRecord(assignmentId);

        if (!canEdit(id)) {
            return new ResponseEntity<>(Map.of("status", false, "msg", "Cannot edit submission", "redirect", false), HttpStatus.OK);
        }

        if ((assignmentRow.get("type").equals("f") || assignmentRow.get("type").equals("b")) && row.get("file_path") == null) {
            MultipartFile file = (MultipartFile) data.get("file_path");
            if (file == null) {
                return new ResponseEntity<>(Map.of("status", false, "msg", "File is required"), HttpStatus.BAD_REQUEST);
            }

            String newPath = this.uploadDir + "/" + System.currentTimeMillis() + getApiStudentId() + "_" + sanitize(getApiStudent().getUser().getName() + "_" + getApiStudent().getUser().getLastName()) + "." + getExtensionForMime(file.getContentType());
            makeUploadDir();
            try {
                Files.copy(file.getInputStream(), Paths.get(newPath), StandardCopyOption.REPLACE_EXISTING);
            } catch (IOException e) {
                e.printStackTrace();
            }

            data.put("file_path", newPath);
        }

        data.put("content", clean((String) data.get("content")));
        if (data.containsKey("_method")) {
            data.remove("_method");
        }

        submissionTable.update(data, id);

        return new ResponseEntity<>(Map.of("status", true, "record", submissionTable.findById(id)), HttpStatus.OK);
    }

    @DeleteMapping("/submission/{id}")
    public ResponseEntity<Map<String, Object>> deleteSubmission(@PathVariable int id) {
        Map<String, Object> row = submissionTable.getRecord(id);
        Map<String, Object> assignmentRow = assignmentTable.getRecord((Integer) row.get("assignment_id"));

        if (row.get("editable") == null || ((Boolean) row.get("editable") && ((Date) assignmentRow.get("due_date")).getTime() < System.currentTimeMillis())) {
            return new ResponseEntity<>(Map.of("status", false, "msg", "Cannot delete submission"), HttpStatus.OK);
        }

        validateApiOwner(row);

        if (row.get("file_path") != null) {
            new File((String) row.get("file_path")).delete();
        }

        submissionTable.deleteRecord(id);
        return new ResponseEntity<>(Map.of("status", true, "msg", "Homework deleted", "assignment", assignmentRow), HttpStatus.OK);
    }

    @DeleteMapping("/submission/file")
    public ResponseEntity<Map<String, Object>> deleteSubmissionFile(AssignmentSubmission assignmentSubmission) {
        validateApiOwner(assignmentSubmission);
        if (assignmentSubmission.getFilePath() != null) {
            new File(assignmentSubmission.getFilePath()).delete();
        }
        assignmentSubmission.setFilePath(null);
        assignmentSubmission.save();
        return new ResponseEntity<>(Map.of("status", true, "msg", "Changes saved"), HttpStatus.OK);
    }

    private void validateAssignment(int id) {
        Map<String, Object> assignmentRow = assignmentTable.getRecord(id);
        if ((Boolean) assignmentRow.get("allow_late") != 1 && ((Date) assignmentRow.get("due_date")).getTime() < System.currentTimeMillis()) {
            throw new IllegalArgumentException("Assignment is past due date");
        }

        if (!studentSessionTable.enrolled(getApiStudentId(), (Integer) assignmentRow.get("course_id"))) {
            throw new IllegalArgumentException("You are not enrolled in this course");
        }
    }

    private boolean canEdit(int id) {
        Map<String, Object> row = submissionTable.getRecord(id);
        Map<String, Object> assignmentRow = assignmentTable.getRecord((Integer) row.get("assignment_id"));
        return (Boolean) row.get("editable") && ((Boolean) assignmentRow.get("allow_late") == 1 || ((Date) assignmentRow.get("due_date")).getTime() >= System.currentTimeMillis());
    }

    @GetMapping("/revision-notes/sessions")
    public ResponseEntity<Map<String, Object>> revisionNotesSessions(HttpServletRequest request) {
        Map<String, String[]> params = request.getParameterMap();
        int page = params.containsKey("page") ? Integer.parseInt(params.get("page")[0]) : 1;
        int rowsPerPage = 30;
        int studentId = getApiStudentId();
        int total = studentSessionTable.getTotalStudentForumRecords(studentId);
        int totalPages = (int) Math.ceil((double) total / rowsPerPage);
        List<Map<String, Object>> records = new ArrayList<>();

        if (page <= totalPages) {
            List<Map<String, Object>> paginator = studentSessionTable.getStudentForumRecords(true, studentId);
            for (Map<String, Object> row : paginator) {
                row.put("total_notes", revisionNoteTable.countByCourseId((Integer) row.get("course_id")));
                row.put("session_name", row.get("name"));
                row.put("session_date", ((Date) row.get("start_date")).getTime());
                row.put("enrollment_closes", stamp((Date) row.get("enrollment_closes")));
                row.put("session_type", row.get("type"));
                row.put("session_id", row.get("course_id"));
                records.add(row);
            }
        }

        Map<String, Object> response = new HashMap<>();
        response.put("total_pages", totalPages);
        response.put("current_page", page);
        response.put("total", total);
        response.put("rows_per_page", rowsPerPage);
        response.put("records", records);

        return new ResponseEntity<>(response, HttpStatus.OK);
    }

    @GetMapping("/revision-notes")
    public ResponseEntity<Map<String, Object>> revisionNotes(HttpServletRequest request) {
        Map<String, String[]> params = request.getParameterMap();
        int courseId = Integer.parseInt(params.get("course_id")[0]);

        if (!enrolledInSession(courseId)) {
            return new ResponseEntity<>(Map.of("status", false), HttpStatus.OK);
        }

        List<Map<String, Object>> rowset = revisionNoteTable.findByCourseId(courseId);
        List<Map<String, Object>> data = new ArrayList<>();

        for (Map<String, Object> value : rowset) {
            Map<String, Object> row = new HashMap<>(value);
            row.put("homework_id", row.get("id"));
            row.put("session_id", row.get("course_id"));
            row.put("lesson_name", lessonTable.findById((Integer) row.get("lesson_id")).getName());
            row.put("date", stamp((Date) row.get("created_at")));
            data.add(row);
        }

        return new ResponseEntity<>(Map.of("data", data), HttpStatus.OK);
    }

    @GetMapping("/revision-note/{id}")
    public ResponseEntity<Map<String, Object>> getRevisionNote(@PathVariable int id) {
        Map<String, Object> row = revisionNoteTable.findById(id);
        row.put("session_id", row.get("course_id"));
        if (!enrolledInSession((Integer) row.get("course_id"))) {
            return new ResponseEntity<>(Map.of("status", false), HttpStatus.OK);
        }

        return new ResponseEntity<>(row, HttpStatus.OK);
    }

    private boolean enrolledInSession(int id) {
        return studentSessionTable.enrolled(getApiStudentId(), id);
    }

    @GetMapping("/submission/file/{id}")
    public void getSubmissionFile(HttpServletResponse response, @PathVariable int id) throws IOException {
        AssignmentSubmission assignmentSubmission = submissionTable.findById(id);
        validateApiOwner(assignmentSubmission);
        if (assignmentSubmission.getFilePath() == null || !new File(assignmentSubmission.getFilePath()).exists()) {
            response.sendError(HttpServletResponse.SC_NOT_FOUND);
            return;
        }

        File file = new File(assignmentSubmission.getFilePath());
        String downloadName = file.getName();
        response.setContentType(Files.probeContentType(file.toPath()));
        response.setHeader("Content-Disposition", "attachment; filename=\"" + downloadName + "\"");
        response.setContentLengthLong(file.length());
        Files.copy(file.toPath(), response.getOutputStream());
    }

    private int getApiStudentId() {
        // Implement this method to get the API student ID
        return 0;
    }

    private Map<String, Object> getApiStudent() {
        // Implement this method to get the API student details
        return new HashMap<>();
    }

    private String sanitize(String input) {
        // Implement this method to sanitize the input string
        return input;
    }

    private String clean(String input) {
        // Implement this method to clean the input string
        return input;
    }

    private String getExtensionForMime(String mimeType) {
        // Implement this method to get the file extension for the given MIME type
        return "";
    }

    private void validateApiOwner(Map<String, Object> row) {
        // Implement this method to validate the API owner
    }

    private void validateApiOwner(AssignmentSubmission assignmentSubmission) {
        // Implement this method to validate the API owner
    }

    private void notifyAdmin(int adminId, String subject, String message) {
        // Implement this method to notify the admin
    }

    private long stamp(Date date) {
        // Implement this method to convert the date to a timestamp
        return date.getTime();
    }
}

