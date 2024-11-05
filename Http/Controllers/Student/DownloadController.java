import java.io.*;
import java.nio.file.*;
import java.util.ArrayList;
import java.util.List;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

@Controller
@RequestMapping("/student/download")
public class DownloadController {

    @Autowired
    private DownloadTable downloadTable;

    @Autowired
    private DownloadFileTable downloadFileTable;

    @Autowired
    private DownloadSessionTable downloadSessionTable;

    @Autowired
    private StudentSessionTable studentSessionTable;

    @GetMapping
    public String index(Model model, @RequestParam(defaultValue = "1") int page) {
        Page<Download> paginator = studentSessionTable.getDownloads(getId(), PageRequest.of(page - 1, 30));

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Downloads");
        model.addAttribute("downloadTable", downloadTable);
        model.addAttribute("fileTable", downloadFileTable);
        model.addAttribute("sessionTable", downloadSessionTable);
        model.addAttribute("studentId", getId());

        return "student/download/index";
    }

    @GetMapping("/files/{id}")
    public String files(Model model, @PathVariable int id) {
        Download download = downloadTable.getDownload(id, getId());
        if (download == null) {
            // Add flash message
            return "redirect:/student/download";
        }

        List<DownloadFile> rowset = downloadFileTable.getDownloadRecords(id);
        model.addAttribute("rowset", rowset);
        model.addAttribute("pageTitle", "File List: " + download.getName());
        model.addAttribute("id", id);
        model.addAttribute("row", download);

        return "student/download/files";
    }

    @GetMapping("/file/{id}")
    public void file(HttpServletResponse response, @PathVariable int id) throws IOException {
        DownloadFile file = downloadFileTable.getFile(id, getId());
        Path path = Paths.get("usermedia", file.getPath());

        response.setContentType(Files.probeContentType(path));
        response.setHeader("Content-Disposition", "attachment; filename=\"" + path.getFileName() + "\"");
        Files.copy(path, response.getOutputStream());
    }

    @GetMapping("/allfiles/{id}")
    public void allfiles(HttpServletResponse response, @PathVariable int id) throws IOException {
        List<DownloadFile> rowset = downloadFileTable.getFiles(id, getId());
        Download download = downloadTable.getRecord(id);

        String zipname = sanitizeFilename(download.getName()) + ".zip";
        response.setContentType("application/zip");
        response.setHeader("Content-Disposition", "attachment; filename=" + zipname);

        try (ZipOutputStream zos = new ZipOutputStream(response.getOutputStream())) {
            for (int i = 0; i < rowset.size(); i++) {
                DownloadFile row = rowset.get(i);
                Path path = Paths.get("usermedia", row.getPath());

                if (Files.exists(path)) {
                    ZipEntry zipEntry = new ZipEntry((i + 1) + "-" + path.getFileName().toString());
                    zos.putNextEntry(zipEntry);
                    Files.copy(path, zos);
                    zos.closeEntry();
                }
            }
        }
    }

    private int getId() {
        // Implement method to get current user ID
        return 0;
    }

    private String sanitizeFilename(String filename) {
        // Implement method to sanitize filename
        return filename.replaceAll("[^a-zA-Z0-9.-]", "_");
    }
}

