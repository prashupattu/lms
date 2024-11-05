import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import com.example.model.AccountsTable;
import com.example.model.Assignment;
import com.example.model.AssignmentSubmissionTable;
import com.example.model.AssignmentTable;
import com.example.model.SessionInstructorTable;
import com.example.model.SessionLessonTable;
import com.example.model.SessionTable;
import com.example.util.BaseForm;
import com.example.util.HelperTrait;
import com.example.util.InputFilter;

@Controller
@RequestMapping("/admin/assignment")
public class AssignmentController {

    @Autowired
    private AssignmentTable assignmentTable;

    @Autowired
    private AssignmentSubmissionTable assignmentSubmissionTable;

    @Autowired
    private SessionTable sessionTable;

    @Autowired
    private SessionInstructorTable sessionInstructorTable;

    @Autowired
    private SessionLessonTable sessionLessonTable;

    @Autowired
    private AccountsTable accountsTable;

    @GetMapping("/index")
    public String index(HttpServletRequest request, Model model) {
        int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
        int itemCountPerPage = 30;

        List<Assignment> paginator = assignmentTable.getPaginatedRecords(true);
        int total = assignmentTable.getTotalAdminAssignments(getAdminId());

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Homework");
        model.addAttribute("submissionTable", assignmentSubmissionTable);
        model.addAttribute("total", total);

        return "admin/assignment/index";
    }

