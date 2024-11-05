import org.springframework.web.bind.annotation.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.ui.Model;
import org.springframework.stereotype.Controller;
import org.springframework.web.servlet.ModelAndView;

import java.util.HashMap;
import java.util.Map;

@Controller
@RequestMapping("/student/forum")
public class ForumController {

    @Autowired
    private StudentSessionTable studentSessionTable;

    @Autowired
    private CourseService courseService;

    @Autowired
    private ForumParticipantTable forumParticipantTable;

    @Autowired
    private ForumPostService forumPostService;

    @Autowired
    private ForumTopicService forumTopicService;

    @Autowired
    private AuthService authService;

    @GetMapping
    public ModelAndView index(@RequestParam(value = "page", defaultValue = "1") int page) {
        int studentId = authService.getId();
        Paginator paginator = studentSessionTable.getStudentForumRecords(true, studentId);
        paginator.setCurrentPageNumber(page);
        paginator.setItemCountPerPage(30);

        Map<String, Object> model = new HashMap<>();
        model.put("paginator", paginator);
        model.put("pageTitle", "Student Forum");

        return new ModelAndView("student/forum/index", model);
    }

    @GetMapping("/topics/{id}")
    public ModelAndView topics(@PathVariable Long id, @RequestParam(required = false) Long lectureId) {
        validateAccess(id);
        Course session = courseService.findById(id);
        List<ForumTopic> topics;

        if (lectureId != null) {
            topics = session.getForumTopics().where("lecture_id", lectureId).orderBy("id", "desc").paginate(20);
        } else {
            topics = session.getForumTopics().orderBy("id", "desc").paginate(20);
        }

        Map<String, Object> model = new HashMap<>();
        model.put("lecture", lectureService.findById(lectureId));
        model.put("pageTitle", "Forum Topics in " + session.getName());
        model.put("id", id);
        model.put("topics", topics);
        model.put("message", topics.isEmpty() ? "no-topics" : null);
        model.put("student", authService.getStudent());

        return new ModelAndView("student/forum/topics", model);
    }

    @PostMapping("/addtopic/{id}")
    public ModelAndView addTopic(@PathVariable Long id, @ModelAttribute ForumTopicForm form) {
        validateAccess(id);
        Course course = courseService.findById(id);

        if (form.isPost()) {
            if (form.isValid()) {
                ForumTopic forumTopic = course.getForumTopics().create(form.getTopicTitle(), authService.getUserId());
                String message = saveInlineImages(form.getMessage(), getBaseUrl());
                message = clean(message);

                forumPostService.createPost(forumTopic.getId(), message, authService.getUserId());
                forumParticipantTable.updateParticipant(forumTopic.getId(), authService.getUserId());

                return new ModelAndView("redirect:/student/forum/topic/" + forumTopic.getId());
            } else {
                model.put("message", getFormErrors(form));
            }
        }

        Map<String, Object> model = new HashMap<>();
        model.put("pageTitle", "Add Topic: " + course.getName());
        model.put("form", form);
        model.put("customCrumbs", getCustomCrumbs(id));

        return new ModelAndView("student/forum/addtopic", model);
    }

    @GetMapping("/topic/{id}")
    public ModelAndView topic(@PathVariable Long id) {
        ForumTopic forumTopic = forumTopicService.findById(id);
        validateAccess(forumTopic.getCourseId());

        Map<String, Object> model = new HashMap<>();
        model.put("id", id);
        model.put("posts", forumPostService.getPostsByTopicId(id));
        model.put("pageTitle", forumTopic.getCourse().getName());
        model.put("customCrumbs", getCustomCrumbs(forumTopic.getCourseId()));

        Checkbox checkbox = new Checkbox("notify");
        checkbox.setAttribute("id", "notify");
        checkbox.setCheckedValue(1);
        model.put("checked", forumParticipantTable.isParticipantNotified(id, authService.getUserId()));
        model.put("checkbox", checkbox);
        model.put("forumTopic", forumTopic);

        return new ModelAndView("student/forum/topic", model);
    }

    @PostMapping("/reply/{id}")
    public ModelAndView reply(@PathVariable Long id, @RequestParam String message) {
        ForumTopic topic = forumTopicService.findById(id);
        validateAccess(topic.getCourseId());

        if (message.isEmpty()) {
            flashMessage("Please enter a message");
            return new ModelAndView("redirect:/student/forum/topic/" + id);
        }

        message = saveInlineImages(message, getBaseUrl());
        message = clean(message);

        forumPostService.createReply(id, message, authService.getUserId());
        forumParticipantTable.updateParticipant(id, authService.getUserId());
        notifyParticipants(id);
        flashMessage("Reply saved!");

        return new ModelAndView("redirect:/student/forum/topic/" + id);
    }

    @PostMapping("/notifications/{id}")
    public ResponseEntity<String> notifications(@PathVariable Long id, @RequestParam boolean notify) {
        ForumTopic topic = forumTopicService.findById(id);
        validateAccess(topic.getCourseId());

        forumParticipantTable.updateParticipant(id, authService.getUserId(), notify);
        return ResponseEntity.ok("true");
    }

    @PostMapping("/deletetopic/{id}")
    public ModelAndView deleteTopic(@PathVariable Long id) {
        ForumTopic forumTopic = forumTopicService.findById(id);
        if (forumTopic.getUserId().equals(authService.getUserId())) {
            forumTopicService.delete(forumTopic);
            flashMessage("Topic deleted!");
        }

        return new ModelAndView("redirect:/student/forum");
    }

    private void validateAccess(Long sessionId) {
        if (!studentSessionTable.isEnrolled(authService.getId(), sessionId)) {
            flashMessage("forum-unavailable");
            throw new RedirectException("redirect:/student/forum");
        }
    }

    private Map<String, String> getCustomCrumbs(Long id) {
        Map<String, String> crumbs = new HashMap<>();
        crumbs.put("/student/dashboard", "dashboard");
        crumbs.put("/student/forum", "Student Forum");
        crumbs.put("/student/forum/topics/" + id, "Forum Topics");
        crumbs.put("#", "Add Topic");
        return crumbs;
    }
}

