package App.Http.Controllers.Admin;

import App.AssignmentSubmission;
import App.Course;
import App.Http.Controllers.Controller;
import App.Lesson;
import App.Lib.HelperTrait;
import App.Student;
import App.StudentTest;
import App.Test;
import App.V2.Model.AttendanceTable;
import App.V2.Model.SessionCategoryTable;
import App.V2.Model.SessionLessonTable;
import App.V2.Model.SessionTable;
import App.V2.Model.StudentSessionTable;
import App.V2.Model.TestGradeTable;
import com.dompdf.Dompdf;
import com.dompdf.Options;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.ModelAndView;

import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/admin/report")
public class ReportController extends Controller {
    private final HelperTrait helperTrait = new HelperTrait();

    @RequestMapping(value = "", method = RequestMethod.GET)
    public ModelAndView index(@RequestParam Map<String, String> requestParams) {
        SessionTable table = new SessionTable();
        AttendanceTable attendanceTable = new AttendanceTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();

        String filter = requestParams.get("filter");
        if (filter == null || filter.isEmpty()) {
            filter = null;
        }

        String group = requestParams.get("group");
        if (group == null || group.isEmpty()) {
            group = null;
        }

        String sort = requestParams.get("sort");
        if (sort == null || sort.isEmpty()) {
            sort = null;
        }

        String type = requestParams.get("type");
        if (type == null || type.isEmpty()) {
            type = null;
        }

        Text text = new Text("filter");
        text.setAttribute("class", "form-control");
        text.setAttribute("placeholder", __lang("Search"));
        text.setValue(filter);

        Select select = new Select("group");
        select.setAttribute("class", "form-control select2");
        select.setEmptyOption("--" + __lang("Select Category") + "--");

        Select sortSelect = new Select("sort");
        sortSelect.setAttribute("class", "form-control");
        sortSelect.setValueOptions(new HashMap<String, String>() {{
            put("recent", __lang("Recently Added"));
            put("asc", __lang("Alphabetical (Ascending)"));
            put("desc", __lang("Alphabetical (Descending)"));
            put("date", __lang("Start Date"));
            put("priceAsc", __lang("Price (Lowest to Highest)"));
            put("priceDesc", __lang("Price (Highest to Lowest)"));
        }});
        sortSelect.setEmptyOption("--" + __lang("Sort") + "--");
        sortSelect.setValue(sort);

        Select typeSelect = new Select("type");
        typeSelect.setAttribute("class", "form-control");
        typeSelect.setValueOptions(new HashMap<String, String>() {{
            put("s", __lang("Training Session"));
            put("c", __lang("Online Course"));
            put("b", __lang("training-online"));
        }});
        typeSelect.setEmptyOption("--" + __lang("Type") + "--");
        typeSelect.setValue(type);

        SessionCategoryTable groupTable = new SessionCategoryTable();
        List<GroupRow> groupRowset = groupTable.getLimitedRecords(1000);
        Map<Integer, String> options = new HashMap<>();

        for (GroupRow row : groupRowset) {
            options.put(row.id, row.name);
        }
        select.setValueOptions(options);
        select.setValue(group);

        Paginator paginator = table.getPaginatedRecords(true, null, null, filter, group, sort, type);
        paginator.setCurrentPageNumber(Integer.parseInt(requestParams.getOrDefault("page", "1")));
        paginator.setItemCountPerPage(30);

        Map<String, Object> model = new HashMap<>();
        model.put("paginator", paginator);
        model.put("pageTitle", __lang("Reports"));
        model.put("attendanceTable", attendanceTable);
        model.put("studentSessionTable", studentSessionTable);
        model.put("filter", filter);
        model.put("group", group);
        model.put("text", text);
        model.put("select", select);
        model.put("sortSelect", sortSelect);
        model.put("sort", sort);
        model.put("typeSelect", typeSelect);
        model.put("type", type);

        return new ModelAndView("admin", model);
    }

    @RequestMapping(value = "/classes/{id}", method = RequestMethod.GET)
    public ModelAndView classes(@PathVariable int id) {
        Course session = Course.findOrFail(id);

        Map<String, Object> data = new HashMap<>();
        data.put("pageTitle", __lang("Class Report") + ": " + session.name);
        data.put("session", session);
        data.put("attendanceTable", new AttendanceTable());
        data.put("sessionLessonTable", new SessionLessonTable());
        data.put("id", id);
        return new ModelAndView("admin.report.classes", data);
    }

    @RequestMapping(value = "/students/{id}", method = RequestMethod.GET)
    public ModelAndView students(@PathVariable int id) {
        AttendanceTable attendanceTable = new AttendanceTable();
        Map<String, Object> data = new HashMap<>();
        data.put("rowset", attendanceTable.getStudentSessionReportRecords(id));
        data.put("id", id);
        Course session = Course.findOrFail(id);
        data.put("pageTitle", __lang("Student Report") + ": " + session.name);
        data.put("session", session);
        data.put("attendanceTable", attendanceTable);

        int totalLessons = session.lessons().count();
        if (totalLessons == 0) {
            totalLessons = 1;
        }
        data.put("totalSessionLessons", totalLessons);

        data.put("allTests", getSessionTests(id));
        data.put("controller", this);
        data.put("testGradeTable", new TestGradeTable());

        return new ModelAndView("admin.report.students", data);
    }