    @GetMapping("/add")
    public String add(HttpServletRequest request, Model model) {
        Map<String, Object> output = new HashMap<>();
        BaseForm form = getAssignmentForm();
        InputFilter filter = getAssignmentFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String> data = new HashMap<>();
            request.getParameterMap().forEach((key, value) -> data.put(key, value[0]));

            form.setData(data);
            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                array.put(assignmentTable.getPrimary(), 0);
                array.put("due_date", !data.get("due_date").isEmpty() ? getDateString(data.get("due_date")) : null);
                array.put("opening_date", !data.get("opening_date").isEmpty() ? getDateString(data.get("opening_date")) : null);
                array.put("admin_id", getAdmin().getId());

                assignmentTable.saveRecord(array);

                if (!data.get("notify_students").isEmpty()) {
                    String subject = "New Homework";
                    String message = "New homework assigned: " + data.get("title") + " - " + data.get("instruction") + " - Due: " + data.get("due_date");
                    String sms = "New homework: " + data.get("title") + " - Due: " + data.get("due_date");

                    notifySessionStudents(data.get("course_id"), subject, message, true, sms);
                }

                request.getSession().setAttribute("flash_message", "Record Added");
                return "redirect:/admin/assignment/index";
            } else {
                output.put("flash_message", "Save failed");
            }
        }

        output.put("form", form);
        output.put("pageTitle", "Add Homework");
        output.put("action", "add");
        output.put("id", null);
        model.addAllAttributes(output);

        return "admin/assignment/add";
    }

    @GetMapping("/edit")
    public String edit(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Map<String, Object> output = new HashMap<>();
        BaseForm form = getAssignmentForm();
        InputFilter filter = getAssignmentFilter();

        Assignment row = assignmentTable.getRecord(id);
        String oldName = row.getTitle();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String> data = new HashMap<>();
            request.getParameterMap().forEach((key, value) -> data.put(key, value[0]));

            form.setData(data);
            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                array.put(assignmentTable.getPrimary(), id);
                array.put("due_date", !data.get("due_date").isEmpty() ? getDateString(data.get("due_date")) : null);
                array.put("opening_date", !data.get("opening_date").isEmpty() ? getDateString(data.get("opening_date")) : null);

                assignmentTable.saveRecord(array);

                if (!data.get("notify_students").isEmpty()) {
                    String subject = "New Homework";
                    String message = "New homework assigned: " + data.get("title") + " - " + data.get("instruction") + " - Due: " + data.get("due_date");
                    String textMessage = "New homework: " + data.get("title") + " - Due: " + data.get("due_date");

                    notifySessionStudents(data.get("course_id"), subject, message, false, textMessage);
                }

                request.getSession().setAttribute("flash_message", "Changes Saved!");
                return "redirect:/admin/assignment/index";
            } else {
                output.put("flash_message", getFormErrors(form));
            }
        } else {
            Map<String, Object> data = getObjectProperties(row);
            data.put("due_date", !data.get("due_date").toString().isEmpty() ? showDate("Y-m-d", data.get("due_date").toString()) : null);
            data.put("opening_date", !data.get("opening_date").toString().isEmpty() ? showDate("Y-m-d", data.get("opening_date").toString()) : null);
            form.setData(data);
        }

        output.put("form", form);
        output.put("id", id);
        output.put("pageTitle", "Edit Homework");
        output.put("row", row);
        output.put("action", "edit");
        model.addAllAttributes(output);

        return "admin/assignment/add";
    }

    @GetMapping("/view")
    public String view(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Assignment row = assignmentTable.getRecord(id);
        Map<String, Object> data = new HashMap<>();
        data.put("row", row);
        data.put("table", assignmentSubmissionTable);
        data.put("accountsTable", accountsTable);

        model.addAllAttributes(data);
        return "admin/assignment/view";
    }

    @GetMapping("/delete")
    public String delete(HttpServletRequest request, @RequestParam("id") int id) {
        assignmentTable.deleteRecord(id);
        request.getSession().setAttribute("flash_message", "Record deleted");
        return "redirect:/admin/assignment/index";
    }

    @GetMapping("/submissions")
    public String submissions(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Assignment row = assignmentTable.getRecord(id);
        List<AssignmentSubmissionTable> paginator = assignmentSubmissionTable.getAssignmentPaginatedRecords(true, id);
        int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
        int itemCountPerPage = 30;

        int assignmentTotal = assignmentSubmissionTable.getTotalSubmittedForAssignment(id);
        int totalPassed = assignmentSubmissionTable.getTotalPassedForAssignment(id, row.getPassmark());
        int totalFailed = assignmentTotal - totalPassed;
        double average = assignmentSubmissionTable.getAverageScore(id);

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Homework Submissions: " + row.getTitle());
        model.addAttribute("total", assignmentTotal);
        model.addAttribute("passed", totalPassed);
        model.addAttribute("failed", totalFailed);
        model.addAttribute("average", average);
        model.addAttribute("row", row);
        model.addAttribute("id", id);

        return "admin/assignment/submissions";
    }

    @GetMapping("/viewsubmission")
    public String viewsubmission(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        AssignmentSubmissionTable row = assignmentSubmissionTable.getSubmission(id);
        BaseForm form = getGradeForm();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            Map<String, String> formData = new HashMap<>();
            request.getParameterMap().forEach((key, value) -> formData.put(key, value[0]));
            form.setData(formData);
            if (form.isValid()) {
                Map<String, Object> data = form.getData();
                assignmentSubmissionTable.update(data, id);

                if (!formData.get("notify").isEmpty()) {
                    notifyStudent(row.getStudentId(), "Homework Graded", "Your homework has been graded.");
                }

                request.getSession().setAttribute("flash_message", "Assignment updated");
                return "redirect:/admin/assignment/submissions?id=" + row.getAssignmentId();
            } else {
                request.getSession().setAttribute("flash_message", getFormErrors(form));
            }
        } else {
            form.setData(getObjectProperties(row));
            if (row.getGrade() == null) {
                form.get("editable").setValue(0);
            }
        }

        model.addAttribute("row", row);
        model.addAttribute("pageTitle", "Homework Submission: " + row.getTitle());
        model.addAttribute("form", form);

        return "admin/assignment/viewsubmission";
    }

    @GetMapping("/downloadFile")
    public void downloadFile(HttpServletRequest request, HttpServletResponse response, @RequestParam("id") int id) throws IOException {
        AssignmentSubmissionTable row = assignmentSubmissionTable.getSubmission(id);
        String path = row.getFilePath();
        File file = new File(path);

        if (!file.exists()) {
            return;
        }

        response.setContentType(getFileMimeType(path));
        response.setHeader("Content-Disposition", "attachment; filename=\"" + file.getName() + "\"");
        try (FileWriter writer = new FileWriter(file)) {
            writer.write(file.getName());
        }
    }

    @GetMapping("/exportresult")
    public void exportresult(HttpServletRequest request, HttpServletResponse response, @RequestParam("id") int id, @RequestParam("type") String type) throws IOException {
        Assignment row = assignmentTable.getRecord(id);
        File file = new File("export.txt");
        if (file.exists()) {
            file.delete();
        }

        try (FileWriter writer = new FileWriter(file)) {
            writer.write("First Name,Last Name,Email,Score %\n");
            int totalRecords = type.equals("pass") ? assignmentSubmissionTable.getTotalPassedForAssignment(id, row.getPassmark()) : assignmentSubmissionTable.getTotalFailedForAssignment(id, row.getPassmark());
            int rowsPerPage = 3000;
            int totalPages = (int) Math.ceil((double) totalRecords / rowsPerPage);

            for (int i = 1; i <= totalPages; i++) {
                List<AssignmentSubmissionTable> paginator = type.equals("pass") ? assignmentSubmissionTable.getPassedPaginatedRecords(true, id, row.getPassmark()) : assignmentSubmissionTable.getFailPaginatedRecords(true, id, row.getPassmark());
                paginator.setCurrentPageNumber(i);
                paginator.setItemCountPerPage(rowsPerPage);

                for (AssignmentSubmissionTable submission : paginator) {
                    writer.write(submission.getFirstName() + "," + submission.getLastName() + "," + submission.getEmail() + "," + submission.getGrade() + "\n");
                }
            }
        }

        response.setContentType("text/csv");
        response.setHeader("Content-Disposition", "attachment; filename=\"" + type + "_student_test_export_" + new SimpleDateFormat("d/M/Y").format(new Date()) + ".csv\"");
        try (FileWriter writer = new FileWriter(file)) {
            writer.write(file.getName());
        }
        file.delete();
    }

    @GetMapping("/sessionlessons")
    public String sessionlessons(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        String selected = request.getParameter("lesson_id");
        List<Map<String, Object>> options = new ArrayList<>();

        List<SessionLessonTable> rowset = sessionLessonTable.getSessionRecords(id);
        for (SessionLessonTable row : rowset) {
            Map<String, Object> option = new HashMap<>();
            option.put("value", row.getLessonId());
            option.put("label", row.getName());
            options.add(option);
        }

        model.addAttribute("select", options);
        model.addAttribute("selected", selected);

        return "admin/assignment/sessionlessons";
    }

    private BaseForm getGradeForm() {
        BaseForm form = new BaseForm();
        form.createTextArea("admin_comment", "Comment (optional)", false);
        form.createText("grade", "Grade (%)", true, "form-control digit", null, "Digits only");
        form.createSelect("editable", "Status", new String[]{"0", "1"}, new String[]{"Graded (Un-editable)", "Ungraded (Editable)"}, true, false);
        form.setInputFilter(getGradeFilter());
        return form;
    }

    private InputFilter getGradeFilter() {
        InputFilter filter = new InputFilter();
        filter.add("admin_comment", false);
        filter.add("grade", true, new String[]{"NotEmpty", "Float"});
        filter.add("editable", true);
        return filter;
    }

    private BaseForm getAssignmentForm() {
        BaseForm form = new BaseForm();
        List<SessionTable> rowset = sessionTable.getLimitedRecords(5000);
        List<Map<String, Object>> options = new ArrayList<>();
        Map<Integer, Boolean> log = new HashMap<>();

        for (SessionTable row : rowset) {
            Map<String, Object> option = new HashMap<>();
            option.put("value", row.getId());
            option.put("label", row.getName() + " (" + row.getId() + ")");
            options.add(option);
            log.put(row.getId(), true);
        }

        List<SessionInstructorTable> instructorRowset = sessionInstructorTable.getAccountRecords(getAdminId());
        for (SessionInstructorTable row : instructorRowset) {
            if (log.containsKey(row.getCourseId())) {
                continue;
            }
            Map<String, Object> option = new HashMap<>();
            option.put("value", row.getCourseId());
            option.put("label", row.getName() + " (" + row.getCourseId() + ")");
            options.add(option);
        }

        form.createSelect("course_id", "Session/Course", options, true);
        form.get("course_id").setAttribute("class", "form-control select2");

        form.createText("due_date", "Due Date", true, "form-control date");
        form.createText("opening_date", "Opening Date", true, "form-control date");
        form.createSelect("schedule_type", "Type", new String[]{"s", "c"}, new String[]{"Scheduled", "Post Class"}, true, false);
        form.createText("title", "Title", true);
        form.createSelect("type", "Student Response Type", new String[]{"t", "f", "b"}, new String[]{"Text", "File Upload", "Text & File Upload"}, true);
        form.createTextArea("instruction", "Homework Instructions", true);
        form.get("instruction").setAttribute("id", "instruction");
        form.createHidden("lesson_id");
        form.createText("passmark", "Passmark (%)", true, "number form-control");
        form.createCheckbox("notify", "Receive submission notifications?", 1);
        form.createCheckbox("allow_late", "Allow late submissions?", 1);
        return form;
    }

    private InputFilter getAssignmentFilter() {
        InputFilter filter = new InputFilter();
        filter.add("course_id", true, new String[]{"NotEmpty"});
        filter.add("due_date", false);
        filter.add("type", true, new String[]{"NotEmpty"});
        filter.add("instruction", true, new String[]{"NotEmpty"});
        filter.add("passmark", true, new String[]{"NotEmpty"});
        filter.add("notify", false);
        filter.add("allow_late", false);
        filter.add("opening_date", false);
        filter.add("lesson_id", false);
        filter.add("schedule_type", true);
        filter.add("title", true, new String[]{"NotEmpty"});
        return filter;
    }

    private String getDateString(String date) {
        // Implement date conversion logic here
        return date;
    }

    private String showDate(String format, String date) {
        // Implement date formatting logic here
        return date;
    }

    private Map<String, Object> getObjectProperties(Assignment row) {
        // Implement logic to get object properties here
        return new HashMap<>();
    }

    private String getFormErrors(BaseForm form) {
        // Implement logic to get form errors here
        return "";
    }

    private void notifySessionStudents(String courseId, String subject, String message, boolean isEmail, String sms) {
        // Implement notification logic here
    }

    private void notifyStudent(int studentId, String subject, String message) {
        // Implement notification logic here
    }

    private int getAdminId() {
        // Implement logic to get admin ID here
        return 0;
    }

    private String getFileMimeType(String path) {
        // Implement logic to get file MIME type here
        return "application/octet-stream";
    }
}

