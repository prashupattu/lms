package com.example.demo.controller;

import com.example.demo.model.BlogCategory;
import com.example.demo.model.BlogPost;
import com.example.demo.service.BlogService; // Service for blog-related operations
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;

import java.time.LocalDateTime;
import java.util.List;

@Controller
public class BlogController {

    @Autowired
    private BlogService blogService; // Assume you have a service for handling blog logic

    @GetMapping("/blog")
    public String index(@RequestParam(required = false) String category,
                        @RequestParam(required = false) String q,
                        Model model) {
        String title = "Blog"; // Default title
        Page<BlogPost> posts;
        List<BlogPost> recentPosts = blogService.findRecentPosts(PageRequest.of(0, 5)); // Get recent posts
        List<BlogCategory> categories = blogService.findAllCategories(); // Get all categories

        if (category != null && !category.isEmpty()) {
            title = blogService.findCategoryById(Long.parseLong(category)).getName();
            posts = blogService.findPostsByCategory(category, PageRequest.of(0, 20));
        } else if (q != null && !q.isEmpty()) {
            title = "Search Results: " + q;
            posts = blogService.searchPosts(q, PageRequest.of(0, 20));
        } else {
            posts = blogService.findEnabledPosts(PageRequest.of(0, 20));
        }

        model.addAttribute("posts", posts);
        model.addAttribute("recent", recentPosts);
        model.addAttribute("title", title);
        model.addAttribute("categories", categories);

        return "site/blog/index"; // Return the view for blog index
    }

    @GetMapping("/blog/{id}")
    public String post(@PathVariable Long id, Model model) {
        BlogPost blogPost = blogService.findPostById(id);
        List<BlogCategory> categories = blogService.findAllCategories();
        model.addAttribute("blogPost", blogPost);
        model.addAttribute("categories", categories);
        return "site/blog/post"; // Return the view for individual blog post
    }
}
