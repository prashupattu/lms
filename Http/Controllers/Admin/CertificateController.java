import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

import com.example.app.Certificate;
import com.example.app.Course;
import com.example.app.Lesson;
import com.example.app.StudentField;
import com.example.app.Test;
import com.example.app.v2.form.CertificateFilter;
import com.example.app.v2.form.CertificateForm;
import com.example.app.v2.form.EditCertificateForm;
import com.example.app.v2.model.AssignmentTable;
import com.example.app.v2.model.CertificateAssignmentTable;
import com.example.app.v2.model.CertificateLessonTable;
import com.example.app.v2.model.CertificateTable;
import com.example.app.v2.model.CertificateTestTable;
import com.example.app.v2.model.LessonTable;
import com.example.app.v2.model.SessionLessonTable;
import com.example.app.v2.model.SessionTable;
import com.example.app.v2.model.StudentCertificateTable;
import com.example.app.v2.model.TestTable;

@Controller
@RequestMapping("/admin/certificate")
public class CertificateController {

    @Autowired
    private CertificateTable certificateTable;

    @Autowired
    private AssignmentTable assignmentTable;

    @Autowired
    private SessionLessonTable sessionLessonTable;

    @Autowired
    private CertificateLessonTable certificateLessonTable;

    @Autowired
    private CertificateTestTable certificateTestTable;

    @Autowired
    private CertificateAssignmentTable certificateAssignmentTable;

    @Autowired
    private StudentCertificateTable studentCertificateTable;

    @GetMapping("/index")
    public String index(HttpServletRequest request, Model model) {
        int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
        int itemCountPerPage = 30;

        List<Certificate> paginator = certificateTable.getPaginatedRecords(true);
        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Certificates");
        model.addAttribute("articleTable", certificateTable);

        return "admin/certificate/index";
    }

