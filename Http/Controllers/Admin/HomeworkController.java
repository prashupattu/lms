package com.example.admin.controllers;

import com.example.admin.forms.HomeworkFilter;
import com.example.admin.forms.HomeworkForm;
import com.example.admin.models.HomeworkTable;
import com.example.lib.HelperTrait;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.validation.Valid;

@Controller
@RequestMapping("/admin/homework")
public class HomeworkController {

    @Autowired
    private HomeworkTable homeworkTable;

    @Autowired
    private HelperTrait helperTrait;

    @GetMapping
    public String index(@RequestParam(defaultValue = "1") int page, Model model) {
        Page<?> paginator = homeworkTable.getPaginatedRecords(true, PageRequest.of(page - 1, 30));
        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Revision Notes");
        return "admin/homework/index";
    }

    @GetMapping("/add")
    public String addForm(Model model) {
        model.addAttribute("form", new HomeworkForm());
        model.addAttribute("pageTitle", "Add Note");
        model.addAttribute("action", "add");
        return "admin/homework/add";
    }

    @PostMapping("/add")
    public String add(@Valid @ModelAttribute("form") HomeworkForm form, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/homework/add";
        }

        HomeworkFilter filter = new HomeworkFilter();
        // Apply filter logic here

        form.setLessonId(Integer.parseInt(form.getLessonId().replace("string:", "")));
        homeworkTable.saveRecord(form);

        if (form.isNotify()) {
            String subject = "New revision note";
            String message = String.format("revision-note-mail", form.getTitle(), form.getDescription());
            String sms = String.format("revision-note-sms", form.getTitle());
            helperTrait.notifySessionStudents(form.getCourseId(), subject, message, true, sms);
        }

        redirectAttributes.addFlashAttribute("flash_message", "Record Added!");
        return "redirect:/admin/homework";
    }

    @GetMapping("/edit/{id}")
    public String editForm(@PathVariable int id, Model model) {
        HomeworkForm form = homeworkTable.getRecord(id);
        model.addAttribute("form", form);
        model.addAttribute("pageTitle", "Edit Note");
        model.addAttribute("action", "edit");
        model.addAttribute("id", id);
        return "admin/homework/add";
    }

    @PostMapping("/edit/{id}")
    public String edit(@PathVariable int id, @Valid @ModelAttribute("form") HomeworkForm form, BindingResult result, RedirectAttributes redirectAttributes) {
        if (result.hasErrors()) {
            return "admin/homework/add";
        }

        HomeworkFilter filter = new HomeworkFilter();
        // Apply filter logic here

        form.setLessonId(Integer.parseInt(form.getLessonId().replace("string:", "")));
        form.setId(id);
        homeworkTable.saveRecord(form);

        if (form.isNotify()) {
            String subject = "Updated revision note";
            String message = String.format("revision-note-updated-mail", form.getTitle());
            helperTrait.notifySessionStudents(form.getCourseId(), subject, message);
        }

        redirectAttributes.addFlashAttribute("flash_message", "Changes Saved!");
        return "redirect:/admin/homework";
    }

    @PostMapping("/delete/{id}")
    public String delete(@PathVariable int id, RedirectAttributes redirectAttributes) {
        homeworkTable.deleteRecord(id);
        redirectAttributes.addFlashAttribute("flash_message", "Record deleted");
        return "redirect:/admin/homework";
    }
}

