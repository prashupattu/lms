package com.example.controllers.api;

import com.example.models.*;
import com.example.services.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;

import java.time.Instant;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.*;
import java.util.stream.Collectors;

@RestController
@RequestMapping("/api")
public class AppController {

    @Autowired
    private AuthenticationManager authenticationManager;

    @Autowired
    private UserService userService;

    @Autowired
    private StudentService studentService;

    @Autowired
    private SettingService settingService;

    @Autowired
    private CurrencyService currencyService;

    @Autowired
    private CourseService courseService;

    @Autowired
    private NewsflashService newsflashService;

    @Autowired
    private StudentFieldService studentFieldService;

    @Autowired
    private CourseCategoryService courseCategoryService;

    @Autowired
    private PasswordEncoder passwordEncoder;

    @PostMapping("/login")
    public ResponseEntity<?> login(@RequestBody LoginRequest loginRequest) {
        Authentication authentication = authenticationManager.authenticate(
            new UsernamePasswordAuthenticationToken(loginRequest.getEmail(), loginRequest.getPassword())
        );

        SecurityContextHolder.getContext().setAuthentication(authentication);

        User user = userService.findByEmail(loginRequest.getEmail());

        if (user.getRoleId() != 2) {
            return ResponseEntity.badRequest().body(Map.of("status", false, "msg", "Only students can login"));
        }

        Student student = user.getStudent();
        String token = student.getApiToken();

        if (token == null || token.isEmpty()) {
            do {
                token = UUID.randomUUID().toString().replace("-", "");
            } while (studentService.findByApiToken(token) != null);

            student.setApiToken(token);
        }

        LocalDateTime tokenExpires = LocalDateTime.now().plusDays(365);
        student.setTokenExpires(tokenExpires);
        studentService.save(student);

        Map<String, Object> response = new HashMap<>();
        response.put("id", student.getId());
        response.put("first_name", user.getName());
        response.put("last_name", user.getLastName());
        response.put("token", token);
        response.put("status", true);
        response.put("user_id", student.getUserId());
        response.put("picture", user.getPicture());

        return ResponseEntity.ok(response);
    }

    @GetMapping("/setup")
    public ResponseEntity<?> setup() {
        Map<String, Object> output = new HashMap<>();

        // Settings
        Map<String, String> settings = settingService.getAllSettings();
        List<String> exclude = Arrays.asList("general_chat_code", "general_header_scripts", "general_foot_scripts",
                "footer_newsletter_code", "footer_credits", "sms_enabled", "sms_sender_name", "social_enable_facebook",
                "social_facebook_secret", "social_facebook_app_id", "social_enable_google", "social_google_secret", "social_google_id");
        exclude.forEach(settings::remove);
        output.put("settings", settings);

        // Currencies
        List<Map<String, Object>> currencies = currencyService.getAllCurrencies().stream()
                .map(currency -> {
                    Map<String, Object> currencyMap = new HashMap<>();
                    currencyMap.put("currency_id", currency.getId());
                    currencyMap.put("currency_code", currency.getCountry().getCurrencyCode());
                    currencyMap.put("currency_name", currency.getCountry().getCurrencyName());
                    currencyMap.put("currency_symbol", currency.getCountry().getSymbolLeft());
                    currencyMap.put("exchange_rate", currency.getExchangeRate());
                    return currencyMap;
                })
                .collect(Collectors.toList());
        output.put("currencies", currencies);

        output.put("student_currency", currencyService.getCurrentCurrency().getId());

        output.put("base_path", "http://localhost:8080"); // Replace with actual base URL

        // Widgets
        List<Map<String, Object>> widgets = new ArrayList<>();
        // Implement widget logic here

        output.put("widgets", widgets);

        // Registration fields
        List<Map<String, Object>> registration = studentFieldService.getEnabledFields().stream()
                .map(field -> {
                    Map<String, Object> fieldMap = new HashMap<>();
                    fieldMap.put("registration_field_id", field.getId());
                    fieldMap.put("name", field.getName());
                    fieldMap.put("sort_order", field.getSortOrder());
                    fieldMap.put("type", field.getType());
                    fieldMap.put("options", Arrays.asList(field.getOptions().split("\n")));
                    fieldMap.put("required", field.isRequired());
                    fieldMap.put("placeholder", field.getPlaceholder());
                    fieldMap.put("status", field.isEnabled());
                    return fieldMap;
                })
                .collect(Collectors.toList());
        output.put("registration", registration);

        // Categories
        List<Map<String, Object>> categories = courseCategoryService.getEnabledCategories().stream()
                .map(category -> {
                    Map<String, Object> categoryMap = new HashMap<>();
                    categoryMap.put("session_category_id", category.getId());
                    categoryMap.put("category_name", category.getName());
                    return categoryMap;
                })
                .collect(Collectors.toList());
        output.put("categories", categories);

        output.put("mode", System.getenv("APP_MODE"));

        return ResponseEntity.ok(output);
    }

    @PutMapping("/update")
    public ResponseEntity<?> update(@RequestBody Student updatedStudent, Authentication authentication) {
        Student student = studentService.getApiStudent(authentication);
        student.updateFrom(updatedStudent);
        studentService.save(student);

        User user = student.getUser();
        user.updateFrom(updatedStudent);
        userService.save(user);

        return ResponseEntity.ok(student);
    }
}