    @GetMapping("/add")
    public String add(HttpServletRequest request, Model model) {
        Map<String, Object> output = new HashMap<>();
        CertificateForm form = new CertificateForm(null, null);
        CertificateFilter filter = new CertificateFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String> data = new HashMap<>();
            request.getParameterMap().forEach((key, value) -> data.put(key, value[0]));
            form.setData(data);

            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                array.put(certificateTable.getPrimary(), 0);
                int id = certificateTable.saveRecord(array);
                // flashMessage(__lang('record-added!'));
                return "redirect:/admin/certificate/edit?id=" + id;
            } else {
                output.put("flash_message", "Save failed");
                if (data.containsKey("image")) {
                    output.put("display_image", resizeImage(data.get("image"), 100, 100));
                }
                output.put("no_image", resizeImage("img/no_image.jpg", 100, 100));
            }
        } else {
            output.put("no_image", resizeImage("img/no_image.jpg", 100, 100));
            output.put("display_image", resizeImage("img/no_image.jpg", 100, 100));
        }

        output.put("form", form);
        output.put("pageTitle", "Add Certificate");
        output.put("action", "add");
        output.put("id", null);
        model.addAllAttributes(output);

        return "admin/certificate/add";
    }

    @GetMapping("/edit")
    public String edit(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Map<String, Object> output = new HashMap<>();
        Certificate certificate = certificateTable.getRecord(id);
        EditCertificateForm form = new EditCertificateForm(null, null);
        CertificateFilter filter = new CertificateFilter();

        form.setInputFilter(filter);
        if ("POST".equalsIgnoreCase(request.getMethod())) {
            Map<String, String> formData = new HashMap<>();
            request.getParameterMap().forEach((key, value) -> formData.put(key, value[0]));
            form.setData(formData);

            if (form.isValid()) {
                Map<String, Object> data = form.getData();
                String newSrc = getBaseUrl() + "/" + data.get("image");
                Certificate certificateRow = certificateTable.getRecord(id);
                String html = data.get("html") != null ? (String) data.get("html") : certificateRow.getHtml();

                if (html != null && !html.isEmpty()) {
                    // Process HTML with DOMDocument
                    // ...
                }

                if (!certificateRow.getCourseId().equals(data.get("course_id")) || !certificateRow.getOrientation().equals(data.get("orientation"))) {
                    data.put("html", "");
                }

                // Remove lesson records
                data.entrySet().removeIf(entry -> entry.getKey().startsWith("lesson_"));

                certificateTable.update(data, id);

                // Save classes
                certificateLessonTable.clearCertificateRecords(id);
                List<Integer> classes = new ArrayList<>();
                formData.forEach((key, value) -> {
                    if (key.startsWith("lesson_") && !value.isEmpty()) {
                        classes.add(Integer.parseInt(value));
                    }
                });
                certificate.setLessons(classes);

                certificateTestTable.clearCertificateRecords(id);
                formData.forEach((key, value) -> {
                    if (key.startsWith("test_") && !value.isEmpty()) {
                        certificateTestTable.addRecord(Map.of("certificate_id", id, "test_id", Integer.parseInt(value)));
                    }
                });

                certificateAssignmentTable.clearCertificateRecords(id);
                formData.forEach((key, value) -> {
                    if (key.startsWith("assignment_") && !value.isEmpty()) {
                        certificateAssignmentTable.addRecord(Map.of("certificate_id", id, "assignment_id", Integer.parseInt(value)));
                    }
                });

                // flashMessage(__lang('Changes Saved!'));
                return "redirect:/admin/certificate/index";
            } else {
                output.put("flash_message", getFormErrors(form));
            }

            form.populateValues(formData);
        } else {
            Certificate row = certificateTable.getRecord(id);
            Map<String, Object> data = row.toMap();
            List<Map<String, Object>> lessons = certificateLessonTable.getCertificateLessons(id);
            lessons.forEach(lesson -> data.put("lesson_" + lesson.get("lesson_id"), lesson.get("lesson_id")));
            form.setData(data);
        }

        Certificate row = certificateTable.getRecord(id);
        List<Map<String, Object>> rowset = sessionLessonTable.getSessionRecords(row.getCourseId());
        rowset.forEach(row2 -> {
            form.createCheckbox("lesson_" + row2.get("lesson_id"), row2.get("name"), row2.get("lesson_id"));
            if (certificateLessonTable.hasLesson(id, row2.get("lesson_id"))) {
                form.get("lesson_" + row2.get("lesson_id")).setAttribute("checked", "checked");
            }
        });

        List<Map<String, Object>> lessons = sessionLessonTable.getSessionRecords(row.getCourseId());
        List<Map<String, Object>> tests = certificateTestTable.getCertificateRecords(id);
        List<Map<String, Object>> assignments = certificateAssignmentTable.getCertificateRecords(id);

        int width = row.getOrientation().equals("p") ? 595 : 842;
        int height = row.getOrientation().equals("p") ? 842 : 595;

        output.put("allTests", getSessionTestsObjects(row.getCourseId()));
        output.put("allAssignments", assignmentTable.getPaginatedRecords(false, row.getCourseId()));
        output.put("studentFields", StudentField.get());
        output.put("row", row);
        output.put("pageTitle", "Edit Certificate: " + row.getName());
        output.put("certificateLessonTable", certificateLessonTable);
        output.put("lessons", lessons);
        output.put("tests", tests);
        output.put("assignments", assignments);
        output.put("width", width);
        output.put("height", height);
        output.put("siteUrl", getBaseUrl());
        output.put("form", form);
        output.put("action", "edit");
        output.put("id", id);
        output.put("certificate", certificate);

        if (row.getImage() != null && fileExists(DIR_MER_IMAGE + row.getImage()) && isFile(DIR_MER_IMAGE + row.getImage())) {
            output.put("display_image", resizeImage(row.getImage(), 100, 100));
        } else {
            output.put("display_image", resizeImage("img/no_image.jpg", 100, 100));
        }

        output.put("no_image", getBaseUrl() + "/" + resizeImage("img/no_image.jpg", 100, 100));

        model.addAllAttributes(output);

        return "admin/certificate/edit";
    }

    @GetMapping("/fix")
    public String fix(HttpServletRequest request, Model model) {
        List<Certificate> certificates = certificateTable.getAll();
        certificates.forEach(certificateRow -> {
            String newSrc = getBaseUrl() + "/" + certificateRow.getImage();
            String html = certificateRow.getHtml();
            if (html == null || html.isEmpty()) {
                return;
            }
            // Process HTML with DOMDocument
            // ...
            certificateRow.setHtml(html);
            certificateRow.save();
        });

        return "Fixed all";
    }

    @GetMapping("/reset")
    public String reset(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        certificateTable.update(Map.of("html", ""), id);
        // session()->flash('flash_message', __lang('certificate-reset'));
        return "redirect:/admin/certificate/edit?id=" + id;
    }

    @GetMapping("/loadclasses")
    public String loadclasses(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        List<Map<String, Object>> rowset = sessionLessonTable.getSessionRecords(id);
        model.addAttribute("rowset", rowset);
        return "admin/certificate/loadclasses";
    }

    @GetMapping("/delete")
    public String delete(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        certificateTable.deleteRecord(id);
        // session()->flash('flash_message', __lang('Record deleted'));
        return "redirect:/admin/certificate/index";
    }

    @GetMapping("/duplicate")
    public String duplicate(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Certificate certiciateRow = certificateTable.getRecord(id);
        List<Map<String, Object>> certificateLessonRowset = certificateLessonTable.getCertificateLessons(id);
        List<Map<String, Object>> certificateTestRowset = certificateTestTable.getCertificateTests(id);

        Map<String, Object> certificateArray = certiciateRow.toMap();
        certificateArray.remove("id");
        int newId = certificateTable.addRecord(certificateArray);

        certificateLessonRowset.forEach(row -> {
            Map<String, Object> data = row.toMap();
            data.put("certificate_id", newId);
            certificateLessonTable.addRecord(data);
        });

        certificateTestRowset.forEach(row -> {
            Map<String, Object> data = row.toMap();
            data.put("certificate_id", newId);
            certificateTestTable.addRecord(data);
        });

        // session()->flash('flash_message', __lang('certificate-duplicated'));
        return "redirect:/admin/certificate/index";
    }

    @GetMapping("/students")
    public String students(HttpServletRequest request, @RequestParam("id") int id, Model model) {
        Certificate certificate = certificateTable.getRecord(id);
        List<Map<String, Object>> studentCertificates = certificate.getStudentCertificates().orderBy("id").desc().paginate(30);
        int total = certificate.getStudentCertificates().count();

        model.addAttribute("students", studentCertificates);
        model.addAttribute("total", total);
        model.addAttribute("pageTitle", "Student Certificates: " + certificate.getCertificateName() + " (" + total + ")");

        return "admin/certificate/students";
    }

    @GetMapping("/track")
    public String track(HttpServletRequest request, Model model) {
        String filter = request.getParameter("query");
        List<Map<String, Object>> paginator = null;

        if (filter != null && !filter.isEmpty()) {
            paginator = studentCertificateTable.searchStudents(filter);
            int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
            paginator.setCurrentPageNumber(page);
            paginator.setItemCountPerPage(30);
        }

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Track Certificate");

        return "admin/certificate/track";
    }

    private List<Integer> getSessionTests(int sessionId) {
        Course session = Course.find(sessionId);
        List<Integer> allTests = new ArrayList<>();
        session.getTests().forEach(test -> allTests.add(test.getId()));
        session.getLessons().forEach(lesson -> {
            if (lesson != null && lesson.getTestId() != null && lesson.getTestRequired() && Test.find(lesson.getTestId()) != null) {
                allTests.add(lesson.getTestId());
            }
        });
        return allTests;
    }

    private List<Test> getSessionTestsObjects(int sessionId) {
        List<Integer> testIds = getSessionTests(sessionId);
        List<Test> objects = new ArrayList<>();
        testIds.forEach(id -> {
            Test test = Test.find(id);
            if (test != null) {
                objects.add(test);
            }
        });
        return objects;
    }

    private String resizeImage(String imagePath, int width, int height) {
        // Implement image resizing logic here
        return imagePath;
    }

    private String getBaseUrl() {
        // Implement logic to get base URL
        return "http://example.com";
    }

    private boolean fileExists(String filePath) {
        // Implement logic to check if file exists
        return false;
    }

    private boolean isFile(String filePath) {
        // Implement logic to check if path is a file
        return false;
    }

    private String getFormErrors(CertificateForm form) {
        // Implement logic to get form errors
        return "";
    }
}

