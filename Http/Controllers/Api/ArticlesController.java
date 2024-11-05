package App.Http.Controllers.Api;

import App.Http.Controllers.Controller;
import App.Article;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import java.util.List;
import java.util.stream.Collectors;

@RestController
@RequestMapping("/api/articles")
public class ArticlesController extends Controller {

    @GetMapping
    public ResponseEntity<List<ArticleResponse>> articles() {
        List<Article> rowset = Article.where("mobile", 1)
                                       .select("id as article_id", "title as article_name", "content as article_content", "slug as alias")
                                       .orderBy("title")
                                       .get();

        List<ArticleResponse> data = rowset.stream()
                                            .map(ArticleResponse::new)
                                            .collect(Collectors.toList());
        return ResponseEntity.ok(data);
    }

    @GetMapping("/{id}")
    public ResponseEntity<ArticleResponse> getArticle(@PathVariable Long id) {
        Article row = Article.select("id as article_id", "title as article_name", "content as article_content", "slug as alias")
                             .find(id);
        ArticleResponse data = new ArticleResponse(row);
        return ResponseEntity.ok(data);
    }

    public static class ArticleResponse {
        private Long article_id;
        private String article_name;
        private String article_content;
        private String alias;

        public ArticleResponse(Article article) {
            this.article_id = article.getId();
            this.article_name = article.getTitle();
            this.article_content = article.getContent();
            this.alias = article.getSlug();
        }

        // Getters and setters
    }
}

