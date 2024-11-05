package App.Http.Controllers.Api;

import App.Http.Controllers.Controller;
import App.Download;
import App.V2.Model.DownloadFileTable;
import App.V2.Model.DownloadSessionTable;
import App.V2.Model.DownloadTable;
import App.V2.Model.StudentSessionTable;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.bind.annotation.RequestBody;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/downloads")
public class DownloadController extends Controller {

    public ResponseEntity<Map<String, Object>> downloads(@RequestBody Map<String, Object> request) {
        DownloadTable table = new DownloadTable();
        DownloadFileTable downloadFileTable = new DownloadFileTable();
        DownloadSessionTable downloadSessionTable = new DownloadSessionTable();
        StudentSessionTable studentSessionTable = new StudentSessionTable();

        List<Download> paginator = studentSessionTable.getDownloads(this.getApiStudent().getId());
        Map<String, Object> params = request;

        int perPage = 30;
        int page = params.get("page") == null ? 1 : (int) params.get("page");
        paginator.setCurrentPageNumber(page);
        paginator.setItemCountPerPage(perPage);

        Map<String, Object> output = new HashMap<>();

        output.put("per_page", perPage);
        output.put("total", studentSessionTable.getDownloadsTotal(this.getApiStudentId()));
        output.put("current_page", page);

        int totalPages = (int) Math.ceil((double) output.get("total") / perPage);
        List<Map<String, Object>> downloadRows = new ArrayList<>();
        if (page <= totalPages) {
            for (Download row : paginator) {
                Download dRow = Download.find(row.getDownloadId());
                Map<String, Object> downloadRow = new HashMap<>();
                downloadRow.put("download_id", row.getDownloadId());
                downloadRow.put("download_name", row.getDownloadName());
                downloadRow.put("description", dRow.getDescription());
                downloadRow.put("files", downloadFileTable.getTotalForDownload(row.getDownloadId()));
                downloadRows.add(downloadRow);
            }
        }

        output.put("data", downloadRows);
        return ResponseEntity.ok(jsonResponse(output));
    }

    public ResponseEntity<Map<String, Object>> getDownload(@RequestBody Map<String, Object> request, @PathVariable int id) {
        DownloadTable downloadTable = new DownloadTable();

        Student student = this.getApiStudent();

        Download row = downloadTable.getDownload(id, student.getId());
        if (row == null) {
            return ResponseEntity.ok(jsonResponse(Map.of(
                "status", false,
                "message", "You do not have permission to access this download"
            )));
        }

        Download download = apiDownload(Download.find(id));
        download.setCreatedOn(stamp(download.getCreatedAt()));
        download.setAccountId(download.getAdminId());

        DownloadFileTable table = new DownloadFileTable();
        List<DownloadFile> rowset = table.getDownloadRecords(id);

        List<Map<String, Object>> files = new ArrayList<>();
        for (DownloadFile row : rowset) {
            row.setDownloadFileId(row.getId());
            row.setCreatedOn(stamp(row.getCreatedAt()));
            files.add(row);
        }

        download.setFiles(files);

        return ResponseEntity.ok(jsonResponse(Map.of(
            "status", true,
            "download", download
        )));
    }

    public void file(@RequestBody Map<String, Object> request, @PathVariable int id) {
        setTimeLimit(86400);

        DownloadFileTable table = new DownloadFileTable();
        DownloadFile row = table.getFile(id, this.getApiStudent().getId());
        String path = "usermedia/" + row.getPath();
        response.setContentType(getFileMimeType(path));
        response.setHeader("Content-Disposition", "attachment; filename=\"" + basename(path) + "\"");
        response.setContentLength((int) new File(path).length());
        try (InputStream in = new FileInputStream(path); OutputStream out = response.getOutputStream()) {
            byte[] buffer = new byte[1024];
            int bytesRead;
            while ((bytesRead = in.read(buffer)) != -1) {
                out.write(buffer, 0, bytesRead);
            }
        } catch (IOException e) {
            e.printStackTrace();
        }
        exit();
    }
}

