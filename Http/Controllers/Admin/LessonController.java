import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;

import javax.servlet.http.HttpServletRequest;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import com.example.app.models.Lesson;
import com.example.app.models.LessonGroup;
import com.example.app.models.LessonFile;
import com.example.app.models.Lecture;
import com.example.app.models.SessionLesson;
import com.example.app.repositories.LessonRepository;
import com.example.app.repositories.LessonGroupRepository;
import com.example.app.repositories.LessonFileRepository;
import com.example.app.repositories.LectureRepository;
import com.example.app.repositories.SessionLessonRepository;
import com.example.app.services.HelperService;
import com.example.app.services.LessonService;
import com.example.app.services.LessonFilterService;
import com.example.app.services.LessonFormService;
import com.example.app.services.LessonGroupService;
import com.example.app.services.LessonFileService;
import com.example.app.services.LectureService;
import com.example.app.services.SessionLessonService;

@Controller
@RequestMapping("/admin/lesson")
public class LessonController {

    @Autowired
    private LessonService lessonService;

    @Autowired
    private LessonGroupService lessonGroupService;

    @Autowired
    private LessonFileService lessonFileService;

    @Autowired
    private LectureService lectureService;

    @Autowired
    private SessionLessonService sessionLessonService;

    @Autowired
    private HelperService helperService;

    @Autowired
    private LessonFormService lessonFormService;

    @Autowired
    private LessonFilterService lessonFilterService;

    @GetMapping("/index")
    public String index(HttpServletRequest request, Model model) {
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

        List<Lesson> paginator = lessonService.getLessons(true, filter, group, sort);

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Classes");
        model.addAttribute("filter", filter);
        model.addAttribute("group", group);
        model.addAttribute("sort", sort);

        return "admin/lesson/index";
    }

    @PostMapping("/add")
    public String add(HttpServletRequest request, Model model) {
        Map<String, Object> output = new HashMap<>();
        Lesson lesson = new Lesson();
        LessonFormService form = lessonFormService.createForm(lesson);
        LessonFilterService filter = lessonFilterService.createFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);

            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                lessonService.saveRecord(array);
                output.put("flash_message", "Record Added!");
                form = lessonFormService.createForm(lesson);
                request.getSession().setAttribute("flash_message", "Class Added");

                String sessionId = request.getParameter("sessionId");
                if (sessionId != null) {
                    SessionLesson sessionLesson = new SessionLesson();
                    sessionLesson.setSessionId(Long.parseLong(sessionId));
                    sessionLesson.setLessonId(lesson.getId());
                    sessionLesson.setSortOrder(sessionLessonService.getLastSortOrder(Long.parseLong(sessionId)) + 1);
                    sessionLessonService.save(sessionLesson);
                    return "redirect:/admin/lesson/index";
                }

