package com.example.controllers.admin;

import com.example.models.BlogPost;
import com.example.lib.HelperTrait;
import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.security.access.prepost.PreAuthorize;

import javax.validation.Valid;
import java.io.File;
import java.io.IOException;
import java.time.LocalDate;
import java.util.List;

import net.coobird.thumbnailator.Thumbnails;

@Controller
@RequestMapping("/admin/blog-posts")
public class BlogPostsController {

    private static final String BLOG_FILES = "blog_files";
    private static final String UPLOAD_PATH = "upload_path";

    @PreAuthorize("hasAuthority('view_blog')")
    @GetMapping
    public String index(@RequestParam(value = "filter", required = false) String keyword,
                        @RequestParam(value = "page", defaultValue = "0") int page,
                        Model model) {
        int perPage = 25;
        Page<BlogPost> blogposts;

        if (keyword != null && !keyword.isEmpty()) {
            blogposts = BlogPost.search(keyword, PageRequest.of(page, perPage));
        } else {
            blogposts = BlogPost.findAll(PageRequest.of(page, perPage, Sort.by("createdAt").descending()));
        }

        model.addAttribute("blogposts", blogposts);
        return "admin/blog-posts/index";
    }

    @PreAuthorize("hasAuthority('add_blog')")
    @GetMapping("/create")
    public String create(Model model) {
        model.addAttribute("blogPost", new BlogPost());
        return "admin/blog-posts/create";
    }

    @PreAuthorize("hasAuthority('add_blog')")
    @PostMapping
    public String store(@Valid @ModelAttribute("blogPost") BlogPost blogPost,
                        BindingResult result,
                        @RequestParam("cover_photo") MultipartFile coverPhoto) throws IOException {
        if (result.hasErrors()) {
            return "admin/blog-posts/create";
        }

        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        // Assuming you have a User class with getAdmin() method
        // User user = (User) auth.getPrincipal();
        // if (user.getAdmin() != null) {
        //     blogPost.setAdminId(user.getAdmin().getId());
        // }

        if (!coverPhoto.isEmpty()) {
            String path = saveCoverPhoto(coverPhoto);
            blogPost.setCoverPhoto(path);
        }

        blogPost.setContent(saveInlineImages(blogPost.getContent()));

        if (blogPost.getPublishDate() == null) {
            blogPost.setPublishDate(LocalDate.now());
        }

        blogPost.save();

        // Assuming you have a method to set categories
        // blogPost.setCategories(categories);

        return "redirect:/admin/blog-posts";
    }

    @PreAuthorize("hasAuthority('view_blog')")
    @GetMapping("/{id}")
    public String show(@PathVariable Long id, Model model) {
        BlogPost blogpost = BlogPost.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog post Id:" + id));
        model.addAttribute("blogpost", blogpost);
        return "admin/blog-posts/show";
    }

    @PreAuthorize("hasAuthority('edit_blog')")
    @GetMapping("/{id}/edit")
    public String edit(@PathVariable Long id, Model model) {
        BlogPost blogpost = BlogPost.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog post Id:" + id));
        model.addAttribute("blogpost", blogpost);
        return "admin/blog-posts/edit";
    }

    @PreAuthorize("hasAuthority('edit_blog')")
    @PostMapping("/{id}")
    public String update(@PathVariable Long id,
                         @Valid @ModelAttribute("blogPost") BlogPost blogPost,
                         BindingResult result,
                         @RequestParam("cover_photo") MultipartFile coverPhoto) throws IOException {
        if (result.hasErrors()) {
            return "admin/blog-posts/edit";
        }

        BlogPost existingBlogPost = BlogPost.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog post Id:" + id));

        if (!coverPhoto.isEmpty()) {
            if (existingBlogPost.getCoverPhoto() != null) {
                new File(existingBlogPost.getCoverPhoto()).delete();
            }
            String path = saveCoverPhoto(coverPhoto);
            existingBlogPost.setCoverPhoto(path);
        }

        existingBlogPost.setTitle(blogPost.getTitle());
        existingBlogPost.setContent(saveInlineImages(blogPost.getContent()));
        existingBlogPost.setPublishDate(blogPost.getPublishDate() != null ? blogPost.getPublishDate() : LocalDate.now());

        existingBlogPost.save();

        // Assuming you have a method to set categories
        // existingBlogPost.setCategories(categories);

        return "redirect:/admin/blog-posts";
    }

    @PreAuthorize("hasAuthority('edit_blog')")
    @PostMapping("/{id}/remove-picture")
    public String removePicture(@PathVariable Long id) {
        BlogPost blogPost = BlogPost.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog post Id:" + id));
        if (blogPost.getCoverPhoto() != null) {
            new File(blogPost.getCoverPhoto()).delete();
            blogPost.setCoverPhoto(null);
            blogPost.save();
        }
        return "redirect:/admin/blog-posts/" + id + "/edit";
    }

    @PreAuthorize("hasAuthority('delete_blog')")
    @PostMapping("/{id}/delete")
    public String destroy(@PathVariable Long id) {
        BlogPost blogPost = BlogPost.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog post Id:" + id));
        blogPost.delete();
        return "redirect:/admin/blog-posts";
    }

    private String saveCoverPhoto(MultipartFile file) throws IOException {
        String filename = file.getOriginalFilename();
        String path = UPLOAD_PATH + "/" + BLOG_FILES + "/" + filename;
        File destFile = new File(path);
        file.transferTo(destFile);

        Thumbnails.of(destFile)
                .size(500, 500)
                .keepAspectRatio(true)
                .toFile(destFile);

        return path;
    }

    private String saveInlineImages(String content) {
        // Implement the logic to save inline images
        return content;
    }
}

