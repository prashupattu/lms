package com.example.admin.controllers;

import com.example.models.BlogCategory;
import com.example.services.BlogCategoryService;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.validation.Valid;

@Controller
@RequestMapping("/admin/blog-categories")
public class BlogCategoriesController {

    @Autowired
    private BlogCategoryService blogCategoryService;

    /**
     * Display a listing of the resource.
     */
    @GetMapping
    public String index(@RequestParam(value = "search", required = false) String keyword,
                        @RequestParam(defaultValue = "0") int page,
                        @RequestParam(defaultValue = "25") int size,
                        Model model) {
        PageRequest pageRequest = PageRequest.of(page, size, Sort.by("createdAt").descending());
        Page<BlogCategory> blogCategories;

        if (keyword != null && !keyword.isEmpty()) {
            blogCategories = blogCategoryService.findByNameContaining(keyword, pageRequest);
        } else {
            blogCategories = blogCategoryService.findAll(pageRequest);
        }

        model.addAttribute("blogCategories", blogCategories);
        return "admin/blog-categories/index";
    }

    /**
     * Show the form for creating a new resource.
     */
    @GetMapping("/create")
    public String create(Model model) {
        model.addAttribute("blogCategory", new BlogCategory());
        return "admin/blog-categories/create";
    }

    /**
     * Store a newly created resource in storage.
     */
    @PostMapping
    public String store(@Valid @ModelAttribute BlogCategory blogCategory,
                        BindingResult result,
                        RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/blog-categories/create";
        }

        blogCategoryService.save(blogCategory);
        redirectAttributes.addFlashAttribute("flashMessage", "Changes saved successfully");
        return "redirect:/admin/blog-categories";
    }

    /**
     * Display the specified resource.
     */
    @GetMapping("/{id}")
    public String show(@PathVariable Long id, Model model) {
        BlogCategory blogCategory = blogCategoryService.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog category Id:" + id));
        model.addAttribute("blogCategory", blogCategory);
        return "admin/blog-categories/show";
    }

    /**
     * Show the form for editing the specified resource.
     */
    @GetMapping("/{id}/edit")
    public String edit(@PathVariable Long id, Model model) {
        BlogCategory blogCategory = blogCategoryService.findById(id)
                .orElseThrow(() -> new IllegalArgumentException("Invalid blog category Id:" + id));
        model.addAttribute("blogCategory", blogCategory);
        return "admin/blog-categories/edit";
    }

    /**
     * Update the specified resource in storage.
     */
    @PutMapping("/{id}")
    public String update(@PathVariable Long id,
                         @Valid @ModelAttribute BlogCategory blogCategory,
                         BindingResult result,
                         RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            blogCategory.setId(id);
            return "admin/blog-categories/edit";
        }

        blogCategoryService.save(blogCategory);
        redirectAttributes.addFlashAttribute("flashMessage", "Changes saved successfully");
        return "redirect:/admin/blog-categories";
    }

    /**
     * Remove the specified resource from storage.
     */
    @DeleteMapping("/{id}")
    public String destroy(@PathVariable Long id, RedirectAttributes redirectAttributes) {
        blogCategoryService.deleteById(id);
        redirectAttributes.addFlashAttribute("flashMessage", "Record deleted successfully");
        return "redirect:/admin/blog-categories";
    }
}

