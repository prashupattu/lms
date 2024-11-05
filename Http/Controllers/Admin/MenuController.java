package com.example.controllers.admin;

import com.example.models.Article;
import com.example.models.CourseCategory;
import com.example.models.FooterMenu;
import com.example.models.HeaderMenu;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;

import javax.validation.Valid;
import java.util.*;
import java.util.stream.Collectors;

@Controller
@RequestMapping("/admin")
public class MenuController {

    @Autowired
    private HeaderMenuRepository headerMenuRepository;

    @Autowired
    private FooterMenuRepository footerMenuRepository;

    @Autowired
    private ArticleRepository articleRepository;

    @Autowired
    private CourseCategoryRepository courseCategoryRepository;

    @GetMapping("/header-menu")
    public String headerMenu(Model model) {
        model.addAllAttributes(getLinks());
        return "admin/menu/header_menu";
    }

    @GetMapping("/load-header-menu")
    public String loadHeaderMenu(Model model) {
        List<HeaderMenu> menus = headerMenuRepository.findByParentIdOrderBySortOrder(0L);
        model.addAttribute("menus", menus);
        return "admin/menu/load_header";
    }

    @PostMapping("/save-header-menu")
    public ResponseEntity<?> saveHeaderMenu(@Valid @RequestBody HeaderMenu headerMenu, BindingResult result) {
        if (result.hasErrors()) {
            String errors = result.getAllErrors().stream()
                    .map(error -> error.getDefaultMessage())
                    .collect(Collectors.joining(", "));
            return ResponseEntity.badRequest().body(Map.of("error", errors, "status", false));
        }

        if (headerMenu.getParentId() == null) {
            headerMenu.setParentId(0L);
        }

        if (headerMenu.getName() == null || headerMenu.getName().isEmpty()) {
            headerMenu.setName(headerMenu.getLabel());
        }

        if (headerMenu.getSortOrder() == null) {
            headerMenu.setSortOrder(0);
        }

        headerMenuRepository.save(headerMenu);
        return ResponseEntity.ok(Map.of("status", true));
    }

    @PutMapping("/update-header-menu/{id}")
    public ResponseEntity<?> updateHeaderMenu(@PathVariable Long id, @Valid @RequestBody HeaderMenu headerMenu, BindingResult result) {
        if (result.hasErrors()) {
            String errors = result.getAllErrors().stream()
                    .map(error -> error.getDefaultMessage())
                    .collect(Collectors.joining(", "));
            return ResponseEntity.badRequest().body(Map.of("error", errors, "status", false));
        }

        HeaderMenu existingMenu = headerMenuRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("HeaderMenu not found"));

        existingMenu.setLabel(headerMenu.getLabel());
        existingMenu.setSortOrder(headerMenu.getSortOrder());

        headerMenuRepository.save(existingMenu);
        return ResponseEntity.ok(Map.of("status", true));
    }

    @DeleteMapping("/delete-header-menu/{id}")
    public ResponseEntity<?> deleteHeaderMenu(@PathVariable Long id) {
        headerMenuRepository.deleteById(id);
        return ResponseEntity.ok(Map.of("status", true));
    }

    @GetMapping("/footer-menu")
    public String footerMenu(Model model) {
        model.addAllAttributes(getLinks());
        return "admin/menu/footer_menu";
    }

    @GetMapping("/load-footer-menu")
    public String loadFooterMenu(Model model) {
        List<FooterMenu> menus = footerMenuRepository.findByParentIdOrderBySortOrder(0L);
        model.addAttribute("menus", menus);
        return "admin/menu/load_footer";
    }

    @PostMapping("/save-footer-menu")
    public ResponseEntity<?> saveFooterMenu(@Valid @RequestBody FooterMenu footerMenu, BindingResult result) {
        if (result.hasErrors()) {
            String errors = result.getAllErrors().stream()
                    .map(error -> error.getDefaultMessage())
                    .collect(Collectors.joining(", "));
            return ResponseEntity.badRequest().body(Map.of("error", errors, "status", false));
        }

        if (footerMenu.getParentId() == null) {
            footerMenu.setParentId(0L);
        }

        if (footerMenu.getName() == null || footerMenu.getName().isEmpty()) {
            footerMenu.setName(footerMenu.getLabel());
        }

        if (footerMenu.getSortOrder() == null) {
            footerMenu.setSortOrder(0);
        }

        footerMenuRepository.save(footerMenu);
        return ResponseEntity.ok(Map.of("status", true));
    }

    @PutMapping("/update-footer-menu/{id}")
    public ResponseEntity<?> updateFooterMenu(@PathVariable Long id, @Valid @RequestBody FooterMenu footerMenu, BindingResult result) {
        if (result.hasErrors()) {
            String errors = result.getAllErrors().stream()
                    .map(error -> error.getDefaultMessage())
                    .collect(Collectors.joining(", "));
            return ResponseEntity.badRequest().body(Map.of("error", errors, "status", false));
        }

        FooterMenu existingMenu = footerMenuRepository.findById(id)
                .orElseThrow(() -> new ResourceNotFoundException("FooterMenu not found"));

        existingMenu.setLabel(footerMenu.getLabel());
        existingMenu.setSortOrder(footerMenu.getSortOrder());

        footerMenuRepository.save(existingMenu);
        return ResponseEntity.ok(Map.of("status", true));
    }

    @DeleteMapping("/delete-footer-menu/{id}")
    public ResponseEntity<?> deleteFooterMenu(@PathVariable Long id) {
        footerMenuRepository.deleteById(id);
        return ResponseEntity.ok(Map.of("status", true));
    }

    private Map<String, Object> getLinks() {
        List<Map<String, String>> pages = Arrays.asList(
                Map.of("name", "Home", "url", "/"),
                Map.of("name", "Courses", "url", "/courses"),
                Map.of("name", "Sessions", "url", "/sessions"),
                Map.of("name", "Blog", "url", "/blog"),
                Map.of("name", "Instructors", "url", "/instructors"),
                Map.of("name", "Contact", "url", "/contact"),
                Map.of("name", "Privacy Policy", "url", "/privacy"),
                Map.of("name", "Terms & Conditions", "url", "/terms")
        );

        List<Map<String, String>> articles = articleRepository.findAll().stream()
                .map(article -> Map.of(
                        "name", limitLength(article.getTitle(), 150),
                        "url", "/article/" + article.getSlug()
                ))
                .collect(Collectors.toList());

        List<Map<String, String>> categories = courseCategoryRepository.findAllByOrderBySortOrder().stream()
                .map(category -> Map.of(
                        "name", limitLength(category.getName(), 150),
                        "url", "/?category=" + category.getId()
                ))
                .collect(Collectors.toList());

        return Map.of("pages", pages, "articles", articles, "categories", categories);
    }

    private String limitLength(String text, int maxLength) {
        return text.length() > maxLength ? text.substring(0, maxLength) : text;
    }
}

