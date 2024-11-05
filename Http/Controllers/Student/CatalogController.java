package Demo.App.Http.Controllers.Student;

import java.util.HashMap;
import java.util.Map;
import javax.servlet.http.HttpServletRequest;
import org.springframework.web.bind.annotation.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;

@Controller
@RequestMapping("/student")
public class CatalogController {

    @Autowired
    private SessionTable sessionTable;

    @Autowired
    private StudentSessionTable studentSessionTable;

    @Autowired
    private SessionCategoryTable sessionCategoryTable;

    @Autowired
    private LectureTable lectureTable;

    @Autowired
    private StudentLectureTable studentLectureTable;

    @Autowired
    private StudentSessionLogTable logTable;

    @Autowired
    private DownloadSessionTable downloadSessionTable;

    @Autowired
    private DownloadFileTable downloadFileTable;

    @Autowired
    private SessionTestTable sessionTestTable;

    @Autowired
    private StudentTestTable studentTestTable;

    @GetMapping("/sessions")
    public ModelAndView sessions(HttpServletRequest request) {
        String filter = request.getParameter("filter");
        String group = request.getParameter("group");
        String sort = request.getParameter("sort");

        if (filter == null || filter.isEmpty()) {
            filter = null;
        }

        if (group == null || group.isEmpty()) {
            group = null;
        }

        if (sort == null || sort.isEmpty()) {
            sort = null;
        }

        Text text = new Text("filter");
        text.setAttribute("class", "form-control");
        text.setAttribute("placeholder", "Search");
        text.setValue(filter);

        Select sortSelect = new Select("sort");
        sortSelect.setAttribute("class", "form-control");

        Map<String, String> valueOptions = new HashMap<>();
        valueOptions.put("recent", "Recently Added");
        valueOptions.put("asc", "Alphabetical (Ascending)");
        valueOptions.put("desc", "Alphabetical (Descending)");
        valueOptions.put("date", "Start Date");

        if (getSetting("general_show_fee") == 1) {
            valueOptions.put("priceAsc", "Price (Lowest to Highest)");
            valueOptions.put("priceDesc", "Price (Highest to Lowest)");
        }

        sortSelect.setValueOptions(valueOptions);
        sortSelect.setEmptyOption("--Sort--");
        sortSelect.setValue(sort);

        Paginator paginator = sessionTable.getPaginatedRecords(true, null, true, filter, group, sort, new String[]{"s", "b"}, true);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        String role = getRole();
        int id = getId();

        Map<String, Object> output = new HashMap<>();
        output.put("paginator", paginator);
        output.put("pageTitle", "Upcoming Sessions");
        output.put("studentSessionTable", studentSessionTable);
        output.put("id", id);
        output.put("filter", filter);
        output.put("group", group);
        output.put("text", text);
        output.put("sortSelect", sortSelect);
        output.put("sort", sort);

        return new ModelAndView("student", output);
    }

    @GetMapping("/courses")
    public ModelAndView courses(HttpServletRequest request) {
        String filter = request.getParameter("filter");
        String group = request.getParameter("group");
        String sort = request.getParameter("sort");

        if (filter == null || filter.isEmpty()) {
            filter = null;
        }

        if (group == null || group.isEmpty()) {
            group = null;
        }

        if (sort == null || sort.isEmpty()) {
            sort = null;
        }

        Text text = new Text("filter");
        text.setAttribute("class", "form-control");
        text.setAttribute("placeholder", "Search");
        text.setValue(filter);

        Select sortSelect = new Select("sort");
        sortSelect.setAttribute("class", "form-control");

        Map<String, String> valueOptions = new HashMap<>();
        valueOptions.put("recent", "Recently Added");
        valueOptions.put("asc", "Alphabetical (Ascending)");
        valueOptions.put("desc", "Alphabetical (Descending)");
        valueOptions.put("date", "Start Date");

        if (getSetting("general_show_fee") == 1) {
            valueOptions.put("priceAsc", "Price (Lowest to Highest)");
            valueOptions.put("priceDesc", "Price (Highest to Lowest)");
        }

        sortSelect.setValueOptions(valueOptions);
        sortSelect.setEmptyOption("--Sort--");
        sortSelect.setValue(sort);

        Paginator paginator = sessionTable.getPaginatedCourseRecords(true, null, true, filter, group, sort, "c");
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        String role = getRole();
        int id = getId();

        List<CourseCategory> categories = CourseCategory.where("parent_id", null).orderBy("sort_order").where("enabled", 1).limit(100).get();

        String pageTitle = "Online Courses";
        String description = "";
        CourseCategory parent = null;

        if (group != null && !group.isEmpty()) {
            CategoryRow categoryRow = sessionCategoryTable.getRecord(group);
            pageTitle += ": " + categoryRow.getCategoryName();
            description = categoryRow.getDescription();
            List<CourseCategory> subCategories = CourseCategory.where("parent_id", group).orderBy("sort_order").where("enabled", 1).get();
            if (subCategories.isEmpty()) {
                subCategories = null;
            }

            if (categoryRow.getParentId() != null) {
                parent = sessionCategoryTable.getRecord(categoryRow.getParentId());
            }
        }

        Map<String, Object> output = new HashMap<>();
        output.put("paginator", paginator);
        output.put("pageTitle", pageTitle);
        output.put("studentSessionTable", studentSessionTable);
        output.put("id", id);
        output.put("filter", filter);
        output.put("group", group);
        output.put("text", text);
        output.put("sortSelect", sortSelect);
        output.put("sort", sort);
        output.put("categories", categories);
        output.put("description", description);
        output.put("subCategories", null);
        output.put("parent", parent);

        return new ModelAndView("student", output);
    }

