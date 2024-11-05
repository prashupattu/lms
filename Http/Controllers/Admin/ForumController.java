package App.Http.Controllers.Admin;

import App.Course;
import App.ForumParticipant;
import App.ForumPost;
import App.ForumTopic;
import App.Http.Controllers.Controller;
import App.Lib.BaseForm;
import App.Lib.ForumTrait;
import App.Lib.HelperTrait;
import App.V2.Model.ForumParticipantTable;
import App.V2.Model.ForumTopicTable;
import App.V2.Model.SessionInstructorTable;
import App.V2.Model.SessionTable;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.ModelAndView;

import javax.servlet.http.HttpServletRequest;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/admin/forum")
public class ForumController extends Controller {
    private final ForumTrait forumTrait;
    private final HelperTrait helperTrait;

    public ForumController(ForumTrait forumTrait, HelperTrait helperTrait) {
        this.forumTrait = forumTrait;
        this.helperTrait = helperTrait;
    }

    @GetMapping
    public ModelAndView index(HttpServletRequest request) {
        ForumTopicTable table = new ForumTopicTable();
        String sessionId = request.getParameter("course_id");

        var paginator = table.getTopicsForAdmin(this.getAdmin().admin.id, sessionId);
        paginator.setCurrentPageNumber(Integer.parseInt(request.getParameter("page", "1")));
        paginator.setItemCountPerPage(30);

        String pageTitle = __lang("Student Forum") + ": " + table.total + " " + __lang("topics");
        if (sessionId != null && !sessionId.isEmpty()) {
            pageTitle = __lang("Forum Topics for") + " " + Course.find(sessionId).name + " (" + table.total + ")";
        }

        BaseForm form = this.adminForumForm();
        form.get("course_id").setValue(sessionId);
        form.get("course_id").setAttribute("style", "min-width:150px");

        Map<String, Object> model = new HashMap<>();
        model.put("topics", paginator);
        model.put("pageTitle", pageTitle);
        model.put("select", form.get("course_id"));
        model.put("form", form);

        return new ModelAndView("admin/forum/index", model);
    }

    @PostMapping("/addtopic")
    public ModelAndView addTopic(HttpServletRequest request) {
        BaseForm form = this.adminForumForm();

        if ("POST".equalsIgnoreCase(request.getMethod())) {
            Map<String, String[]> formData = request.getParameterMap();
            form.setData(formData);
            if (form.isValid()) {
                Map<String, String> data = form.getData();
                ForumTopic forumTopic = ForumTopic.create(new HashMap<>() {{
                    put("title", data.get("topic_title"));
                    put("user_id", String.valueOf(this.getAdminId()));
                    put("course_id", data.get("course_id"));
                }});

                String message = this.saveInlineImages(data.get("message"), this.getBaseUrl());
                message = clean(message);

                ForumPost post = ForumPost.create(new HashMap<>() {{
                    put("forum_topic_id", String.valueOf(forumTopic.id));
                    put("message", message);
                    put("user_id", String.valueOf(this.getAdminId()));
                }});

                ForumParticipantTable fpTable = new ForumParticipantTable();
                fpTable.updateParticipant(forumTopic.id, this.getAdminId());

                return adminRedirect(new HashMap<>() {{
                    put("controller", "forum");
                    put("action", "topic");
                    put("id", forumTopic.id);
                }});
            } else {
                this.data.put("message", this.getFormErrors(form));
            }
        }

        this.data.put("pageTitle", __lang("Add Topic"));
        this.data.put("form", form);
        this.data.put("customCrumbs", new HashMap<>() {{
            put(route("admin.dashboard"), __lang("Dashboard"));
            put(adminUrl(new HashMap<>() {{
                put("controller", "forum");
                put("action", "index");
            }}), __lang("Student Forum"));
            put("#", __lang("Add Topic"));
        }});
        return new ModelAndView("admin/forum/addtopic", this.data);
    }

    @GetMapping("/topic/{id}")
    public ModelAndView topic(HttpServletRequest request, @PathVariable String id) {
        this.data.put("id", id);
        ForumTopic forumTopic = ForumTopic.find(id);
        String sessionId = forumTopic.course.id;
        this.validateAccess(sessionId);

        this.data.put("posts", forumTopic.forumPosts().paginate(70));

        this.data.put("customCrumbs", new HashMap<>() {{
            put(route("admin.dashboard"), __lang("Dashboard"));
            put(adminUrl(new HashMap<>() {{
                put("controller", "forum");
                put("action", "index");
            }}), __lang("Student Forum"));
            put("#", __lang("Forum Topic"));
        }});

        Checkbox checkbox = new Checkbox("notify");
        checkbox.setAttribute("id", "notify");
        checkbox.setCheckedValue(1);
        ForumParticipant participant = ForumParticipant.where("forum_topic_id", id).where("user_id", this.getAdminId()).first();
        this.data.put("checked", participant != null && participant.notify == 1);
        this.data.put("checkbox", checkbox);
        this.data.put("forumTopic", forumTopic);
        this.data.put("pageTitle", forumTopic.title);

        return new ModelAndView("admin/forum/topic", this.data);
    }

