package com.example.controllers.admin;

import com.example.models.User;
import com.example.lib.HelperTrait;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.validation.Valid;
import java.util.Optional;

@Controller
@RequestMapping("/admin/admins")
public class AdminsController {

    @Autowired
    private UserRepository userRepository;

    @Autowired
    private BCryptPasswordEncoder passwordEncoder;

    private HelperTrait helperTrait;

    @GetMapping("")
    public String index(@RequestParam(value = "search", required = false) String keyword, Model model) {
        int perPage = 25;
        Page<User> admins = userRepository.findByRoleIdAndAdminNotNull(1, PageRequest.of(0, perPage, Sort.by("createdAt").descending()));
        model.addAttribute("admins", admins);
        return "admin/admins/index";
    }

    @GetMapping("/create")
    public String create(Model model) {
        String displayImage = helperTrait.resizeImage("img/no_image.jpg", 100, 100, helperTrait.getBaseUrl());
        String noImage = helperTrait.resizeImage("img/no_image.jpg", 100, 100, helperTrait.getBaseUrl());
        model.addAttribute("displayImage", displayImage);
        model.addAttribute("noImage", noImage);
        return "admin/admins/create";
    }

    @PostMapping("")
    public String store(@Valid @ModelAttribute User user, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/admins/create";
        }
        user.setPassword(passwordEncoder.encode(user.getPassword()));
        user.setRoleId(1);
        user.setAdminRoleId(user.getRole());
        User admin = userRepository.save(user);
        admin.getAdmin().create(user);
        redirectAttributes.addFlashAttribute("flashMessage", "Changes saved");
        return "redirect:/admin/admins";
    }

    @GetMapping("/{id}")
    public String show(@PathVariable int id, Model model) {
        Optional<User> admin = userRepository.findById(id);
        if (admin.isPresent()) {
            checkAdmin(admin.get());
            model.addAttribute("admin", admin.get());
            return "admin/admins/show";
        }
        return "redirect:/admin/admins";
    }

    @GetMapping("/{id}/edit")
    public String edit(@PathVariable int id, Model model) {
        Optional<User> admin = userRepository.findById(id);
        if (admin.isPresent()) {
            String displayImage;
            if (admin.get().getPicture() != null && new java.io.File(DIR_MER_IMAGE + admin.get().getPicture()).exists()) {
                displayImage = helperTrait.resizeImage(admin.get().getPicture(), 100, 100, helperTrait.getBaseUrl());
            } else {
                displayImage = helperTrait.resizeImage("img/no_image.jpg", 100, 100, helperTrait.getBaseUrl());
            }
            String noImage = helperTrait.resizeImage("img/no_image.jpg", 100, 100, helperTrait.getBaseUrl());
            checkAdmin(admin.get());
            model.addAttribute("admin", admin.get());
            model.addAttribute("displayImage", displayImage);
            model.addAttribute("noImage", noImage);
            return "admin/admins/edit";
        }
        return "redirect:/admin/admins";
    }

    @PutMapping("/{id}")
    public String update(@PathVariable int id, @Valid @ModelAttribute User user, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/admins/edit";
        }
        Optional<User> existingAdmin = userRepository.findById(id);
        if (existingAdmin.isPresent()) {
            User admin = existingAdmin.get();
            if (!user.getPassword().isEmpty()) {
                admin.setPassword(passwordEncoder.encode(user.getPassword()));
            }
            admin.setName(user.getName());
            admin.setEmail(user.getEmail());
            admin.setAdminRoleId(user.getRole());
            admin = userRepository.save(admin);
            admin.getAdmin().update(user);
            redirectAttributes.addFlashAttribute("flashMessage", "Changes saved");
        }
        return "redirect:/admin/admins";
    }

    @DeleteMapping("/{id}")
    public String destroy(@PathVariable int id, RedirectAttributes redirectAttributes) {
        try {
            userRepository.deleteById(id);
            redirectAttributes.addFlashAttribute("flashMessage", "Record deleted");
        } catch (Exception e) {
            redirectAttributes.addFlashAttribute("flashMessage", "Locked message");
            return "redirect:/admin/admins";
        }
        return "redirect:/admin/admins";
    }

    private User checkAdmin(User admin) {
        if (admin.getAdmin() == null) {
            admin.setAdmin(new Admin());
            admin.getAdmin().setAdminRoleId(2);
            admin.getAdmin().setPublic(false);
            userRepository.save(admin);
        }
        return admin;
    }
}

