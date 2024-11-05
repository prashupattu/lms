package App.Http.Controllers.Auth;

import App.Http.Controllers.Controller;
import App.Lib.HelperTrait;
import App.PendingStudent;
import App.Providers.RouteServiceProvider;
import App.StudentField;
import App.User;
import Illuminate.Auth.Events.Registered;
import Illuminate.Foundation.Auth.RegistersUsers;
import Illuminate.Http.Request;
import Illuminate.Http.Response;
import Illuminate.Support.Facades.Auth;
import Illuminate.Support.Facades.Hash;
import Illuminate.Support.Facades.Validator;
import Illuminate.Support.Str;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class RegisterController extends Controller {
    protected String redirectTo = RouteServiceProvider.HOME;

    public RegisterController() {
        this.middleware("guest");
    }

    protected Validator validator(Map<String, String> data) {
        Map<String, List<String>> rules = new HashMap<>();
        rules.put("name", List.of("required", "string", "max:255"));
        rules.put("last_name", List.of("required", "string", "max:255"));
        rules.put("email", List.of("required", "string", "email", "max:255", "unique:users"));
        rules.put("password", List.of("required", "string", "min:8", "confirmed"));
        rules.put("mobile_number", List.of("required", "string", "max:255"));
        rules.put("agree", List.of("required"));

        if (setting("regis_captcha_type").equals("image")) {
            rules.put("captcha", List.of("required", "captcha"));
        }
        if (setting("regis_captcha_type").equals("google")) {
            rules.put("captcha_token", List.of("required"));
        }

        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        for (StudentField field : fields) {
            if (field.getType().equals("file")) {
                String required = "";
                if (field.getRequired() == 1) {
                    required = "required|";
                }
                rules.put("field_" + field.getId(), List.of("nullable", required + "max:" + config("app.upload_size") + "|mimes:" + config("app.upload_files")));
            } else if (field.getRequired() == 1) {
                rules.put("field_" + field.getId(), List.of("required"));
            }
        }

        return Validator.make(data, rules);
    }

    public Response register(Request request) {
        boolean enableRegistration = !setting("regis_enable_registration").isEmpty();
        if (!enableRegistration) {
            abort(401);
        }

        if (setting("regis_captcha_type").equals("google")) {
            String recaptcha_secret = setting("regis_recaptcha_secret");
            String recaptcha_response = request.get("captcha_token");

            // Implement HTTP request to Google reCAPTCHA API
            // ...

            // Handle response
            // ...
        }

        this.validator(request.all()).validate();

        if (setting("regis_confirm_email") == 1) {
            String hash;
            do {
                hash = Str.random(30);
            } while (PendingStudent.where("hash", hash).first() != null);

            Map<String, String> formData = request.getAll();
            formData.put("role_id", "2");

            PendingStudent pendingStudent = PendingStudent.create(new HashMap<>() {{
                put("role_id", 2);
                put("data", serialize(formData));
                put("hash", hash);
            }});

            List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
            for (StudentField field : fields) {
                if (request.hasFile("field_" + field.getId())) {
                    // Handle file upload
                    // ...
                }
            }

            String link = route("confirm.student", new HashMap<>() {{
                put("hash", hash);
            }}, true);
            this.sendEmail(request.get("email"), __("default.confirm-your-email"), __("default.confirm-email-mail", new HashMap<>() {{
                put("link", link);
            }}));
            return redirect().route("register.confirm");
        }

        event(new Registered(this.create(request.all())));

        this.guard().login(user);

        if (response = this.registered(request, user)) {
            return response;
        }

        return request.wantsJson() ? new Response("", 201) : redirect(this.redirectPath());
    }

    protected User create(Map<String, String> data) {
        User user = User.create(new HashMap<>() {{
            put("name", data.get("name"));
            put("last_name", data.get("last_name"));
            put("email", data.get("email"));
            put("password", Hash.make(data.get("password")));
            put("role_id", 2);
        }});

        user.student().create(new HashMap<>() {{
            put("mobile_number", data.get("mobile_number"));
        }});

        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        Map<Integer, Map<String, String>> customValues = new HashMap<>();
        for (StudentField field : fields) {
            if (data.containsKey("field_" + field.getId())) {
                if (field.getType().equals("file")) {
                    if (request.hasFile("field_" + field.getId())) {
                        // Handle file upload
                        // ...
                    }
                } else {
                    customValues.put(field.getId(), new HashMap<>() {{
                        put("value", data.get("field_" + field.getId()));
                    }});
                }
            }
        }

        user.student().studentFields().sync(customValues);

        String message = __("mails.new-account", new HashMap<>() {{
            put("siteName", setting("general_site_name"));
            put("email", data.get("email"));
            put("password", data.get("password"));
            put("link", url("/login"));
        }});

        if (!setting("regis_email_message").isEmpty()) {
            message += "<br/>" + setting("regis_email_message");
        }

        String subject = __("mails.new-account-subj", new HashMap<>() {{
            put("siteName", setting("general_site_name"));
        }});
        this.sendEmail(data.get("email"), subject, message);

        if (setting("regis_signup_alert") == 1) {
            this.notifyAdmins(__lang("New registration"), data.get("name") + " " + data.get("last_name") + " " + __lang("just registered"));
        }

        return user;
    }

    public Response showRegistrationForm() {
        boolean enableRegistration = !setting("regis_enable_registration").isEmpty();
        if (!enableRegistration) {
            return abort(401);
        }
        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        return tview("auth.register", new HashMap<>() {{
            put("enableRegistration", enableRegistration);
            put("fields", fields);
        }});
    }

    public Response confirmStudent(String hash) {
        PendingStudent pendingStudent = PendingStudent.where("hash", hash).first();
        if (pendingStudent == null) {
            abort(404);
        }

        Map<String, String> requestData = unserialize(pendingStudent.getData());
        String password = requestData.get("password");
        requestData.put("password", Hash.make(password));

        // Handle profile picture
        // ...

        User user = User.create(requestData);
        user.student().create(new HashMap<>() {{
            put("mobile_number", requestData.get("mobile_number"));
        }});

        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        Map<Integer, Map<String, String>> customValues = new HashMap<>();
        for (StudentField field : fields) {
            if (field.getType().equals("file")) {
                // Handle pending files
                // ...
            } else if (requestData.containsKey("field_" + field.getId())) {
                customValues.put(field.getId(), new HashMap<>() {{
                    put("value", requestData.get("field_" + field.getId()));
                }});
            }
        }

        user.student().studentFields().sync(customValues);
        pendingStudent.delete();

        String message = __("mails.new-account", new HashMap<>() {{
            put("siteName", setting("general_site_name"));
            put("email", requestData.get("email"));
            put("password", password);
            put("link", url("/login"));
        }});
        String subject = __("mails.new-account-subj", new HashMap<>() {{
            put("siteName", setting("general_site_name"));
        }});
        this.sendEmail(requestData.get("email"), subject, message);

        Auth.login(user, true);
        return redirect().route("home");
    }

    public Response confirm() {
        return tview("auth.confirm");
    }

    public void newCaptcha() {
        System.out.println(captcha_img());
    }

    public String redirectPath() {
        String link = getRedirectLink();
        if (!link.isEmpty()) {
            return link;
        }

        if (this.redirectTo() != null) {
            return this.redirectTo();
        }

        return this.redirectTo != null ? this.redirectTo : "/home";
    }
}

