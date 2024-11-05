package App.Http.Controllers.Admin;

import App.Http.Controllers.Controller;
import App.Lib.HelperTrait;
import org.springframework.http.HttpRequest;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestMethod;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.util.HashMap;
import java.util.Map;

@RestController
public class FilemanagerController extends Controller {
    private HelperTrait helperTrait;
    private Map<String, Object> data = new HashMap<>();
    private String filePath;
    private String fileUrl;

    @RequestMapping(value = "/filemanager", method = RequestMethod.GET)
    public String index(HttpRequest request) {
        setFilePath();
        String user = "";
        if (System.getProperty("USER_ID") != null) {
            user = "/" + System.getProperty("USER_ID");
        }
        data.put("pageTitle", "File Manager");
        data.put("directory", fileUrl);

        if (request.getParameter("field") != null) {
            data.put("field", request.getParameter("field"));
        } else {
            data.put("field", "");
        }

        data.put("siteUrl", getBaseUrl());

        return view("admin.filemanager.index", data);
    }

    private void setFilePath() {
        String user = "";
        if (System.getProperty("USER_ID") != null) {
            user = "/" + System.getProperty("USER_ID");
        }
        String filePath = "usermedia" + user;

        File directory = new File(filePath);
        if (!directory.exists()) {
            directory.mkdirs();
        }

        String fileUrl = getBaseUrl() + "/usermedia" + user;

        if (!Auth.user().can("access", "global_resource_access")) {
            File adminFilesDir = new File(System.getProperty("USER_PATH") + "/admin_files");
            if (!adminFilesDir.isDirectory()) {
                adminFilesDir.mkdirs();
            }

            fileUrl = fileUrl + "/admin_files/" + getAdminId();
            filePath = filePath + "/admin_files/" + getAdminId();
            File subDirectory = new File(filePath);
            if (!subDirectory.isDirectory()) {
                subDirectory.mkdirs();
            }
        }

        this.filePath = filePath;
        this.fileUrl = fileUrl;
    }

    @RequestMapping(value = "/connector", method = RequestMethod.POST)
    public void connector(HttpRequest request) {
        String dir = "client/vendor/filemanager/php/";
        // Include elFinder classes here

        // Access control function
        boolean access(String attr, String path, Object data, Object volume) {
            return path.startsWith(".") ? !(attr.equals("read") || attr.equals("write")) : null;
        }

        // Logger function
        void logger(String cmd, Object result, Object args, Object elfinder) {
            StringBuilder log = new StringBuilder(String.format("[%s] %s:", new java.util.Date(), cmd.toUpperCase()));
            // Process result and build log
            String logFile = "../files/temp/log.txt";
            File logDir = new File(logFile).getParentFile();
            if (!logDir.exists() && !logDir.mkdirs()) {
                return;
            }
            try (FileWriter fw = new FileWriter(logFile, true)) {
                fw.write(log.toString());
            } catch (IOException e) {
                e.printStackTrace();
            }
        }

        // Set custom page
        setFilePath();

        Map<String, Object> opts = new HashMap<>();
        opts.put("debug", true);
        // Add other options...

        // Run elFinder
        // elFinderConnector connector = new elFinderConnector(new elFinder(opts));
        // connector.run();
    }

    public String getBaseUrl() {
        return "http://localhost"; // Replace with actual base URL logic
    }

    @RequestMapping(value = "/image", method = RequestMethod.GET)
    public void image(HttpRequest request) {
        if (request.getParameter("image") != null) {
            String image = request.getParameter("image");
            // Resize image logic here
            // exit(resizeImage(html_entity_decode(urldecode(image, ENT_QUOTES, 'UTF-8'), 100, 100, getBaseUrl())));
        }
    }
}

