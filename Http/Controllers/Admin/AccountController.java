package App.Http.Controllers.Admin;

import App.Http.Controllers.Controller;
import App.Lib.HelperTrait;
import App.V2.Form.ProfileFilter;
import App.V2.Form.ProfileForm;
import App.V2.Model.AccountsTable;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.ModelAndView;
import org.springframework.validation.annotation.Validated;

import javax.validation.Valid;
import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/admin/account")
public class AccountController extends Controller {
    private final HelperTrait helperTrait = new HelperTrait();

    @PostMapping("/email")
    public ModelAndView email(@Valid @RequestBody Request request) {
        Map<String, Object> output = new HashMap<>();
        User user = this.getAdmin();
        String email = user.getEmail();
        AccountsTable accountsTable = new AccountsTable();

        if (request.getMethod().equals("POST")) {
            Map<String, String> post = request.getBody();
            this.validate(request, new String[]{"email"}, new String[]{"required", "string", "email", "max:255", "unique:users"});
            String newEmail = post.get("email");
            accountsTable.getTableGateway().update(Map.of("email", newEmail), Map.of("email", email));
            output.put("flash_message", helperTrait.__lang("email-changed-to", newEmail));
        }
        output.put("pageTitle", helperTrait.__lang("Change Your Email"));
        return new ModelAndView("admin/account/email", output);
    }

    @PostMapping("/password")
    public ModelAndView password(@Valid @RequestBody Request request) {
        Map<String, Object> output = new HashMap<>();
        User user = this.getAdmin();
        AccountsTable accountsTable = new AccountsTable();

        if (request.getMethod().equals("POST")) {
            this.validate(request, new String[]{"password"}, new String[]{"required", "string", "min:8", "confirmed"});
            user.setPassword(Hash.make(request.getPassword()));
            user.save();
            output.put("flash_message", helperTrait.__lang("Password changed!"));
        }
        output.put("pageTitle", helperTrait.__lang("Change Your Password"));
        return new ModelAndView("admin/account/password", output);
    }

    @PostMapping("/profile")
    public ModelAndView profile(@Valid @RequestBody Request request) {
        Map<String, Object> output = new HashMap<>();
        AccountsTable accountsTable = new AccountsTable();
        User user = this.getAdmin();
        ProfileForm form = new ProfileForm(null, this.getServiceLocator());
        ProfileFilter filter = new ProfileFilter();
        form.setInputFilter(filter);

        if (request.getMethod().equals("POST")) {
            Map<String, String> post = request.getBody();
            form.setData(post);

            if (form.isValid()) {
                Map<String, String> data = form.getData();
                user.update(data);
                user.getAdmin().update(Map.of("notify", data.get("notify"), "about", data.get("about")));
                output.put("flash_message", helperTrait.__lang("Changes Saved!"));
            } else {
                output.put("flash_message", helperTrait.__lang("Submission Failed"));
            }
        } else {
            form.setData(Map.of(
                "name", user.getName(),
                "last_name", user.getLastName(),
                "notify", user.getAdmin().getNotify(),
                "about", user.getAdmin().getAbout()
            ));
        }

        if (user.getPicture() != null && new File(DIR_MER_IMAGE + user.getPicture()).exists()) {
            output.put("display_image", resizeImage(user.getPicture(), 100, 100, this.getBaseUrl()));
        } else {
            output.put("display_image", resizeImage("img/no_image.jpg", 100, 100, this.getBaseUrl()));
        }

        output.put("no_image", resizeImage("img/no_image.jpg", 100, 100, this.getBaseUrl()));
        output.put("form", form);
        output.put("pageTitle", helperTrait.__lang("Profile"));
        return new ModelAndView("admin/account/profile", output);
    }
}