    @RequestMapping(value = "/tests/{id}", method = RequestMethod.GET)
    public ModelAndView tests(@PathVariable int id) {
        Map<String, Object> data = new HashMap<>();
        data.put("tests", getSessionTestsObjects(id));
        data.put("allTests", getSessionTests(id));
        data.put("controller", this);
        data.put("testGradeTable", new TestGradeTable());
        data.put("pageTitle", __lang("Test Report") + ": " + Course.find(id).name);

        AttendanceTable attendanceTable = new AttendanceTable();
        data.put("rowset", attendanceTable.getStudentSessionReportRecords(id));
        data.put("session", Course.find(id));

        return new ModelAndView("admin.report.tests", data);
    }

    @RequestMapping(value = "/homework/{id}", method = RequestMethod.GET)
    public ModelAndView homework(@PathVariable int id) {
        Course session = Course.findOrFail(id);
        Map<String, Object> data = new HashMap<>();
        data.put("session", session);
        data.put("pageTitle", __lang("Homework Report") + ": " + session.name);
        data.put("controller", this);
        AttendanceTable attendanceTable = new AttendanceTable();
        data.put("rowset", attendanceTable.getStudentSessionReportRecords(id));
        data.put("testGradeTable", new TestGradeTable());

        return new ModelAndView("admin.report.homework", data);
    }

    @RequestMapping(value = "/reportcard/{id}", method = RequestMethod.GET)
    public ResponseEntity<?> reportcard(@RequestParam int sessionId, @PathVariable int id) {
        Map<String, Object> data = new HashMap<>();
        data.put("tests", getSessionTestsObjects(sessionId));
        data.put("allTests", getSessionTests(sessionId));
        data.put("controller", this);
        data.put("testGradeTable", new TestGradeTable());
        Student student = Student.find(id);
        data.put("student", student);
        data.put("session", Course.find(sessionId));
        data.put("baseUrl", getBaseUrl());
        String html = view("admin.report.reportcard", data).toHtml();
        String fileName = safeUrl(student.first_name + " " + student.last_name + " report " + data.get("session").name) + ".pdf";
        String orientation = "portrait";

        if (useDomPdf()) {
            Options options = new Options();
            options.set("isRemoteEnabled", true);
            Dompdf dompdf = new Dompdf(options);

            dompdf.loadHtml(html);
            dompdf.setPaper("A4", orientation);
            dompdf.render();
            dompdf.stream(fileName);
            return ResponseEntity.ok().build();
        } else {
            PdfWrapper pdf = App.make("snappy.pdf.wrapper");
            pdf.loadHTML(html).setPaper("a4").setOrientation(orientation).setOption("disable-smart-shrinking", true);
            return pdf.download(fileName);
        }
    }

    public Map<String, Object> getStudentTestsStats(int studentId) {
        int totalTaken = 0;
        int scores = 0;

        for (Integer testId : this.data.get("allTests")) {
            StudentTest studentTest = StudentTest.where("student_id", studentId).where("test_id", testId).orderBy("score", "desc").first();
            if (studentTest != null) {
                totalTaken++;
                scores += studentTest.score;
            }
        }

        if (totalTaken != 0) {
            return new HashMap<String, Object>() {{
                put("testsTaken", totalTaken);
                put("average", (double) scores / totalTaken);
            }};
        } else {
            return new HashMap<String, Object>() {{
                put("testsTaken", totalTaken);
                put("average", 0);
            }};
        }
    }

    public Map<String, Object> getStudentAssignmentStats(int studentId) {
        Course session = this.data.get("session");
        int totalTaken = 0;
        int scores = 0;

        for (Assignment assignment : session.assignments) {
            AssignmentSubmission submission = AssignmentSubmission.where("student_id", studentId).where("assignment_id", assignment.assignment_id).orderBy("grade", "desc").first();
            if (submission != null) {
                totalTaken++;
                scores += submission.grade;
            }
        }

        if (totalTaken != 0) {
            return new HashMap<String, Object>() {{
                put("submissions", totalTaken);
                put("average", (double) scores / totalTaken);
            }};
        } else {
            return new HashMap<String, Object>() {{
                put("submissions", totalTaken);
                put("average", 0);
            }};
        }
    }

    public int getStudentTotalPosts(int studentId) {
        Student student = Student.find(studentId);
        if (student == null) {
            return 0;
        }
        int total = 0;

        for (ForumTopic topic : this.data.get("session").forumTopics) {
            total += topic.forumPosts().where("user_id", student.user_id).get().size();
        }
        return total;
    }

    private List<Integer> getSessionTests(int sessionId) {
        Course session = Course.find(sessionId);
        List<Integer> allTests = new ArrayList<>();
        for (Test test : session.tests) {
            allTests.add(test.id);
        }

        for (Lesson lesson : session.lessons) {
            if (lesson != null && lesson.test_id != null && lesson.test_required && Test.find(lesson.test_id) != null) {
                allTests.add(lesson.test_id);
            }
        }
        return allTests;
    }

    private List<Test> getSessionTestsObjects(int sessionId) {
        List<Integer> testIds = getSessionTests(sessionId);
        List<Test> objects = new ArrayList<>();
        for (Integer id : testIds) {
            Test test = Test.find(id);
            if (test != null) {
                objects.add(test);
            }
        }
        return objects;
    }
}

