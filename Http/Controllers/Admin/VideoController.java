import java.io.File;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.nio.file.StandardCopyOption;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.multipart.MultipartFile;

import com.amazonaws.services.s3.AmazonS3;
import com.amazonaws.services.s3.model.PutObjectRequest;
import com.fasterxml.jackson.databind.ObjectMapper;

@Controller
@RequestMapping("/admin/video")
public class VideoController {

    private static final String VIDEO_DIR = "uservideo";
    private static final String VIDEO_PATH = "uservideo";

    @Autowired
    private VideoTable videoTable;

    @Autowired
    private AmazonS3 s3Client;

    @GetMapping("/index")
    public String index(HttpServletRequest request, Model model) {
        String filter = request.getParameter("filter");
        String sort = request.getParameter("sort");

        if (filter == null) {
            filter = "";
        }

        if (sort == null) {
            sort = "";
        }

        // Assuming VideoTable has a method getVideos that returns a paginator
        Paginator paginator = videoTable.getVideos(true, filter, sort);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page") != null ? request.getParameter("page") : "1"));
        paginator.setItemCountPerPage(30);

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Video Library");
        model.addAttribute("filter", filter);
        model.addAttribute("sort", sort);

        return "admin/video/index";
    }

    @PostMapping("/add")
    public String add(HttpServletRequest request, @RequestParam("files") MultipartFile file, Model model) throws IOException {
        if (request.getMethod().equalsIgnoreCase("POST")) {
            if (file.isEmpty()) {
                model.addAttribute("error", "Invalid upload");
                return "admin/video/add";
            }

            Path targetDir = Paths.get(VIDEO_PATH);
            if (!Files.exists(targetDir)) {
                Files.createDirectories(targetDir);
            }

            String uniqueID = UUID.randomUUID().toString();
            String ext = getFileExtension(file.getOriginalFilename());
            String newVideoName = uniqueID + "." + ext;

            Video video = new Video();
            video.setName(getFileNameWithoutExtension(file.getOriginalFilename()));
            video.setAdminId(getAdministratorID());
            video.setCreatedAt(System.currentTimeMillis());
            video.setFileName(newVideoName);
            video.setFileSize(file.getSize());
            video.setLength(0);
            video.setMimeType(file.getContentType());
            videoTable.save(video);

            Path videoDirectory = targetDir.resolve(String.valueOf(video.getId()));
            Files.createDirectories(videoDirectory);

            Path videoDestination = videoDirectory.resolve(newVideoName);
            Files.copy(file.getInputStream(), videoDestination, StandardCopyOption.REPLACE_EXISTING);

            // Image generation logic here...

            model.addAttribute("message", "File upload success: " + file.getOriginalFilename());
        }

        long maxSize = getSetting("general_video_max_size", 200) * 1048576;
        model.addAttribute("pageTitle", "Add Videos");
        model.addAttribute("maxSize", maxSize / 1048576 + "MB");
        model.addAttribute("maxSizeB", maxSize);

        return "admin/video/add";
    }

    @GetMapping("/delete")
    public String delete(@RequestParam("id") int id, Model model) {
        try {
            Video video = videoTable.find(id);
            Path dir = Paths.get(VIDEO_PATH, String.valueOf(video.getId()));
            deleteDirectory(dir);

            if (video.getLocation().equals("r")) {
                Path path = dir.resolve(video.getFileName());
                s3Client.deleteObject("your-bucket-name", path.toString());
            }

            videoTable.deleteRecord(id);
            model.addAttribute("message", "Record deleted");
        } catch (Exception ex) {
            model.addAttribute("error", ex.getMessage());
        }

        return "redirect:/admin/video/index";
    }

    @GetMapping("/removeimage")
    public String removeImage(@RequestParam("id") int id, Model model) {
        Video video = videoTable.find(id);
        validateAdminOwner(video);
        Path path = Paths.get(VIDEO_PATH, String.valueOf(video.getId()), videoImage(video.getFileName()));
        try {
            Files.delete(path);
        } catch (IOException e) {
            model.addAttribute("error", e.getMessage());
        }

        return "redirect:/admin/video/index";
    }

