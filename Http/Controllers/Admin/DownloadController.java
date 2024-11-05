package App.Http.Controllers.Admin;

import App.Download;
import App.Http.Controllers.Controller;
import App.Lib.HelperTrait;
import App.V2.Form.DownloadFilter;
import App.V2.Form.DownloadForm;
import App.V2.Model.DownloadFileTable;
import App.V2.Model.DownloadSessionTable;
import App.V2.Model.DownloadTable;
import App.V2.Model.SessionInstructorTable;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.ModelAndView;

import javax.servlet.http.HttpServletRequest;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/admin/download")
public class DownloadController extends Controller {
    private final HelperTrait helperTrait;

    public DownloadController(HelperTrait helperTrait) {
        this.helperTrait = helperTrait;
    }

    @GetMapping
    public ModelAndView index(HttpServletRequest request) {
        DownloadTable table = new DownloadTable();
        DownloadFileTable downloadFileTable = new DownloadFileTable();

        Paginator paginator = table.getPaginatedRecords(true);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        Map<String, Object> model = new HashMap<>();
        model.put("paginator", paginator);
        model.put("pageTitle", __lang("Downloads"));
        model.put("downloadTable", table);
        model.put("fileTable", downloadFileTable);

        return viewModel("admin", getClass(), "index", model);
    }

    @PostMapping("/add")
    public ModelAndView add(HttpServletRequest request) {
        Map<String, Object> output = new HashMap<>();
        DownloadTable downloadTable = new DownloadTable();
        DownloadForm form = new DownloadForm(null, this.getServiceLocator());
        DownloadFilter filter = new DownloadFilter();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);
            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                array.put(downloadTable.getPrimary(), 0);
                int id = downloadTable.saveRecord(array);
                flashMessage(__lang("download-created!"));

