package com.example.controllers.admin;

import com.example.controllers.Controller;
import com.example.lib.HelperTrait;
import com.example.v2.model.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.ui.Model;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import javax.servlet.http.HttpServletRequest;
import java.util.List;

@RestController
@RequestMapping("/admin/discuss")
public class DiscussController extends Controller {

    @Autowired
    private DiscussionTable discussionTable;

    @Autowired
    private DiscussionReplyTable discussionReplyTable;

    @Autowired
    private DiscussionAccountTable discussionAccountTable;

    @Autowired
    private StudentTable studentTable;

    @Autowired
    private AccountsTable accountsTable;

    @GetMapping
    public String index(HttpServletRequest request, Model model) {
        String replied = request.getParameter("replied");
        int total = discussionTable.getTotalDiscussions(replied);

        Page<Discussion> paginator = discussionTable.getDiscussRecords(true, replied);
        int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
        paginator = paginator.getContent(PageRequest.of(page - 1, 30));

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "Instructor Chat");
        model.addAttribute("replyTable", discussionReplyTable);
        model.addAttribute("total", total);
        model.addAttribute("accountTable", discussionAccountTable);

        return "admin/discuss/index";
    }

    @PostMapping("/addreply/{id}")
    public String addreply(@PathVariable("id") int id, @RequestParam("reply") String reply) {
        validateDiscussion(id);
        Discussion discussionRow = discussionTable.getRecord(id);

        if (reply != null && !reply.isEmpty()) {
            User user = getAdmin();

            DiscussionReply data = new DiscussionReply();
            data.setDiscussionId(id);
            data.setReply(reply);
            data.setUserId(user.getId());

            int rid = discussionReplyTable.addRecord(data);

            if (rid != 0) {
                discussionTable.update(id, true);
            }

            String name = user.getName() + " " + user.getLastName();
            String subject = "New reply for \"" + discussionRow.getSubject() + "\"";
            String message = String.format("discussion-reply-mail", discussionRow.getSubject(), name, reply);
            String loginLink = getBaseUrl() + "/login";
            String adminLink = getBaseUrl() + "/admin";

            Student student = studentTable.getRecord(discussionRow.getStudentId());
            sendEmail(student.getEmail(), subject, message + loginLink);

            List<User> repliedAdmins = discussionReplyTable.getRepliedAdmins(id);
            for (User admin : repliedAdmins) {
                try {
                    if (admin.getEmail() != null && !admin.getEmail().equals(user.getEmail())) {
                        sendEmail(admin.getEmail(), subject, message + loginLink);
                    }
                } catch (Exception ex) {
                    // Handle exception
                }
            }

            // Flash message implementation depends on your framework
            // session.setAttribute("flash_message", "Reply added successfully");
        } else {
            // session.setAttribute("flash_message", "Submission failed");
        }

        return "redirect:/admin/discuss";
    }

    @GetMapping("/viewdiscussion/{id}")
    public String viewdiscussion(@PathVariable("id") int id, HttpServletRequest request, Model model) {
        validateDiscussion(id);
        Discussion row = discussionTable.getRecord(id);

        Page<DiscussionReply> paginator = discussionReplyTable.getPaginatedRecordsForDiscussion(true, id);
        int page = request.getParameter("page") != null ? Integer.parseInt(request.getParameter("page")) : 1;
        paginator = paginator.getContent(PageRequest.of(page - 1, 30));

        List<DiscussionAccount> accounts = discussionAccountTable.getDiscussionAccounts(id);

        model.addAttribute("paginator", paginator);
        model.addAttribute("pageTitle", "View Chat");
        model.addAttribute("row", row);
        model.addAttribute("studentTable", studentTable);
        model.addAttribute("accountTable", accountsTable);
        model.addAttribute("total", discussionReplyTable.getTotalReplies(id));
        model.addAttribute("accounts", accounts);

        return "admin/discuss/viewdiscussion";
    }

    @DeleteMapping("/delete/{id}")
    public String delete(@PathVariable("id") int id) {
        validateDiscussion(id);

        if (GLOBAL_ACCESS) {
            discussionTable.deleteRecord(id);
        } else {
            discussionAccountTable.deleteAccountRecord(id, ADMIN_ID);
        }

        // Flash message implementation depends on your framework
        // session.setAttribute("flash_message", "Record deleted");

        return "redirect:/admin/discuss";
    }

    private void validateDiscussion(int id) {
        if (!discussionAccountTable.hasDiscussion(ADMIN_ID, id) && !GLOBAL_ACCESS) {
            throw new RuntimeException("Access denied");
        }
    }
}


This Java code is a translation of the provided PHP code, adapted for a Spring Boot application. Here are some key points:

1. The class is annotated with `@RestController` and `@RequestMapping("/admin/discuss")` to define it as a REST controller with a base path.

2. Dependencies are injected using `@Autowired` instead of creating new instances.

3. The `index`, `viewdiscussion`, and `addreply` methods are mapped to GET and POST requests respectively.

4. The `delete` method is mapped to a DELETE request.

5. The `validateDiscussion` method is kept as a private helper method.

6. Some PHP-specific functions like `__lang` and `viewModel` are not directly translated. You may need to implement these separately or use equivalent Java/Spring Boot features.

7. Session flash messages are commented out, as their implementation would depend on your specific setup.

8. Error handling and redirects are simplified. You may want to add more robust error handling.

9. The code assumes you have corresponding model classes (Discussion, DiscussionReply, etc.) and repository interfaces for database operations.

Remember to adjust the package names, import statements, and implement any missing methods or classes according to your project structure and requirements.

