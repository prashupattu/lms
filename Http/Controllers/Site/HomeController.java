package com.example.controllers.site;

import com.example.models.Admin;
import com.example.models.Article;
import com.example.controllers.Controller;
import com.example.lib.CronJobs;
import com.example.lib.HelperTrait;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import javax.servlet.http.HttpServletRequest;
import javax.validation.Valid;
import java.io.File;
import java.time.LocalDateTime;
import java.util.List;

@Controller
public class HomeController extends Controller {

    @Autowired
    private HelperTrait helperTrait;

    @GetMapping("/")
    public String index() {
        // Check if installation file exists and redirect to install if not
        if (!new File("../storage/installed").exists()) {
            return "redirect:/install";
        }
        return "site/home/index";
    }

    @GetMapping("/article/{slug}")
    public String article(@PathVariable String slug, Model model) {
        Article article = Article.findBySlugAndEnabled(slug, true);
        if (article == null) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        }
        model.addAttribute("article", article);
        return "site/home/article";
    }

    @GetMapping("/contact")
    public String contact(Model model) {
        String captchaUrl = getCaptchaSrc();
        model.addAttribute("captchaUrl", captchaUrl);
        return "site/home/contact";
    }

    @PostMapping("/send-mail")
    public String sendMail(@Valid @ModelAttribute ContactForm form, BindingResult bindingResult) {
        if (bindingResult.hasErrors()) {
            return "site/home/contact";
        }

        String adminEmail = getSetting("general_admin_email");
        if (adminEmail != null && !adminEmail.isEmpty()) {
            helperTrait.sendEmail(adminEmail, "Contact Form Message", form.getMessage(),
                    new String[]{form.getEmail(), form.getName()});
        }

        return "redirect:/contact?success=true";
    }

    @GetMapping("/privacy")
    public String privacy(Model model) {
        model.addAttribute("title", "Privacy Policy");
        model.addAttribute("content", getSetting("info_privacy"));
        return "site/home/info";
    }

    @GetMapping("/terms")
    public String terms(Model model) {
        model.addAttribute("title", "Terms & Conditions");
        model.addAttribute("content", getSetting("info_terms"));
        return "site/home/info";
    }

    @GetMapping("/instructors")
    public String instructors(Model model) {
        List<Admin> admins = Admin.findPublicOrderByName();
        model.addAttribute("admins", admins);
        return "site/home/instructors";
    }

    @GetMapping("/instructor/{id}")
    public String instructor(@PathVariable Long id, Model model) {
        Admin admin = Admin.findById(id);
        if (admin == null || !admin.isPublic()) {
            throw new ResponseStatusException(HttpStatus.UNAUTHORIZED);
        }
        model.addAttribute("admin", admin);
        return "site/home/instructor";
    }

    @GetMapping("/cron/{method}")
    public void cron(@PathVariable String method, HttpServletRequest request) {
        // Protect IP
        String ip = getSetting("general_site_ip");
        if (ip != null && !ip.trim().equals(request.getRemoteAddr())) {
            throw new ResponseStatusException(HttpStatus.UNAUTHORIZED, "Unauthorized access");
        }

        // Process only at specified hour
        LocalDateTime now = LocalDateTime.now();
        int cronHour = Integer.parseInt(getSetting("general_reminder_hour"));
        if (now.getHour() != cronHour) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Invalid time for cron");
        }

        CronJobs jobs = new CronJobs();
        try {
            jobs.getClass().getMethod(method).invoke(jobs);
        } catch (Exception e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "Error executing cron job", e);
        }
    }

    private String getSetting(String key) {
        // Implement this method to get settings from your configuration
        return "";
    }

    private String getCaptchaSrc() {
        // Implement this method to generate captcha URL
        return "";
    }
}