                return adminRedirect(new HashMap<String, String>() {{
                    put("controller", "download");
                    put("action", "edit");
                    put("id", String.valueOf(id));
                }});
            } else {
                output.put("flash_message", __lang("save-failed-msg"));
            }
        }

        output.put("form", form);
        output.put("pageTitle", __lang("Add Download"));
        output.put("action", "add");
        output.put("id", null);
        return viewModel("admin", getClass(), "add", output);
    }

    @PostMapping("/edit/{id}")
    public ModelAndView edit(HttpServletRequest request, @PathVariable int id) {
        Map<String, Object> output = new HashMap<>();
        DownloadTable table = new DownloadTable();
        DownloadFilter filter = new DownloadFilter();
        DownloadForm form = new DownloadForm(null, this.getServiceLocator());

        Object row = table.getRecord(id);
        if ("POST".equalsIgnoreCase(request.getMethod())) {
            form.setInputFilter(filter);
            Map<String, String[]> data = request.getParameterMap();
            form.setData(data);
            if (form.isValid()) {
                Map<String, Object> array = form.getData();
                array.put(table.getPrimary(), id);
                table.saveRecord(array);
                output.put("flash_message", __lang("Changes Saved!"));
                flashMessage((String) output.get("flash_message"));
                return redirect().route("admin.download.index");
            } else {
                output.put("flash_message", __lang("save-failed-msg"));
            }
        } else {
            Map<String, Object> data = getObjectProperties(row);
            form.setData(data);
        }

        output.put("form", form);
        output.put("id", id);
        output.put("pageTitle", __lang("Edit Download"));
        output.put("row", row);
        output.put("action", "edit");

        String html = app(DownloadController.class).files(request, id).toHtml();
        output.put("files", html);

        html = app(DownloadController.class).sessions(request, id).toHtml();
        output.put("sessions", html);

        return viewModel("admin", getClass(), "edit", output);
    }

    @GetMapping("/files/{id}")
    public ModelAndView files(HttpServletRequest request, @PathVariable int id) {
        DownloadFileTable table = new DownloadFileTable();
        Object rowset = table.getDownloadRecords(id);
        return viewModel("admin", getClass(), "files", new HashMap<String, Object>() {{
            put("rowset", rowset);
        }});
    }

    @GetMapping("/sessions/{id}")
    public ModelAndView sessions(HttpServletRequest request, @PathVariable int id) {
        DownloadSessionTable table = new DownloadSessionTable();
        Object rowset = table.getDownloadRecords(id);
        return viewModel("admin", getClass(), "sessions", new HashMap<String, Object>() {{
            put("rowset", rowset);
        }});
    }

    @PostMapping("/addfile/{id}")
    public ModelAndView addFile(HttpServletRequest request, @PathVariable int id) {
        String path = request.getParameter("path");
        DownloadFileTable downloadFileTable = new DownloadFileTable();
        path = path.replace("usermedia/", "");
        if (!downloadFileTable.fileExists(id, path)) {
            downloadFileTable.addRecord(new HashMap<String, Object>() {{
                put("download_id", id);
                put("path", path);
                put("enabled", 1);
            }});
        }

        return app(DownloadController.class).files(request, id);
    }

    @PostMapping("/removefile/{id}")
    public ModelAndView removeFile(HttpServletRequest request, @PathVariable int id) {
        DownloadFileTable downloadFileTable = new DownloadFileTable();
        Object row = downloadFileTable.getRecord(id);
        int downloadId = (int) ((Map<String, Object>) row).get("download_id");

        downloadFileTable.deleteRecord(id);
        return app(DownloadController.class).files(request, downloadId);
    }

    @PostMapping("/addsession/{id}")
    public ModelAndView addSession(HttpServletRequest request, @PathVariable int id) {
        DownloadSessionTable downloadSessionTable = new DownloadSessionTable();
        int count = 0;
        if ("POST".equalsIgnoreCase(request.getMethod())) {
            Map<String, String[]> data = request.getParameterMap();
            for (Map.Entry<String, String[]> entry : data.entrySet()) {
                String key = entry.getKey();
                String[] value = entry.getValue();
                if (key.startsWith("session_") && !downloadSessionTable.sessionExists(id, value[0])) {
                    downloadSessionTable.addRecord(new HashMap<String, Object>() {{
                        put("download_id", id);
                        put("course_id", value[0]);
                    }});
                    count++;
                }
            }
            session().flash("flash_message", count + " " + __lang("added-to-download-msg"));
        }

        return adminRedirect(new HashMap<String, String>() {{
            put("controller", "download");
            put("action", "edit");
            put("id", String.valueOf(id));
        }});
    }

    @PostMapping("/removesession/{id}")
    public ModelAndView removeSession(HttpServletRequest request, @PathVariable int id) {
        DownloadSessionTable downloadSessionTable = new DownloadSessionTable();
        Object row = downloadSessionTable.getRecord(id);
        int downloadId = (int) ((Map<String, Object>) row).get("download_id");

        downloadSessionTable.deleteRecord(id);
        return app(DownloadController.class).sessions(request, downloadId);
    }

    @PostMapping("/delete/{id}")
    public ModelAndView delete(HttpServletRequest request, @PathVariable int id) {
        DownloadTable table = new DownloadTable();
        table.deleteRecord(id);
        session().flash("flash_message", __lang("Record deleted"));
        return adminRedirect(new HashMap<String, String>() {{
            put("controller", "download");
            put("action", "index");
        }});
    }

    @PostMapping("/duplicate/{id}")
    public ModelAndView duplicate(HttpServletRequest request, @PathVariable int id) {
        DownloadTable downloadTable = new DownloadTable();
        DownloadFileTable downloadFileTable = new DownloadFileTable();
        DownloadSessionTable downloadSessionTable = new DownloadSessionTable();

        Object downloadRow = downloadTable.getRecord(id);
        Object downloadFileRowset = downloadFileTable.getDownloadRecords(id);
        Object downloadSessionRowset = downloadSessionTable.getDownloadRecords(id);

        Map<String, Object> downloadArray = getObjectProperties(downloadRow);
        downloadArray.remove("id");
        int newId = downloadTable.addRecord(downloadArray);

        for (Object row : downloadFileRowset) {
            Map<String, Object> data = getObjectProperties(row);
            data.remove("id");
            data.put("download_id", newId);
            downloadFileTable.addRecord(data);
        }

        for (Object row : downloadSessionRowset) {
            Map<String, Object> data = getObjectProperties(row);
            data.remove("id");
            data.put("download_id", newId);
            String courseName = (String) data.get("course_name");
            data.remove("course_name");
            downloadSessionTable.addRecord(data);
        }

        session().flash("flash_message", __lang("Download duplicated successfully"));
        return adminRedirect(new HashMap<String, String>() {{
            put("controller", "download");
            put("action", "index");
        }});
    }

    @GetMapping("/browsesessions/{id}")
    public ModelAndView browseSessions(HttpServletRequest request, @PathVariable int id) {
        Map<String, Object> data = app(StudentController.class).sessions(request).getData();
        data.put("id", id);

        SessionInstructorTable sessionInstructorTable = new SessionInstructorTable();
        Object assigned = sessionInstructorTable.getAccountRecords(ADMIN_ID);
        data.put("assigned", assigned);

        return view("admin.download.browsesessions", data);
    }

    @GetMapping("/download/{id}")
    public ResponseEntity<byte[]> download(HttpServletRequest request, @PathVariable int id) {
        set_time_limit(86400);
        DownloadFileTable table = new DownloadFileTable();
        Object row = table.getRecord(id);
        String path = "usermedia/" + ((Map<String, Object>) row).get("path");

        return ResponseEntity.ok()
                .header("Content-Type", getFileMimeType(path))
                .header("Content-Disposition", "attachment; filename=\"" + path.substring(path.lastIndexOf('/') + 1) + "\"")
                .body(readFile(path));
    }
}

