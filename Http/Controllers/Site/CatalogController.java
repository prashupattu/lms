package App.Http.Controllers.Site;

import App.Course;
import App.CourseCategory;
import App.Http.Controllers.Controller;
import App.Lib.HelperTrait;
import App.V2.Form.DiscussionForm;
import App.V2.Model.DownloadFileTable;
import App.V2.Model.DownloadSessionTable;
import App.V2.Model.LectureTable;
import App.V2.Model.SessionCategoryTable;
import App.V2.Model.SessionInstructorTable;
import App.V2.Model.SessionLessonAccountTable;
import App.V2.Model.SessionLessonTable;
import App.V2.Model.SessionTable;
import App.V2.Model.SessionTestTable;
import App.V2.Model.StudentLectureTable;
import App.V2.Model.StudentSessionLogTable;
import App.V2.Model.StudentSessionTable;
import App.V2.Model.StudentTestTable;
import App.V2.Model.TestQuestionTable;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.servlet.ModelAndView;

import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/site/catalog")
public class CatalogController extends Controller {
    private final HelperTrait helperTrait;

    public CatalogController(HelperTrait helperTrait) {
        this.helperTrait = helperTrait;
    }

    /**
     * For browsing courses
     */
    @RequestMapping(value = "/courses", method = RequestMethod.GET)
    public ResponseEntity<ModelAndView> courses(@RequestParam Map<String, String> requestParams) {
        SessionTable table = new SessionTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        SessionCategoryTable sessionCategoryTable = new SessionCategoryTable();

        String filter = requestParams.getOrDefault("filter", null);
        String group = requestParams.getOrDefault("group", null);
        String sort = requestParams.getOrDefault("sort", null);

        Text text = new Text("filter");
        text.setAttribute("class", "form-control");
        text.setAttribute("placeholder", __lang("Search"));
        text.setValue(filter);

        Select sortSelect = new Select("sort");
        sortSelect.setAttribute("class", "form-control");

        Map<String, String> valueOptions = new HashMap<>();
        valueOptions.put("recent", __lang("Recently Added"));
        valueOptions.put("asc", __lang("Alphabetical (Ascending)"));
        valueOptions.put("desc", __lang("Alphabetical (Descending)"));
        valueOptions.put("date", __lang("Start Date"));

        if (this.helperTrait.getSetting("general_show_fee") == 1) {
            valueOptions.put("priceAsc", __lang("Price (Lowest to Highest)"));
            valueOptions.put("priceDesc", __lang("Price (Highest to Lowest)"));
        }

        sortSelect.setValueOptions(valueOptions);
        sortSelect.setEmptyOption("--" + __lang("Sort") + "--");
        sortSelect.setValue(sort);

        Paginator paginator = table.getPaginatedCourseRecords(true, null, true, filter, group, sort, 'c');
        paginator.setCurrentPageNumber(Integer.parseInt(requestParams.getOrDefault("page", "1")));
        paginator.setItemCountPerPage(30);

        List<CourseCategory> categories = CourseCategory.whereNull("parent_id").orderBy("sort_order").where("enabled", 1).limit(100).get();

        String pageTitle = __lang("Online Courses");
        CourseCategory parent = null;
        String description;
        List<CourseCategory> subCategories;

        if (group != null) {
            CourseCategory categoryRow = sessionCategoryTable.getRecord(group);
            pageTitle += ": " + categoryRow.getName();
            description = categoryRow.getDescription();
            subCategories = CourseCategory.where("parent_id", group).orderBy("sort_order").where("enabled", 1).get();
            if (subCategories.isEmpty()) {
                subCategories = null;
            }
            if (categoryRow.getParentId() != null) {
                parent = sessionCategoryTable.getRecord(categoryRow.getParentId());
            }
        } else {
            description = "";
            subCategories = null;
        }

        Map<String, Object> output = new HashMap<>();
        output.put("paginator", paginator);
        output.put("pageTitle", pageTitle);
        output.put("studentSessionTable", studentSessionTable);
        output.put("filter", filter);
        output.put("group", group);
        output.put("text", text);
        output.put("sortSelect", sortSelect);
        output.put("sort", sort);
        output.put("categories", categories);
        output.put("description", description);
        output.put("subCategories", subCategories);
        output.put("parent", parent);

        if (isStudent()) {
            return ResponseEntity.ok(view("site.catalog.courses", output));
        }

        if (frontendEnabled()) {
            return ResponseEntity.ok(tview("site.catalog.courses", output));
        } else {
            return ResponseEntity.status(302).location("/home").build();
        }
    }

