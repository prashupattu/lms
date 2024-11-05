package App.Http.Controllers.Api;

import App.BlogPost;
import App.Http.Controllers.Controller;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.bind.annotation.RequestParam;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/blog")
public class BlogController extends Controller {

    @GetMapping("/posts")
    public ResponseEntity<Map<String, Object>> posts(@RequestParam Map<String, String> params) {
        int rowsPerPage;
        if (params.containsKey("rows") && !params.get("rows").isEmpty() && Integer.parseInt(params.get("rows")) <= 100) {
            rowsPerPage = Integer.parseInt(params.get("rows"));
        } else {
            rowsPerPage = 30;
        }

        List<BlogPost> select = BlogPost.orderBy("publish_date", "desc");

        if (params.containsKey("filter") && !params.get("filter").isEmpty()) {
            String filter = params.get("filter");
            select = BlogPost.whereRaw("MATCH (title,content,meta_title,meta_description) AGAINST (? IN NATURAL LANGUAGE MODE)", filter);
        }

        Map<String, Object> data = new HashMap<>();
        data.put("total", select.size());
        List<BlogPost> rowset = select.subList(0, Math.min(rowsPerPage, select.size()));
        data.putAll(rowset);

        List<Map<String, Object>> newData = new ArrayList<>();
        for (BlogPost row : rowset) {
            Map<String, Object> rowData = new HashMap<>();
            rowData.put("content", stripTags(limitLength(row.getContent(), 200)));
            rowData.put("date", showDate("d M Y", row.getPublishDate()));
            rowData.put("newsflash_id", row.getId());
            rowData.put("picture", row.getCoverPhoto());
            newData.add(rowData);
        }

        data.put("data", newData);
        return ResponseEntity.ok(jsonResponse(data));
    }

    @GetMapping("/post/{id}")
    public ResponseEntity<Map<String, Object>> getPost(@PathVariable Long id) {
        BlogPost row = BlogPost.find(id);
        Map<String, Object> data = row.toMap();
        data.put("date", showDate("d M Y", data.get("publish_date")));
        data.put("newsflash_id", data.get("id"));
        data.put("picture", data.get("cover_photo"));
        return ResponseEntity.ok(jsonResponse(data));
    }

    private String stripTags(String content) {
        // Implement the stripTags logic
    }

    private String limitLength(String content, int length) {
        // Implement the limitLength logic
    }

    private String showDate(String format, Object date) {
        // Implement the showDate logic
    }

    private Map<String, Object> jsonResponse(Map<String, Object> data) {
        // Implement the jsonResponse logic
    }
}

