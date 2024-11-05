package App.Http.Controllers.Auth;

import App.Http.Controllers.Controller;
import App.Providers.RouteServiceProvider;
import App.StudentField;
import App.User;
import Hybridauth.Hybridauth;
import Illuminate.Foundation.Auth.AuthenticatesUsers;
import Illuminate.Http.Request;
import Illuminate.Support.Carbon;
import Illuminate.Support.Facades.Auth;
import Illuminate.Support.Facades.Hash;
import Illuminate.Support.Facades.URL;
import Illuminate.Support.Str;

import java.util.HashMap;
import java.util.Map;

public class LoginController extends Controller {

    protected String redirectTo = RouteServiceProvider.HOME;

    public LoginController() {
        this.middleware("guest").except("logout");
    }

    protected String redirectTo() {
        String link = getRedirectLink();
        if (link != null && !link.isEmpty()) {
            return link;
        }

        User user = Auth.user();
        if (user.role_id == 1) {
            return route("admin.dashboard");
        } else if (user.role_id == 2) {
            return route("student.dashboard");
        }
        return null;
    }

    public String showLoginForm(Request request) {
        String url = URL.previous();
        Map<String, String> urlComponents = parseUrl(url);

        if (urlComponents.containsKey("query")) {
            Map<String, String> params = parseQuery(urlComponents.get("query"));
            if (params.containsKey("login-token") && User.where("login_token", params.get("login-token"))
                    .where("login_token_expires", ">=", Carbon.now().toDateString()).first() != null) {
                User user = User.where("login_token", params.get("login-token")).first();
                Auth.login(user);
                return redirect(url);
            }
        }

        boolean enableRegistration = true;
        if (setting("regis_enable_registration") == null) {
            enableRegistration = false;
        }

        return tview("auth.login", enableRegistration);
    }

    public String social(String network, Request request) {
        Map<String, Object> config = new HashMap<>();
        config.put("callback", route("social.login", network));

        if (setting("social_enable_facebook") == 1) {
            Map<String, Object> facebookConfig = new HashMap<>();
            facebookConfig.put("enabled", true);
            Map<String, String> keys = new HashMap<>();
            keys.put("id", trim(setting("social_facebook_app_id")));
            keys.put("secret", trim(setting("social_facebook_secret")));
            facebookConfig.put("keys", keys);
            facebookConfig.put("scope", "email");
            facebookConfig.put("trustForwarded", false);
            config.put("providers.Facebook", facebookConfig);
        }

        if (setting("social_enable_google") == 1) {
            Map<String, Object> googleConfig = new HashMap<>();
            googleConfig.put("enabled", true);
            Map<String, String> keys = new HashMap<>();
            keys.put("id", trim(setting("social_google_id")));
            keys.put("secret", trim(setting("social_google_secret")));
            googleConfig.put("keys", keys);
            config.put("providers.Google", googleConfig);
        }

        config.put("debug_mode", true);
        config.put("debug_file", "hybridlog.txt");

        try {
            Hybridauth hybridauth = new Hybridauth(config);
            AuthSession authSession = hybridauth.authenticate(network);
            UserProfile userProfile = authSession.getUserProfile();

            String email = userProfile.email;
            User user = User.where("email", email).first();
            if (user != null) {
                Auth.login(user);
                return redirect().route("home");
            } else if (setting("regis_enable_registration") == null) {
                return redirect().route("login").with("flash_message", __("default.registration-disabled"));
            }

            UserClass userClass = new UserClass();
            userClass.firstName = userProfile.firstName;
            userClass.lastName = userProfile.lastName;
            userClass.email = userProfile.email;
            userClass.phone = userProfile.phone;

            request.session().put("social_user", serialize(userClass));

        } catch (Exception ex) {
            return back().with("flash_message", ex.getMessage());
        }

        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        return tview("auth.social", userProfile, fields, userClass);
    }

    public String registerSocial(Request request) {
        Map<String, String> rules = new HashMap<>();
        rules.put("mobile_number", "required");
        List<StudentField> fields = StudentField.orderBy("sort_order").where("enabled", 1).get();
        for (StudentField field : fields) {
            if (field.type.equals("file")) {
                String required = "";
                if (field.required == 1) {
                    required = "required|";
                }
                rules.put("field_" + field.id, "nullable|" + required + "max:" + config("app.upload_size") + "|mimes:" + config("app.upload_files"));
            } else if (field.required == 1) {
                rules.put("field_" + field.id, "required");
            }
        }

        this.validate(request, rules);

        String socialUser = session("social_user");
        if (socialUser == null) {
            return redirect().route("login").with("flash_message", __("default.invalid-login"));
        }

        UserClass socialUserClass = (UserClass) unserialize(socialUser);
        Map<String, String> data = request.all();
        String password = Str.random(10);

        data.put("name", socialUserClass.firstName);
        data.put("last_name", socialUserClass.lastName);
        data.put("email", socialUserClass.email);
        data.put("password", Hash.make(password));
        data.put("role_id", "2");

        User user = User.create(data);
        user.student.create(Map.of("mobile_number", request.mobile_number));

        Map<Integer, Map<String, String>> customValues = new HashMap<>();
        for (StudentField field : fields) {
            if (data.containsKey("field_" + field.id)) {
                if (field.type.equals("file")) {
                    if (request.hasFile("field_" + field.id)) {
                        String name = request.file("field_" + field.id).getOriginalFilename();
                        String extension = request.file("field_" + field.id).getExtension();
                        name = name.replace("." + extension, "");
                        name = user.id + "_" + System.currentTimeMillis() + "_" + safeUrl(name) + "." + extension;
                        String path = request.file("field_" + field.id).storeAs(STUDENT_FILES, name, "public_uploads");
                        String file = UPLOAD_PATH + "/" + path;
                        customValues.put(field.id, Map.of("value", file));
                    }
                } else {
                    customValues.put(field.id, Map.of("value", data.get("field_" + field.id)));
                }
            }
        }

        user.student.studentFields().sync(customValues);
        return redirect(this.redirectPath());
    }
}