                if ("c".equals(array.get("type"))) {
                    return "redirect:/admin/lecture/index?id=" + lesson.getId();
                } else {
                    return "redirect:/admin/lesson/index";
                }
            } else {
                output.put("flash_message", "Form validation failed");
            }
        }

        output.put("form", form);
        output.put("pageTitle", "Add Class");
        output.put("action", "add");
        output.put("id", null);
        model.addAllAttributes(output);

        return "admin/lesson/add";
    }

    @PostMapping("/edit")
    public String edit(HttpServletRequest request, @RequestParam("id") Long id, Model model) {
        Map<String, Object> output = new HashMap<>();
        Lesson lesson = lessonService.findById(id).orElseThrow(() -> new RuntimeException("Lesson not found"));
        LessonFormService form = lessonFormService.createForm(lesson);
        LessonFilterService filter = lessonFilterService.createFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);

            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                lessonService.saveRecord(array);
                output.put("flash_message", "Changes Saved!");
                request.getSession().setAttribute("flash_message", "Changes Saved!");
                return "redirect:/admin/lesson/index";
            } else {
                output.put("flash_message", "Form validation failed");
            }
        } else {
            Map<String, Object> data = helperService.getObjectProperties(lesson);
            form.setData(data);
        }

        output.put("form", form);
        output.put("id", id);
        output.put("pageTitle", "Edit Class");
        output.put("row", lesson);
        output.put("action", "edit");
        model.addAllAttributes(output);

        return "admin/lesson/edit";
    }

    @GetMapping("/delete")
    public String delete(@RequestParam("id") Long id) {
        lessonService.deleteRecord(id);
        return "redirect:/admin/lesson/index";
    }

    @GetMapping("/groups")
    public String groups(HttpServletRequest request, Model model) {
        List<LessonGroup> paginator = lessonGroupService.getPaginatedRecords(true);
        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Class Groups");
        return "admin/lesson/groups";
    }

    @PostMapping("/addgroup")
    public String addGroup(HttpServletRequest request, Model model) {
        Map<String, Object> output = new HashMap<>();
        LessonGroup lessonGroup = new LessonGroup();
        LessonFormService form = lessonFormService.createForm(lessonGroup);
        LessonFilterService filter = lessonFilterService.createFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);

            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                lessonGroupService.saveRecord(array);
                output.put("flash_message", "Record Added!");
                request.getSession().setAttribute("flash_message", "Group Created");
                return "redirect:/admin/lesson/groups";
            } else {
                output.put("flash_message", "Form validation failed");
            }
        }

        output.put("form", form);
        output.put("pageTitle", "Add Class Group");
        output.put("action", "addgroup");
        output.put("id", null);
        model.addAllAttributes(output);

        return "admin/lesson/addgroup";
    }

    @PostMapping("/editgroup")
    public String editGroup(HttpServletRequest request, @RequestParam("id") Long id, Model model) {
        Map<String, Object> output = new HashMap<>();
        LessonGroup lessonGroup = lessonGroupService.findById(id).orElseThrow(() -> new RuntimeException("Lesson Group not found"));
        LessonFormService form = lessonFormService.createForm(lessonGroup);
        LessonFilterService filter = lessonFilterService.createFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);

            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                lessonGroupService.saveRecord(array);
                request.getSession().setAttribute("flash_message", "Changes Saved!");
                return "redirect:/admin/lesson/groups";
            } else {
                output.put("flash_message", "Form validation failed");
            }
        } else {
            Map<String, Object> data = helperService.getObjectProperties(lessonGroup);
            form.setData(data);
        }

        output.put("form", form);
        output.put("id", id);
        output.put("pageTitle", "Edit Class Group");
        output.put("row", lessonGroup);
        output.put("action", "editgroup");
        model.addAllAttributes(output);

        return "admin/lesson/editgroup";
    }

    @GetMapping("/deletegroup")
    public String deleteGroup(@RequestParam("id") Long id) {
        lessonGroupService.deleteRecord(id);
        return "redirect:/admin/lesson/groups";
    }

    @GetMapping("/files")
    public String files(@RequestParam("id") Long id, Model model) {
        List<LessonFile> rowset = lessonFileService.getDownloadRecords(id);
        Lesson lesson = lessonService.findById(id).orElseThrow(() -> new RuntimeException("Lesson not found"));
        model.addAttribute("rowset", rowset);
        model.addAttribute("pageTitle", "Class Downloads: " + lesson.getName());
        model.addAttribute("id", id);
        return "admin/lesson/files";
    }

    @PostMapping("/addfile")
    public String addFile(HttpServletRequest request, @RequestParam("id") Long id) {
        String path = request.getParameter("path");
        path = path.replace("usermedia/", "");
        if (!lessonFileService.fileExists(id, path)) {
            lessonFileService.addRecord(id, path);
        }
        return "redirect:/admin/lesson/files?id=" + id;
    }

    @GetMapping("/removefile")
    public String removeFile(@RequestParam("id") Long id) {
        LessonFile lessonFile = lessonFileService.findById(id).orElseThrow(() -> new RuntimeException("Lesson File not found"));
        lessonFileService.deleteRecord(id);
        return "redirect:/admin/lesson/files?id=" + lessonFile.getLessonId();
    }

    @GetMapping("/download")
    public @ResponseBody void download(@RequestParam("id") Long id, HttpServletRequest request, HttpServletResponse response) {
        LessonFile lessonFile = lessonFileService.findById(id).orElseThrow(() -> new RuntimeException("Lesson File not found"));
        String path = "usermedia/" + lessonFile.getPath();
        response.setContentType(helperService.getFileMimeType(path));
        response.setHeader("Content-Disposition", "attachment; filename=\"" + lessonFile.getPath() + "\"");
        helperService.readFile(path, response.getOutputStream());
    }

    @GetMapping("/duplicate")
    public String duplicate(@RequestParam("id") Long id) {
        Lesson oldLesson = lessonService.findById(id).orElseThrow(() -> new RuntimeException("Lesson not found"));
        Lesson lesson = oldLesson.replicate();
        lessonService.save(lesson);

        for (Lecture oldLecture : oldLesson.getLectures()) {
            Lecture lecture = lectureService.create(oldLecture.toMap());
            for (LecturePage oldLecturePage : oldLecture.getLecturePages()) {
                lectureService.createLecturePage(lecture, oldLecturePage.toMap());
            }
            for (LectureFile oldLectureFile : oldLecture.getLectureFiles()) {
                lectureService.createLectureFile(lecture, oldLectureFile.toMap());
            }
        }

        for (LessonGroup group : oldLesson.getLessonGroups()) {
            lesson.getLessonGroups().add(group);
        }

        request.getSession().setAttribute("flash_message", "Record Duplicated");
        return "redirect:/admin/lesson/index";
    }
}