    @PostMapping("/reply/{id}")
    public ResponseEntity<?> reply(HttpServletRequest request, @PathVariable String id) {
        // Set post_max_size to 5M equivalent in Java
        // Handle reply logic
        ForumTopic topic = ForumTopic.find(id);
        this.validateAccess(topic.course_id);
        if ("POST".equalsIgnoreCase(request.getMethod())) {
            Map<String, String[]> post = request.getParameterMap();
            String message = post.get("message")[0];

            if (message == null || message.isEmpty()) {
                session().flash("flash_message", __lang("Please enter a message"));
                return ResponseEntity.badRequest().body("Please enter a message");
            }
            message = this.saveInlineImages(message, this.getBaseUrl());
            message = clean(message);

            ForumPost reply = new ForumPost();
            reply.forum_topic_id = id;
            reply.message = message;
            reply.user_id = this.getAdminId();
            if (post.containsKey("post_reply_id")) {
                reply.post_reply_id = post.get("post_reply_id")[0];
            }
            try {
                reply.save();
            } catch (Exception ex) {
                session().flash("flash_message", __lang("forum-content-error"));
                flashMessage(post);
                return ResponseEntity.badRequest().body("forum-content-error");
            }

            ForumParticipantTable fpTable = new ForumParticipantTable();
            fpTable.updateParticipant(id, this.getAdminId());
            this.notifyParticipants(id);
            session().flash("flash_message", __lang("Reply saved!"));
        }

        return ResponseEntity.ok().body("Reply saved!");
    }

    @PostMapping("/notifications/{id}")
    public ResponseEntity<?> notifications(HttpServletRequest request, @PathVariable String id) {
        ForumTopic topic = ForumTopic.find(id);
        this.validateAccess(topic.course_id);

        String notify = request.getParameter("notify");
        ForumParticipantTable table = new ForumParticipantTable();
        table.updateParticipant(id, this.getAdminId(), notify);
        return ResponseEntity.ok("true");
    }

    @PostMapping("/deletetopic/{id}")
    public ResponseEntity<?> deleteTopic(HttpServletRequest request, @PathVariable String id) {
        ForumTopic forumTopic = ForumTopic.findOrFail(id);
        this.validateAccess(forumTopic.course_id);
        forumTopic.delete();
        session().flash("flash_message", __lang("Topic deleted"));
        return ResponseEntity.ok().body("Topic deleted");
    }

    private void validateAccess(String sessionId) {
        // Check if user has global access
        if (GLOBAL_ACCESS) {
            return;
        }

        // Check if is owner of session
        if (Course.find(sessionId).admin_id == this.getAdmin().admin.id) {
            return;
        }
        SessionInstructorTable sessionInstructorTable = new SessionInstructorTable();
        // Check if is instructor
        if (sessionInstructorTable.isInstructor(sessionId, this.getAdminId())) {
            return;
        }

        flashMessage(__lang("no-forum-access"));
        adminRedirect(new HashMap<>() {{
            put("controller", "forum");
            put("action", "index");
        }});
    }

    private BaseForm adminForumForm() {
        BaseForm form = new BaseForm();
        form.createText("topic_title", __lang("Topic"), true, null, null, __lang("enter-thread-topic"));
        form.createTextArea("message", __lang("Post"), true, null, __lang("enter-first-post"));
        form.get("message").setAttribute("class", "form-control summernote");

        SessionTable sessionTable = new SessionTable();
        var rowset = sessionTable.getLimitedRecords(5000);

        var options = new ArrayList<Map<String, Object>>();
        var log = new HashMap<String, Boolean>();
        for (var row : rowset) {
            options.add(new HashMap<>() {{
                put("attributes", new HashMap<>() {{
                    put("data-type", row.type);
                }});
                put("value", row.id);
                put("label", row.name + " (" + row.id + ")");
            }});
            log.put(row.id, true);
        }
        SessionInstructorTable sessionInstructorTable = new SessionInstructorTable();
        rowset = sessionInstructorTable.getAccountRecords(this.getAdminId());
        for (var row : rowset) {
            if (log.containsKey(row.course_id)) {
                continue;
            }
            options.add(new HashMap<>() {{
                put("attributes", new HashMap<>() {{
                    put("data-type", row.type);
                }});
                put("value", row.id);
                put("label", row.name + " (" + row.id + ")");
            }});
        }

        Select sessionId = new Select("course_id");
        sessionId.setLabel(__lang("Session/Course"));
        sessionId.setAttribute("class", "form-control select2");
        sessionId.setAttribute("id", "course_id");
        sessionId.setValueOptions(options);

        form.add(sessionId);
        form.setInputFilter(this.adminForumFilter());
        return form;
    }

    private BaseFilter adminForumFilter() {
        BaseFilter filter = this.forumTopicFilter();
        filter.add(new HashMap<>() {{
            put("name", "course_id");
            put("required", true);
            put("validators", new ArrayList<>() {{
                add(new HashMap<>() {{
                    put("name", "NotEmpty");
                }});
            }});
        }});
        return filter;
    }
}

