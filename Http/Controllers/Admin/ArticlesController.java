package com.example.controllers.admin;

import com.example.models.Article;
import com.example.services.ArticleService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.validation.Valid;

@Controller
@RequestMapping("/admin/articles")
public class ArticlesController {

    @Autowired
    private ArticleService articleService;

    @GetMapping
    @PreAuthorize("hasAuthority('view_articles')")
    public String index(@RequestParam(value = "filter", required = false) String keyword,
                        @RequestParam(defaultValue = "0") int page,
                        @RequestParam(defaultValue = "25") int size,
                        Model model) {
        Page<Article> articles;
        if (keyword != null && !keyword.isEmpty()) {
            articles = articleService.searchArticles(keyword, PageRequest.of(page, size));
        } else {
            articles = articleService.getAllArticles(PageRequest.of(page, size));
        }
        model.addAttribute("articles", articles);
        model.addAttribute("perPage", size);
        return "admin/articles/index";
    }

    @GetMapping("/create")
    @PreAuthorize("hasAuthority('add_article')")
    public String create(Model model) {
        model.addAttribute("article", new Article());
        return "admin/articles/create";
    }

    @PostMapping
    @PreAuthorize("hasAuthority('add_article')")
    public String store(@Valid @ModelAttribute Article article, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/articles/create";
        }
        
        articleService.createArticle(article);
        redirectAttributes.addFlashAttribute("flash_message", "Changes saved");
        return "redirect:/admin/articles";
    }

    @GetMapping("/{id}")
    @PreAuthorize("hasAuthority('view_articles')")
    public String show(@PathVariable Long id, Model model) {
        Article article = articleService.getArticleById(id);
        model.addAttribute("article", article);
        return "admin/articles/show";
    }

    @GetMapping("/{id}/edit")
    @PreAuthorize("hasAuthority('edit_article')")
    public String edit(@PathVariable Long id, Model model) {
        Article article = articleService.getArticleById(id);
        model.addAttribute("article", article);
        return "admin/articles/edit";
    }

    @PutMapping("/{id}")
    @PreAuthorize("hasAuthority('edit_article')")
    public String update(@PathVariable Long id, @Valid @ModelAttribute Article article, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/articles/edit";
        }
        
        articleService.updateArticle(id, article);
        redirectAttributes.addFlashAttribute("flash_message", "Changes saved");
        return "redirect:/admin/articles";
    }

    @DeleteMapping("/{id}")
    @PreAuthorize("hasAuthority('delete_article')")
    public String destroy(@PathVariable Long id, RedirectAttributes redirectAttributes) {
        articleService.deleteArticle(id);
        redirectAttributes.addFlashAttribute("flash_message", "Record deleted");
        return "redirect:/admin/articles";
    }
}