    @RequestMapping(value = "/sessions", method = RequestMethod.GET)
    public ResponseEntity<ModelAndView> sessions(@RequestParam Map<String, String> requestParams) {
        SessionTable table = new SessionTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        String filter = requestParams.getOrDefault("filter", null);
        String group = requestParams.getOrDefault("group", null);
        String sort = requestParams.getOrDefault("sort", null);

        Text text = new Text("filter");
        text.setAttribute("class", "form-control");
        text.setAttribute("placeholder", "Search");
        text.setValue(filter);

        Select sortSelect = new Select("sort");
        sortSelect.setAttribute("class", "form-control");

        Map<String, String> valueOptions = new HashMap<>();
        valueOptions.put("recent", __lang("Recently Added"));
        valueOptions.put("asc", __lang("Alphabetical (Ascending)"));
        valueOptions.put("desc", __lang("Alphabetical (Descending)"));
        valueOptions.put("date", __lang("Start Date"));

        if (this.helperTrait.getSetting("general_show_fee") == 1) {
            valueOptions.put("priceAsc", __lang("Price (Lowest to Highest)"));
            valueOptions.put("priceDesc", __lang("Price (Highest to Lowest)"));
        }

        sortSelect.setValueOptions(valueOptions);
        sortSelect.setEmptyOption("--" + __lang("Sort") + "--");
        sortSelect.setValue(sort);

        Paginator paginator = table.getPaginatedRecords(true, null, true, filter, group, sort, new char[]{'s', 'b'}, true);
        paginator.setCurrentPageNumber(Integer.parseInt(requestParams.getOrDefault("page", "1")));
        paginator.setItemCountPerPage(30);

        Map<String, Object> output = new HashMap<>();
        output.put("paginator", paginator);
        output.put("pageTitle", __lang("Upcoming Sessions"));
        output.put("studentSessionTable", studentSessionTable);
        output.put("filter", filter);
        output.put("group", group);
        output.put("text", text);
        output.put("sortSelect", sortSelect);
        output.put("sort", sort);

        if (isStudent()) {
            return ResponseEntity.ok(view("site.catalog.sessions", output));
        }

        if (frontendEnabled()) {
            return ResponseEntity.ok(tview("site.catalog.sessions", output));
        } else {
            return ResponseEntity.status(302).location("/home").build();
        }
    }

    @RequestMapping(value = "/course/{course}", method = RequestMethod.GET)
    public ResponseEntity<ModelAndView> course(@RequestParam Map<String, String> requestParams, Course course) {
        SessionTable sessionTable = new SessionTable();
        SessionLessonTable sessionLessonTable = new SessionLessonTable();
        SessionLessonAccountTable sessionLessonAccountTable = new SessionLessonAccountTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();
        SessionInstructorTable sessionInstructorTable = new SessionInstructorTable();
        StudentLectureTable studentLectureTable = new StudentLectureTable();
        StudentSessionLogTable logTable = new StudentSessionLogTable();
        boolean enrolled = false;
        String resumeLink = null;

        int id = course.getId();
        DownloadSessionTable downloadSessionTable = new DownloadSessionTable();

        Session row = sessionTable.getRecord(id);
        List<SessionLesson> rowset = sessionLessonTable.getSessionRecords(id);

        List<Instructor> instructors = sessionInstructorTable.getSessionRecords(id);
        List<Download> downloads = downloadSessionTable.getSessionRecords(id);
        SessionTestTable sessionTestTable = new SessionTestTable();
        List<Test> tests = sessionTestTable.getSessionRecords(id);

        Map<String, Object> output = new HashMap<>();
        output.put("rowset", rowset);
        output.put("row", row);
        output.put("pageTitle", __lang("Course Details"));
        output.put("table", sessionLessonAccountTable);
        output.put("id", id);
        output.put("studentSessionTable", studentSessionTable);
        output.put("instructors", instructors);
        output.put("downloads", downloads);
        output.put("fileTable", new DownloadFileTable());
        output.put("enrolled", enrolled);
        output.put("tests", tests);
        output.put("questionTable", new TestQuestionTable());
        output.put("studentTest", new StudentTestTable());
        output.put("totalClasses", sessionLessonTable.getSessionRecords(id).size());
        output.put("course", course);

        ModelAndView view;
        if (course.getType().equals("c")) {
            view = tview("site.catalog.course", output);
        } else {
            view = tview("site.catalog.session", output);
        }

        if (isStudent()) {
            if (course.getType().equals("c")) {
                view = view("site.catalog.course", output);
            } else {
                view = view("site.catalog.session", output);
            }
            return ResponseEntity.ok(view);
        }

        if (frontendEnabled()) {
            return ResponseEntity.ok(view);
        } else {
            return ResponseEntity.status(302).location("/home").build();
        }
    }
}