    @GetMapping("/course/{id}")
    public ModelAndView course(HttpServletRequest request, @PathVariable int id) {
        String role = getRole();
        boolean enrolled = false;
        String resumeLink = null;
        boolean studentCourse = false;
        int studentId = Authcheck() && role.getId() == 2 ? getId() : 0;

        if (studentId > 0) {
            if (studentSessionTable.enrolled(studentId, id)) {
                studentCourse = getStudent().getStudentCourses().where("course_id", id).first();
                enrolled = true;

                if (studentLectureTable.hasLecture(studentId, id)) {
                    Lecture lecture = studentLectureTable.getLecture(studentId, id);
                    if (lecture != null && sessionLessonTable.lessonExists(id, lecture.getLessonId())) {
                        int lectureId = lecture.getLectureId();
                        Lecture next = lectureTable.getNextLecture(lecture.getLectureId());

                        if (next != null) {
                            lecture = next;
                            lectureId = lecture.getId();
                        }

                        if (lecture.getSortOrder() == 1) {
                            resumeLink = route("student.course.class", Map.of("lesson", lecture.getLessonId(), "course", id));
                        } else {
                            resumeLink = route("student.course.lecture", Map.of("lecture", lectureId, "course", id));
                        }
                    } else {
                        resumeLink = route("student.course.intro", Map.of("id", id));
                    }
                } else {
                    resumeLink = route("student.course.intro", Map.of("id", id));
                }
            }
        }

        DiscussionForm discussionForm = new DiscussionForm(null, studentId);
        Session row = sessionTable.getRecord(id);
        List<SessionLesson> rowset = sessionLessonTable.getSessionRecords(id);

        if (!"c".equals(row.getType())) {
            return redirect().route("student.session-details", Map.of("id", row.getId(), "slug", safeUrl(row.getName())));
        }

        List<Instructor> instructors = sessionInstructorTable.getSessionRecords(id);
        List<Download> downloads = downloadSessionTable.getSessionRecords(id);
        List<Test> tests = sessionTestTable.getSessionRecords(id);

        Map<String, Object> output = new HashMap<>();
        output.put("rowset", rowset);
        output.put("row", row);
        output.put("pageTitle", "Course Details");
        output.put("table", new SessionLessonAccountTable());
        output.put("id", id);
        output.put("studentId", studentId);
        output.put("studentSessionTable", studentSessionTable);
        output.put("instructors", instructors);
        output.put("form", discussionForm);
        output.put("downloads", downloads);
        output.put("fileTable", downloadFileTable);
        output.put("resumeLink", resumeLink);
        output.put("enrolled", enrolled);
        output.put("tests", tests);
        output.put("questionTable", new TestQuestionTable());
        output.put("studentTest", studentTestTable);
        output.put("totalClasses", sessionLessonTable.getSessionRecords(id).size());
        output.put("studentCourse", studentCourse);

        return new ModelAndView("student", output);
    }

    public AuthService getAuthService() {
        return getServiceLocator().get("StudentAuthService");
    }
}

