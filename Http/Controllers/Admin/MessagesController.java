package com.example.admin.controllers;

import com.example.models.EmailTemplate;
import com.example.models.SmsTemplate;
import com.example.lib.HelperTrait;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

@Controller
@RequestMapping("/admin/messages")
public class MessagesController {

    private final EmailTemplateRepository emailTemplateRepository;
    private final SmsTemplateRepository smsTemplateRepository;

    public MessagesController(EmailTemplateRepository emailTemplateRepository, SmsTemplateRepository smsTemplateRepository) {
        this.emailTemplateRepository = emailTemplateRepository;
        this.smsTemplateRepository = smsTemplateRepository;
    }

    @GetMapping("/emails")
    public String emails(Model model) {
        model.addAttribute("pageTitle", "Email Notifications");
        Page<EmailTemplate> templates = emailTemplateRepository.findAll(PageRequest.of(0, 10));
        model.addAttribute("templates", templates);
        return "admin/messages/emails";
    }

    @GetMapping("/editemail/{id}")
    public String editEmail(@PathVariable Long id, Model model) {
        EmailTemplate emailTemplate = emailTemplateRepository.findById(id).orElseThrow();
        model.addAttribute("pageTitle", "Edit Email: " + "e-template-name-" + id);
        model.addAttribute("template", emailTemplate);
        return "admin/messages/edit-email";
    }

    @PostMapping("/editemail/{id}")
    public String updateEmail(@PathVariable Long id, @RequestParam String message, @RequestParam String subject, RedirectAttributes redirectAttributes) {
        EmailTemplate emailTemplate = emailTemplateRepository.findById(id).orElseThrow();
        if (message != null && !message.isEmpty() && subject != null && !subject.isEmpty()) {
            emailTemplate.setMessage(message);
            emailTemplate.setSubject(subject);
            emailTemplateRepository.save(emailTemplate);
            redirectAttributes.addFlashAttribute("flashMessage", "Changes Saved!");
            return "redirect:/admin/messages/emails";
        }
        return "redirect:/admin/messages/editemail/" + id;
    }

    @PostMapping("/resetemail")
    public String resetEmail(@RequestParam Long id, RedirectAttributes redirectAttributes) {
        EmailTemplate template = emailTemplateRepository.findById(id).orElseThrow();
        template.setMessage(template.getDefault());
        template.setSubject(template.getDefaultSubject());
        emailTemplateRepository.save(template);
        redirectAttributes.addFlashAttribute("flashMessage", "Email reset completed");
        return "redirect:/admin/messages/emails";
    }

    @GetMapping("/sms")
    public String sms(Model model) {
        model.addAttribute("pageTitle", "SMS Notifications");
        Page<SmsTemplate> templates = smsTemplateRepository.findAll(PageRequest.of(0, 10));
        model.addAttribute("templates", templates);
        return "admin/messages/sms";
    }

    @GetMapping("/editsms/{id}")
    public String editSms(@PathVariable Long id, Model model) {
        SmsTemplate smsTemplate = smsTemplateRepository.findById(id).orElseThrow();
        model.addAttribute("pageTitle", "Edit SMS: " + "s-template-name-" + id);
        model.addAttribute("template", smsTemplate);
        return "admin/messages/edit-sms";
    }

    @PostMapping("/editsms/{id}")
    public String updateSms(@PathVariable Long id, @RequestParam String message, RedirectAttributes redirectAttributes) {
        SmsTemplate smsTemplate = smsTemplateRepository.findById(id).orElseThrow();
        if (message != null && !message.isEmpty()) {
            smsTemplate.setMessage(message);
            smsTemplateRepository.save(smsTemplate);
            redirectAttributes.addFlashAttribute("flashMessage", "Changes Saved!");
            return "redirect:/admin/messages/sms";
        }
        return "redirect:/admin/messages/editsms/" + id;
    }

    @PostMapping("/resetsms")
    public String resetSms(@RequestParam Long id, RedirectAttributes redirectAttributes) {
        SmsTemplate template = smsTemplateRepository.findById(id).orElseThrow();
        template.setMessage(template.getDefault());
        smsTemplateRepository.save(template);
        redirectAttributes.addFlashAttribute("flashMessage", "SMS reset completed");
        return "redirect:/admin/messages/sms";
    }
}