    @GetMapping("/play")
    public String play(@RequestParam("id") int id, Model model) {
        Video video = videoTable.find(id);
        model.addAttribute("video", video);
        model.addAttribute("videoUrl", adminUrl("controller=video&action=serve&id=" + video.getId()));

        String poster = "";
        String name = fileName(video.getFileName());
        Path videoPath = Paths.get(VIDEO_PATH, String.valueOf(video.getId()), video.getFileName());
        if (Files.exists(Paths.get(VIDEO_PATH, String.valueOf(video.getId()), name + ".jpg"))) {
            poster = getBaseUrl() + "/uservideo/" + video.getId() + "/" + name + ".jpg";
        }
        model.addAttribute("poster", poster);

        if (video.getLocation().equals("l")) {
            model.addAttribute("type", Files.probeContentType(videoPath));
        } else {
            model.addAttribute("type", video.getMimeType());
            model.addAttribute("videoUrl", s3Client.generatePresignedUrl("your-bucket-name", videoPath.toString(), java.time.Duration.ofHours(12)).toString());
        }

        return "admin/video/play";
    }

    @GetMapping("/serve")
    public void serve(@RequestParam("id") int id, HttpServletResponse response) throws IOException {
        Video video = videoTable.find(id);
        Path path = Paths.get(VIDEO_PATH, String.valueOf(video.getId()), video.getFileName());

        if (Files.exists(path)) {
            long size = Files.size(path);
            String type = Files.probeContentType(path);
            long length = size;
            long start = 0;
            long end = size - 1;

            response.setContentType(type);
            response.setHeader("Accept-Ranges", "0-" + length);

            if (request.getHeader("Range") != null) {
                long c_start = start;
                long c_end = end;
                String range = request.getHeader("Range").replace("bytes=", "");
                if (range.contains(",")) {
                    response.setStatus(HttpServletResponse.SC_REQUESTED_RANGE_NOT_SATISFIABLE);
                    response.setHeader("Content-Range", "bytes " + start + "-" + end + "/" + size);
                    return;
                }
                if (range.startsWith("-")) {
                    c_start = size - Long.parseLong(range.substring(1));
                } else {
                    String[] rangeParts = range.split("-");
                    c_start = Long.parseLong(rangeParts[0]);
                    c_end = rangeParts.length > 1 ? Long.parseLong(rangeParts[1]) : size;
                }
                c_end = Math.min(c_end, end);
                if (c_start > c_end || c_start > size - 1 || c_end >= size) {
                    response.setStatus(HttpServletResponse.SC_REQUESTED_RANGE_NOT_SATISFIABLE);
                    response.setHeader("Content-Range", "bytes " + start + "-" + end + "/" + size);
                    return;
                }
                start = c_start;
                end = c_end;
                length = end - start + 1;
                response.setStatus(HttpServletResponse.SC_PARTIAL_CONTENT);
            }

            response.setHeader("Content-Range", "bytes " + start + "-" + end + "/" + size);
            response.setHeader("Content-Length", String.valueOf(length));

            try (InputStream inputStream = Files.newInputStream(path)) {
                inputStream.skip(start);
                byte[] buffer = new byte[1024 * 8];
                int bytesRead;
                while ((bytesRead = inputStream.read(buffer)) != -1) {
                    response.getOutputStream().write(buffer, 0, bytesRead);
                }
            }
        } else {
            response.sendError(HttpServletResponse.SC_NOT_FOUND, "File not found");
        }
    }

    private void deleteDirectory(Path path) throws IOException {
        if (Files.exists(path)) {
            Files.walk(path)
                .sorted((a, b) -> b.compareTo(a))
                .forEach(p -> {
                    try {
                        Files.delete(p);
                    } catch (IOException e) {
                        e.printStackTrace();
                    }
                });
        }
    }

    private String getFileExtension(String fileName) {
        return fileName.substring(fileName.lastIndexOf(".") + 1);
    }

    private String getFileNameWithoutExtension(String fileName) {
        return fileName.substring(0, fileName.lastIndexOf("."));
    }

    private int getAdministratorID() {
        // Implement your logic to get the administrator ID
        return 1;
    }

    private long getSetting(String key, long defaultValue) {
        // Implement your logic to get the setting value
        return defaultValue;
    }

    private void validateAdminOwner(Video video) {
        // Implement your logic to validate the admin owner
    }

    private String videoImage(String fileName) {
        // Implement your logic to get the video image file name
        return fileName.replaceFirst("[.][^.]+$", ".jpg");
    }

    private String fileName(String fileName) {
        // Implement your logic to get the file name without extension
        return fileName.substring(0, fileName.lastIndexOf("."));
    }

    private String getBaseUrl() {
        // Implement your logic to get the base URL
        return "http://example.com";
    }

    private String adminUrl(String params) {
        // Implement your logic to generate the admin URL
        return "http://example.com/admin?" + params;
    }
}

